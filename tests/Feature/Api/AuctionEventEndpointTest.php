<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->auction = Auction::factory()->create([
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);
});

function eventsUrl(Auction $auction, int $after = 0): string
{
    return "/api/auctions/{$auction->id}/events?after={$after}";
}

function placeSomeBids(Auction $auction, int $count): void
{
    $amount = 100_000;

    for ($i = 0; $i < $count; $i++) {
        app(PlaceBid::class)->handle(
            $auction->fresh(),
            User::factory()->verified()->create(),
            $amount,
            (string) Str::uuid(),
        );

        $amount += 5_000;
    }
}

it('geeft alles na een volgnummer terug', function () {
    // Dit is wat een client na een netwerkdip weer op de goede stand krijgt:
    // "geef me alles na 431" (hfst. 5).
    placeSomeBids($this->auction, 3);

    $response = test()->getJson(eventsUrl($this->auction->fresh(), 1))->assertOk();

    expect($response->json('events'))->toHaveCount(2)
        ->and($response->json('events.0.sequence'))->toBe(2)
        ->and($response->json('after'))->toBe(1);
});

it('geeft vanaf nul de volledige stroom', function () {
    placeSomeBids($this->auction, 2);

    test()->getJson(eventsUrl($this->auction->fresh()))
        ->assertOk()
        ->assertJsonCount(2, 'events')
        ->assertJsonPath('current_sequence', 2)
        ->assertJsonPath('reload_required', false);
});

it('is leesbaar zonder account', function () {
    test()->getJson(eventsUrl($this->auction))->assertOk();
});

it('draagt per event het type en de payload', function () {
    placeSomeBids($this->auction, 1);

    $response = test()->getJson(eventsUrl($this->auction->fresh()))->assertOk();

    expect($response->json('events.0.type'))->toBe('bid_placed')
        ->and($response->json('events.0.payload.amount_cents'))->toBe(100_000);
});

it('vraagt om een volledige herlaad bij te veel events', function () {
    /*
     | Meer dan het maximum betekent: zoveel gebeurd dat stap voor stap
     | bijwerken langer duurt dan opnieuw beginnen - en dat de client
     | tussendoor op een oude stand zou kunnen bieden (hfst. 7).
     */
    config()->set('aucvy.auction.max_replay_events', 2);

    placeSomeBids($this->auction, 3);

    test()->getJson(eventsUrl($this->auction->fresh()))
        ->assertOk()
        ->assertJsonPath('reload_required', true)
        ->assertJsonPath('events', []);
});

it('lekt geen volledige naam in de eventpayload', function () {
    $bidder = User::factory()->verified()->create(['name' => 'Robert Jansen', 'display_name' => null]);

    app(PlaceBid::class)->handle($this->auction, $bidder, 100_000, (string) Str::uuid());

    $response = test()->getJson(eventsUrl($this->auction->fresh()))->assertOk();

    expect(json_encode($response->json()))
        ->toContain($bidder->publicName())
        ->not->toContain('Robert Jansen');
});

it('geeft een lege lijst als er niets nieuws is', function () {
    placeSomeBids($this->auction, 1);

    test()->getJson(eventsUrl($this->auction->fresh(), 99))
        ->assertOk()
        ->assertJsonPath('events', [])
        ->assertJsonPath('reload_required', false);
});

it('negeert een negatief volgnummer', function () {
    placeSomeBids($this->auction, 1);

    test()->getJson("/api/auctions/{$this->auction->id}/events?after=-5")
        ->assertOk()
        ->assertJsonPath('after', 0)
        ->assertJsonCount(AuctionEvent::count(), 'events');
});
