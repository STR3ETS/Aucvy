<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Enums\PayoutStatus;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;

/*
| De lijstpagina's onder het dashboard (hfst. 7): kavels, biedingen,
| uitbetalingen en geschillen - elk met een eerlijke lege staat.
*/

it('vraagt om inloggen', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/dashboard/kavels', '/dashboard/biedingen', '/dashboard/uitbetalingen', '/dashboard/geschillen']);

it('toont de eigen kavels met status en stand', function () {
    $lot = Lot::factory()->live()->create(['title' => 'Minigraafmachine 3,5 ton']);
    Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 150_000,
    ]);

    $this->actingAs($lot->seller)
        ->get('/dashboard/kavels')
        ->assertOk()
        ->assertSee('Minigraafmachine 3,5 ton')
        ->assertSee('1.500,00');
});

it('toont per veiling je laatste bod met je positie', function () {
    $bidder = User::factory()->verified()->create();
    $lot = Lot::factory()->live()->create();
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);

    app(PlaceBid::class)->handle($auction, $bidder, 100_000, 'test:'.$auction->id);

    $this->actingAs($bidder)
        ->get('/dashboard/biedingen')
        ->assertOk()
        ->assertSee($lot->title)
        ->assertSee('Hoogste bieder');
});

it('zet biedingen per stand in een eigen tabel en filtert via de tegels', function () {
    $bidder = User::factory()->verified()->create();
    $lot = Lot::factory()->live()->create();
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);

    app(PlaceBid::class)->handle($auction, $bidder, 100_000, 'test:'.$auction->id);

    // Zonder filter: de tabel van de stand, met de koppen één keer en de actie als pictogram.
    $this->actingAs($bidder)
        ->get('/dashboard/biedingen')
        ->assertOk()
        ->assertSeeInOrder(['id="biedingen-bovenaan"', 'class="d-lottable-head"', 'Jouw bod', 'Waarde volgens Atlas', 'Uitkomst', $lot->title, 'data-tip="Bekijk kavel"'], false)
        ->assertDontSee('id="biedingen-overboden"', false);

    // De tegel Overboden filtert: daar staat deze kavel niet.
    $this->actingAs($bidder)
        ->get('/dashboard/biedingen?toon=overboden')
        ->assertOk()
        ->assertSee('Je bent nergens overboden.')
        ->assertDontSee('id="biedingen-bovenaan"', false);
});

it('toont uitbetalingen met status en vrijgavemoment', function () {
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->live()->create(['seller_id' => $seller->id]);
    $order = Order::factory()->completed()->create(['seller_id' => $seller->id, 'lot_id' => $lot->id]);

    Payout::forceCreate([
        'order_id' => $order->id,
        'seller_id' => $seller->id,
        'amount_cents' => 92_500,
        'fee_cents' => 7_500,
        'status' => PayoutStatus::Held,
        'hold_until' => now()->addHours(48),
    ]);

    $this->actingAs($seller)
        ->get('/dashboard/uitbetalingen')
        ->assertOk()
        ->assertSee('925,00')
        ->assertSee('vrijgave');
});

it('zet uitbetalingen per stand in een eigen tabel en filtert via de tegels', function () {
    $seller = User::factory()->verified()->create();
    $make = function (string $title, PayoutStatus $status, array $extra = []) use ($seller): void {
        $lot = Lot::factory()->live()->create(['seller_id' => $seller->id, 'title' => $title]);
        $order = Order::factory()->completed()->create(['seller_id' => $seller->id, 'lot_id' => $lot->id]);

        Payout::forceCreate(['order_id' => $order->id, 'seller_id' => $seller->id, 'amount_cents' => 92_500, 'fee_cents' => 7_500, 'status' => $status, ...$extra]);
    };
    $make('Koelcel in de houdtermijn', PayoutStatus::Held, ['hold_until' => now()->addHours(48)]);
    $make('Heftruck vrijgegeven', PayoutStatus::Released, ['released_at' => now()->subDay()]);

    // Zonder filter: wat onderweg is eerst, dan wat is vrijgegeven, met de koppen één keer.
    $this->actingAs($seller)
        ->get('/dashboard/uitbetalingen')
        ->assertOk()
        ->assertSeeInOrder(['Uitbetalingsrekening', 'id="uitbetalingen-onderweg"', 'class="d-lottable-head"', 'Netto', 'Koelcel in de houdtermijn', 'In houdtermijn, vrijgave', 'data-tip="Bekijk uitbetaling"', 'id="uitbetalingen-klaar"', 'Heftruck vrijgegeven', 'Vrijgegeven op'], false)
        ->assertDontSee('Uitbetalingsproces');

    // De tegel filtert.
    $this->actingAs($seller)
        ->get('/dashboard/uitbetalingen?toon=klaar')
        ->assertOk()
        ->assertSee('Heftruck vrijgegeven')
        ->assertDontSee('Koelcel in de houdtermijn');
});

