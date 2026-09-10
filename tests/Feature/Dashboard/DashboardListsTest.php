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

it('toont een eerlijke lege staat', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($user)->get('/dashboard/geschillen')
        ->assertOk()
        ->assertSee('Geen geschillen');

    $this->actingAs($user)->get('/dashboard/kavels')
        ->assertOk()
        ->assertSee('Nog geen kavels');
});
