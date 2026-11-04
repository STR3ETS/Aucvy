<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceAutoBid;
use App\Auctions\Actions\PlaceBid;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->auction = Auction::factory()->withReserve(150_000)->create([
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);
});

function stateUrl(Auction $auction): string
{
    return "/api/auctions/{$auction->id}/state";
}

describe('toegang', function () {
    it('is leesbaar zonder account', function () {
        // De kavelpagina moet ook voor een bezoeker kloppen, en dit is de
        // pollingfallback als de websocket wegvalt (hfst. 11).
        test()->getJson(stateUrl($this->auction))->assertOk();
    });
});

describe('de stand', function () {
    it('bevat alles wat het biedpaneel nodig heeft', function () {
        test()->getJson(stateUrl($this->auction))
            ->assertOk()
            ->assertJsonStructure([
                'auction_id', 'lot_public_id', 'sequence', 'server_time',
                'sale_type', 'status', 'price_cents', 'price_formatted',
                'bid_count', 'bidder_count', 'ends_at', 'original_ends_at',
                'extension_count', 'has_reserve', 'reserve_met',
                'minimum_bid_cents', 'history',
            ]);
    });

    it('meldt dat er een reserveprijs is zonder hem te noemen', function () {
        $response = test()->getJson(stateUrl($this->auction))->assertOk();

        expect($response->json('has_reserve'))->toBeTrue()
            ->and($response->json('reserve_met'))->toBeFalse()
            ->and(json_encode($response->json()))->not->toContain('150000');
    });

    it('telt het volgnummer mee zodat de client weet waar hij staat', function () {
        app(PlaceBid::class)->handle(
            $this->auction, User::factory()->verified()->create(), 100_000, (string) Str::uuid(),
        );

        test()->getJson(stateUrl($this->auction->fresh()))
            ->assertJsonPath('sequence', 1);
    });

    it('geeft de biedhistorie zonder volledige namen', function () {
        $bidder = User::factory()->verified()->create(['name' => 'Robert Jansen', 'display_name' => null]);

        app(PlaceBid::class)->handle($this->auction, $bidder, 100_000, (string) Str::uuid());

        $response = test()->getJson(stateUrl($this->auction->fresh()))->assertOk();

        expect($response->json('history.0.bidder_label'))->toBe($bidder->publicName())
            ->and(json_encode($response->json()))->not->toContain('Robert Jansen');
    });
});

describe('de kijker', function () {
    it('blijft leeg voor een bezoeker zonder account', function () {
        test()->getJson(stateUrl($this->auction))->assertJsonPath('viewer', null);
    });

    it('vertelt een ingelogde gebruiker waar hij staat', function () {
        $user = User::factory()->verified()->create();
        $this->actingAs($user);

        test()->getJson(stateUrl($this->auction))
            ->assertJsonPath('viewer.state', 'idle')
            ->assertJsonPath('viewer.can_bid', true)
            ->assertJsonPath('viewer.is_seller', false);
    });

    it('meldt dat verificatie nog nodig is', function () {
        $this->actingAs(User::factory()->create());

        test()->getJson(stateUrl($this->auction))
            ->assertJsonPath('viewer.can_bid', false);
    });

    it('toont je eigen maximum alleen aan jezelf', function () {
        $anna = User::factory()->verified()->create();
        app(PlaceAutoBid::class)->handle($this->auction, $anna, 240_000);

        // Anna ziet haar eigen plafond.
        $this->actingAs($anna);
        test()->getJson(stateUrl($this->auction->fresh()))
            ->assertJsonPath('viewer.max_amount_cents', 240_000);

        // Iemand anders niet, nergens in de respons.
        $this->actingAs(User::factory()->verified()->create());
        $response = test()->getJson(stateUrl($this->auction->fresh()))->assertOk();

        expect($response->json('viewer.max_amount_cents'))->toBeNull()
            ->and(json_encode($response->json()))->not->toContain('240000');
    });
});

describe('afslag', function () {
    it('levert alles om zelf af te tellen', function () {
        // De client rekent met dezelfde formule als de server; er wordt niets
        // per tik weggeschreven (hfst. 6).
        $auction = Auction::factory()
            ->dutch(startCents: 200_000, floorCents: 50_000, stepCents: 25_000, intervalSeconds: 20)
            ->create(['starts_at' => now()]);

        test()->getJson(stateUrl($auction))
            ->assertOk()
            ->assertJsonPath('sale_type', 'dutch')
            ->assertJsonPath('dutch.price_cents', 200_000)
            ->assertJsonPath('dutch.floor_reached', false)
            ->assertJsonStructure(['dutch' => [
                // start_cents hoort erbij: zonder de startprijs kan de client
                // de formule niet zelf doorrekenen.
                'price_cents', 'start_cents', 'floor_cents', 'step_cents',
                'interval_seconds', 'starts_at', 'next_drop_at',
                'floor_reached', 'server_time',
            ]]);
    });

    it('rekent de prijs opnieuw uit bij elke aanvraag', function () {
        $auction = Auction::factory()
            ->dutch(startCents: 200_000, floorCents: 50_000, stepCents: 25_000, intervalSeconds: 20)
            ->create(['starts_at' => now()]);

        test()->getJson(stateUrl($auction))->assertJsonPath('price_cents', 200_000);

        $this->travel(40)->seconds();

        test()->getJson(stateUrl($auction))->assertJsonPath('price_cents', 150_000);
    });
});