it('toont een eerlijke lege staat', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($user)->get('/dashboard/geschillen')
        ->assertOk()
        ->assertSee('Geen geschillen');

    $this->actingAs($user)->get('/dashboard/kavels')
        ->assertOk()
        ->assertSee('Nog geen kavels');
});

it('toont één uitbetaling met de stand en de stappen, alleen aan wie hem mag zien', function () {
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->live()->create(['seller_id' => $seller->id, 'title' => 'Bakoven Unox 4 niveaus']);
    $order = Order::factory()->completed()->create(['seller_id' => $seller->id, 'lot_id' => $lot->id, 'hammer_cents' => 100_000]);

    $payout = Payout::forceCreate([
        'order_id' => $order->id,
        'seller_id' => $seller->id,
        'amount_cents' => 92_500,
        'fee_cents' => 7_500,
        'status' => PayoutStatus::Held,
        'hold_until' => now()->addHours(48),
    ]);

    $this->actingAs($seller)
        ->get(route('dashboard.payouts'))
        ->assertOk()
        // Het proces staat niet meer als los blok bovenaan: de stand staat per uitbetaling in de tabel.
        ->assertSee('id="uitbetalingen-onderweg"', false)
        ->assertSee(route('dashboard.payouts.show', $payout), escape: false);

    $this->actingAs($seller)
        ->get(route('dashboard.payouts.show', $payout))
        ->assertOk()
        ->assertSee('Uitbetaling in de houdtermijn')
        ->assertSee('Bakoven Unox 4 niveaus')
        ->assertSee('925,00')
        ->assertSee('Succesfee (7,5%)')
        // Tijdens de houdtermijn beloven we nog niet dat het bedrag is verzonden.
        ->assertDontSee('Uitbetaling is verzonden');

    $this->actingAs(User::factory()->verified()->create())
        ->get(route('dashboard.payouts.show', $payout))
        ->assertForbidden();
});

it('zet live, ingepland en concept elk in een eigen tabel, met de waarde volgens Atlas', function () {
    $seller = User::factory()->verified()->create();
    $live = Lot::factory()->live()->create([
        'seller_id' => $seller->id,
        'title' => 'Combisteamer Rational',
        'value_min_cents' => 200_000,
        'value_max_cents' => 300_000,
        'value_confidence' => 'high',
    ]);
    Auction::factory()->create(['lot_id' => $live->id, 'start_price_cents' => 100_000, 'current_price_cents' => 350_000]);
    Lot::factory()->create(['seller_id' => $seller->id, 'status' => 'scheduled', 'title' => 'Koffiebar-toonbank']);
    Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'Designfauteuil']);

    $content = $this->actingAs($seller)
        ->get(route('dashboard.lots'))
        ->assertOk()
        // Drie tabellen onder elkaar, elk met zijn eigen kavel.
        ->assertSeeInOrder(['id="kavels-live"', 'Combisteamer Rational', 'id="kavels-gepland"', 'Koffiebar-toonbank', 'id="kavels-concept"', 'Designfauteuil'], escape: false)
        // Bij de live kavel staat de waarde en de uitkomst: het bod zit erboven.
        ->assertSee('Waarde volgens Atlas')
        ->assertSee('€ 2.000 – € 3.000')
        ->assertSee('Boven de waarde-indicatie')
        ->getContent();

    expect(substr_count($content, 'class="d-lottable-head"'))->toBe(3);

    // Een tegel als filter laat alleen die tabel over.
    $this->actingAs($seller)
        ->get(route('dashboard.lots', ['status' => 'concept']))
        ->assertOk()
        ->assertSee('id="kavels-concept"', escape: false)
        ->assertDontSee('id="kavels-live"', escape: false)
        ->assertDontSee('Combisteamer Rational');
});
