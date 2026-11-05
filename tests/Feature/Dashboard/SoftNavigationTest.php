<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\Conversation;
use App\Models\Lot;
use App\Models\User;

/*
| Naadloze paginawissels in het dashboard (dash-nav.js): de layout zegt per
| pagina of hij zonder volledige reload gewisseld mag worden. Een pagina met
| eigen scripts moet altijd volledig laden, anders draaien die scripts niet.
*/

it('markeert gewone dashboardpagina\'s als geschikt voor een zachte wissel', function (string $url) {
    $this->actingAs(User::factory()->verified()->create())
        ->get($url)
        ->assertOk()
        ->assertSee('data-soft-nav="on"', escape: false)
        ->assertSee('dash-main', escape: false);
})->with(['/dashboard', '/dashboard/kavels', '/dashboard/biedingen', '/bestellingen', '/dashboard/berichten']);

it('laadt een pagina met eigen scripts altijd volledig', function () {
    $lot = Lot::factory()->live()->create();
    $buyer = User::factory()->verified()->create();
    $conversation = Conversation::create([
        'lot_id' => $lot->id,
        'buyer_id' => $buyer->id,
        'seller_id' => $lot->seller_id,
        'kind' => Conversation::KIND_CHAT,
        'status' => Conversation::STATUS_OPEN,
    ]);

    $this->actingAs($buyer)
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSee('data-soft-nav="off"', escape: false);
});

it('toont de transportindicatie op een kavel met een vanaf-tarief', function () {
    $lot = Lot::factory()->live()->create(['pickup_only' => false, 'transport_from_cents' => 34_000]);
    Auction::factory()->create(['lot_id' => $lot->id]);

    $this->get(route('lots.show', $lot))
        ->assertOk()
        ->assertSee('Transportindicatie');
});
