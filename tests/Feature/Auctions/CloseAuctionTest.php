<?php

declare(strict_types=1);

use App\Auctions\Actions\CloseAuction;
use App\Auctions\Actions\PlaceBid;
use App\Auctions\Events\AuctionClosed;
use App\Auctions\Jobs\CloseAuctionJob;
use App\Enums\AuctionEventType;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\VatRegime;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\AutoBid;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function closeIt(Auction $auction): ?Order
{
    return app(CloseAuction::class)->handle($auction);
}

function bidOn(Auction $auction, User $user, int $amountCents): void
{
    app(PlaceBid::class)->handle($auction, $user, $amountCents, (string) Str::uuid());
}

describe('met een winnaar', function () {
    beforeEach(function () {
        $this->lot = Lot::factory()->live()->create(['vat_regime' => VatRegime::Margin]);
        $this->auction = Auction::factory()->create([
            'lot_id' => $this->lot->id,
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);
        $this->winner = User::factory()->verified()->create();

        bidOn($this->auction, $this->winner, 100_000);
    });

    it('maakt een order aan op de hamerslag', function () {
        $order = closeIt($this->auction->fresh());

        expect($order)->not->toBeNull()
            ->and($order->buyer_id)->toBe($this->winner->id)
            ->and($order->seller_id)->toBe($this->lot->seller_id)
            ->and($order->hammer_cents)->toBe(100_000)
            ->and($order->status)->toBe(OrderStatus::AwaitingPayment);
    });

    it('rekent het ordertotaal met dezelfde klasse als de kavelpagina', function () {
        // Zouden die twee uiteenlopen, dan is dat per definitie een bug en
        // geen afronding (hfst. 9).
        $order = closeIt($this->auction->fresh());

        expect($order->total_cents)
            ->toBe(Money::forOrder($this->lot, 100_000)->totalCents);
    });

    it('geeft de koper achtenveertig uur om te betalen', function () {
        $order = closeIt($this->auction->fresh());

        expect($order->payment_deadline_at->diffInHours(now()->addHours(48), absolute: true))
            ->toBeLessThan(1);
    });

    it('zet de kavel op verkocht', function () {
        closeIt($this->auction->fresh());

        expect($this->lot->fresh()->status)->toBe(LotStatus::Sold);
    });

    it('legt de winnaar en het winnende bod vast', function () {
        closeIt($this->auction->fresh());

        $closed = $this->auction->fresh();

        expect($closed->winner_id)->toBe($this->winner->id)
            ->and($closed->winning_bid_id)->not->toBeNull()
            ->and($closed->closed_at)->not->toBeNull();
    });

    it('schrijft een sluitevent met de uitkomst', function () {
        closeIt($this->auction->fresh());

        $event = AuctionEvent::where('auction_id', $this->auction->id)
            ->where('type', AuctionEventType::Closed)
            ->sole();

        expect($event->payload['sold'])->toBeTrue()
            ->and($event->payload['final_price_cents'])->toBe(100_000)
            ->and($event->payload['order_number'])->not->toBeNull();
    });

    it('zet lopende maxima stop', function () {
        AutoBid::factory()->for($this->auction)->create(['max_amount_cents' => 500_000]);

        closeIt($this->auction->fresh());

        expect(AutoBid::active()->count())->toBe(0);
    });
});

describe('zonder winnaar', function () {
    it('laat een kavel zonder biedingen onverkocht', function () {
        $lot = Lot::factory()->live()->create();
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        $order = closeIt($auction);

        expect($order)->toBeNull()
            ->and($lot->fresh()->status)->toBe(LotStatus::Unsold)
            ->and(Order::count())->toBe(0);
    });

    it('geeft de kavel terug als de reserveprijs niet gehaald is', function () {
        $lot = Lot::factory()->live()->create();
        $auction = Auction::factory()->withReserve(500_000)->create([
            'lot_id' => $lot->id,
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        bidOn($auction, User::factory()->verified()->create(), 100_000);

        $order = closeIt($auction->fresh());

        expect($order)->toBeNull()
            ->and($lot->fresh()->status)->toBe(LotStatus::Unsold)
            ->and($auction->fresh()->reserve_met)->toBeFalse()
            ->and($auction->fresh()->winner_id)->toBeNull();
    });

    it('verkoopt wel zodra de reserveprijs gehaald is', function () {
        $lot = Lot::factory()->live()->create();
        $auction = Auction::factory()->withReserve(150_000)->create([
            'lot_id' => $lot->id,
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        bidOn($auction, User::factory()->verified()->create(), 150_000);

        expect($auction->fresh()->reserve_met)->toBeTrue()
            ->and(closeIt($auction->fresh()))->not->toBeNull();
    });
});

describe('dubbel sluiten', function () {
    it('maakt geen tweede order aan', function () {
        // De ingeplande job en het vangnet kunnen elkaar overlappen.
        $lot = Lot::factory()->live()->create();
        $auction = Auction::factory()->create([
            'lot_id' => $lot->id,
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);
        bidOn($auction, User::factory()->verified()->create(), 100_000);

        $first = closeIt($auction->fresh());
        $second = closeIt($auction->fresh());

        expect(Order::count())->toBe(1)
            ->and($second->id)->toBe($first->id);
    });
});

describe('de ingeplande job', function () {
    it('plant zichzelf opnieuw in als de veiling verlengd is', function () {
        Queue::fake();

        $auction = Auction::factory()->endingIn(300)->create();

        (new CloseAuctionJob($auction->id))->handle(app(CloseAuction::class));

        Queue::assertPushed(CloseAuctionJob::class);
        expect($auction->fresh()->closed_at)->toBeNull();
    });

    it('sluit wel als de eindtijd echt verstreken is', function () {
        $lot = Lot::factory()->live()->create();
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        // De eindtijd voorbij laten gaan zonder de motor te omzeilen.
        $this->travelTo($auction->ends_at->addSecond());

        (new CloseAuctionJob($auction->id))->handle(app(CloseAuction::class));

        expect($auction->fresh()->closed_at)->not->toBeNull();
    });

    it('doet niets bij een al gesloten veiling', function () {
        Event::fake();

        $auction = Auction::factory()->closed()->create();

        (new CloseAuctionJob($auction->id))->handle(app(CloseAuction::class));

        Event::assertNotDispatched(AuctionClosed::class);
    });
});

describe('het vangnet', function () {
    it('vindt niets als alles netjes gesloten is', function () {
        Auction::factory()->closed()->create();

        $this->artisan('auctions:close-overdue')
            ->expectsOutputToContain('Geen achterstallige veilingen.')
            ->assertSuccessful();
    });

    it('sluit een veiling die is blijven hangen', function () {
        // Bijvoorbeeld na een herstart waarbij de ingeplande job verloren ging.
        $lot = Lot::factory()->live()->create();
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        $this->travelTo($auction->ends_at->addMinutes(5));

        $this->artisan('auctions:close-overdue')->assertSuccessful();

        expect($auction->fresh()->closed_at)->not->toBeNull()
            ->and($lot->fresh()->status)->toBe(LotStatus::Unsold);
    });
});
