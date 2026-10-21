<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;

/*
| Delen (briefing stap 10): de eigenaar krijgt op de kavelpagina en op de
| bewerkpagina een deelblok met een link per kanaal, zodat de herkomst in
| de statistieken terugkomt. Andere bezoekers houden de gewone deelknop.
*/

function sharedLot(User $seller): Lot
{
    $lot = Lot::factory()->for($seller, 'seller')->create(['status' => LotStatus::Live, 'title' => 'Linde H25 heftruck']);
    Auction::factory()->for($lot)->create();

    return $lot;
}

it('toont de eigenaar deellinks met herkomst per kanaal op de kavelpagina', function () {
    $seller = User::factory()->verified()->create();
    $lot = sharedLot($seller);

    $this->actingAs($seller)->get(route('lots.show', $lot))
        ->assertOk()
        ->assertSee('Kopieer link')
        ->assertSee('utm_medium%3Dwhatsapp', escape: false)
        ->assertSee('utm_campaign=kavel-'.$lot->public_id)
        ->assertSee('wa.me', escape: false)
        ->assertSee('linkedin.com/sharing', escape: false);
});

it('houdt het deelblok weg bij andere bezoekers', function () {
    $lot = sharedLot(User::factory()->verified()->create());

    $this->actingAs(User::factory()->create())->get(route('lots.show', $lot))
        ->assertOk()
        ->assertDontSee('Kopieer link')
        ->assertDontSee('utm_medium%3Dwhatsapp', escape: false);

    $this->get(route('lots.show', $lot))->assertOk()->assertDontSee('utm_medium%3Dwhatsapp', escape: false);
});

it('toont het deelblok met plaktekst op de bewerkpagina van een publieke kavel', function () {
    $seller = User::factory()->verified()->create();
    $lot = sharedLot($seller);

    $this->actingAs($seller)->get(route('dashboard.lots.edit', $lot))
        ->assertOk()
        ->assertSee('Delen')
        ->assertSee('Kopieer tekst')
        ->assertSee('bekijk op Aucvy');

    $draft = Lot::factory()->for($seller, 'seller')->create(['status' => LotStatus::Draft]);

    $this->actingAs($seller)->get(route('dashboard.lots.edit', $draft))
        ->assertOk()
        ->assertDontSee('utm_medium%3Dwhatsapp', escape: false);
});
