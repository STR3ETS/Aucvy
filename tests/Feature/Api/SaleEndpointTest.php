<?php

declare(strict_types=1);

use App\Auctions\Exceptions\BidRejectionReason;
use App\Models\Auction;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Support\Money\Money;
use Illuminate\Support\Str;

function idempotent(): array
{
    return ['Idempotency-Key' => (string) Str::uuid()];
}

describe('automatisch bieden', function () {
    beforeEach(function () {
        $this->auction = Auction::factory()->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);
        $this->user = User::factory()->verified()->create();
    });

    it('zet een maximum en geeft alleen aan jou het bedrag terug', function () {
        $this->actingAs($this->user);

        $this->postJson("/api/auctions/{$this->auction->id}/auto-bid", [
            'max_amount_cents' => 240_000,
        ])
            ->assertCreated()
            ->assertJsonPath('auto_bid.max_amount_cents', 240_000)
            // Je komt binnen op de startprijs, niet op je plafond.
            ->assertJsonPath('state.price_cents', 100_000)
            ->assertJsonPath('state.viewer.max_amount_cents', 240_000);
    });

    it('weigert een verlaging met 422', function () {
        $this->actingAs($this->user);

        $url = "/api/auctions/{$this->auction->id}/auto-bid";

        $this->postJson($url, ['max_amount_cents' => 240_000])->assertCreated();

        $this->postJson($url, ['max_amount_cents' => 150_000])
            ->assertStatus(422)
            ->assertJsonPath('reason', BidRejectionReason::AutoBidNotIncreasing->value);
    });

    it('haalt het maximum weg en zegt dat het bod blijft staan', function () {
        $this->actingAs($this->user);

        $url = "/api/auctions/{$this->auction->id}/auto-bid";
        $this->postJson($url, ['max_amount_cents' => 240_000])->assertCreated();

        $this->deleteJson($url)
            ->assertOk()
            ->assertJsonPath('cancelled', true)
            // De UI moet dit expliciet zeggen; de verwachting is vaak het
            // tegenovergestelde (hfst. 7).
            ->assertJsonPath('standing_bid_remains', true)
            ->assertJsonPath('state.viewer.state', 'highest');
    });

    it('vraagt een account', function () {
        $this->postJson("/api/auctions/{$this->auction->id}/auto-bid", [
            'max_amount_cents' => 240_000,
        ])->assertUnauthorized();
    });
});

describe('koop-direct', function () {
    beforeEach(function () {
        $lot = Lot::factory()->live()->create();
        $this->auction = Auction::factory()->withBuyNow(200_000)->create([
            'lot_id' => $lot->id,
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);
        $this->buyer = User::factory()->verified()->create();
    });

    it('rekent zonder te kopen als er niet bevestigd is', function () {
        // Geen schuifknop: eerst tonen, dan pas kopen.
        $this->actingAs($this->buyer);

        $this->postJson("/api/auctions/{$this->auction->id}/buy-now", [
            'transport_cents' => 34_000,
        ], idempotent())
            ->assertOk()
            ->assertJsonPath('confirmed', false)
            // Inclusief de administratiekosten per order (N4), uit dezelfde Money-klasse.
            ->assertJsonPath('costs.total_cents', Money::forOrder($this->auction->lot, 200_000, 34_000)->totalCents);

        expect(Order::count())->toBe(0)
            ->and($this->auction->fresh()->buy_now_active)->toBeTrue();
    });

    it('koopt pas na een expliciete bevestiging', function () {
        $this->actingAs($this->buyer);

        $this->postJson("/api/auctions/{$this->auction->id}/buy-now", [
            'confirm' => true,
        ], idempotent())
            ->assertCreated()
            ->assertJsonPath('confirmed', true)
            ->assertJsonPath('order.total_cents', Money::forOrder($this->auction->lot, 200_000)->totalCents)
            ->assertJsonPath('state.status', 'closed');

        expect(Order::count())->toBe(1);
    });

    it('geeft 409 als koop-direct vervallen is', function () {
        $this->auction->update(['buy_now_active' => false]);
        $this->actingAs($this->buyer);

        $this->postJson("/api/auctions/{$this->auction->id}/buy-now", [
            'confirm' => true,
        ], idempotent())
            ->assertStatus(409)
            ->assertJsonPath('reason', BidRejectionReason::BuyNowUnavailable->value);
    });
});

describe('afmijnen', function () {
    beforeEach(function () {
        $this->auction = Auction::factory()
            ->dutch(startCents: 200_000, floorCents: 50_000, stepCents: 25_000, intervalSeconds: 20)
            ->create(['starts_at' => now()]);

        $this->buyer = User::factory()->verified()->create();

        Deposit::factory()->authorised()->create([
            'auction_id' => $this->auction->id,
            'user_id' => $this->buyer->id,
        ]);
    });

    it('rekent af tegen de prijs van de server', function () {
        $this->actingAs($this->buyer);
        $this->travel(40)->seconds();

        $this->postJson("/api/auctions/{$this->auction->id}/dutch-accept", [
            'seen_price_cents' => 150_000,
        ], idempotent())
            ->assertCreated()
            ->assertJsonPath('order.hammer_cents', 150_000)
            ->assertJsonPath('state.status', 'closed');
    });

    it('geeft 409 met de actuele prijs als het scherm te ver achterloopt', function () {
        $this->actingAs($this->buyer);
        $this->travel(60)->seconds();

        $this->postJson("/api/auctions/{$this->auction->id}/dutch-accept", [
            'seen_price_cents' => 200_000,
        ], idempotent())
            ->assertStatus(409)
            ->assertJsonPath('reason', BidRejectionReason::PriceMoved->value)
            // Zodat de UI direct de nieuwe knop kan tonen.
            ->assertJsonPath('current_price_cents', 125_000);

        expect(Order::count())->toBe(0);
    });

    it('eist de prijs die de client zag', function () {
        $this->actingAs($this->buyer);

        $this->postJson("/api/auctions/{$this->auction->id}/dutch-accept", [], idempotent())
            ->assertStatus(422)
            ->assertJsonValidationErrors('seen_price_cents');
    });
});

describe('volglijst', function () {
    beforeEach(function () {
        $this->lot = Lot::factory()->live()->create();
    });

    it('kan volgen zonder verificatie', function () {
        // Verificatie blokkeert alleen het bieden, niet het rondkijken.
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson("/api/lots/{$this->lot->id}/watch")
            ->assertCreated()
            ->assertJsonPath('watching', true);

        expect(WatchlistItem::count())->toBe(1);
    });

    it('volgt niet dubbel', function () {
        $this->actingAs(User::factory()->create());

        $this->postJson("/api/lots/{$this->lot->id}/watch")->assertCreated();
        $this->postJson("/api/lots/{$this->lot->id}/watch")->assertCreated();

        expect(WatchlistItem::count())->toBe(1);
    });

    it('kan weer ontvolgen', function () {
        $this->actingAs(User::factory()->create());

        $this->postJson("/api/lots/{$this->lot->id}/watch")->assertCreated();

        $this->deleteJson("/api/lots/{$this->lot->id}/watch")
            ->assertOk()
            ->assertJsonPath('watching', false);

        expect(WatchlistItem::count())->toBe(0);
    });

    it('vraagt een account', function () {
        $this->postJson("/api/lots/{$this->lot->id}/watch")->assertUnauthorized();
    });
});
