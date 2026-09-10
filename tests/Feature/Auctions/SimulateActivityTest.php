<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\Lot;

/*
| De demosimulator: biedt via de echte biedmotor en laat kijkers ademen.
| Eén beurt (--once) moet dus een geldig bod en een kijkersaantal opleveren.
*/

it('plaatst in een beurt een geldig bod via de echte motor', function () {
    $lot = Lot::factory()->live()->create(['value_max_cents' => 1_000_000]);
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);

    $this->artisan('aucvy:simulate', ['--once' => true])->assertSuccessful();

    $auction->refresh();

    expect($auction->bid_count)->toBe(1)
        ->and($auction->current_price_cents)->toBeGreaterThanOrEqual(100_000);

    $this->getJson("/api/auctions/{$auction->id}/state")
        ->assertJsonPath('bid_count', 1)
        ->assertJson(fn ($json) => $json->where('watching_now', fn ($count) => $count >= 2)->etc());
});

it('stopt met bieden boven het prijsplafond', function () {
    $lot = Lot::factory()->live()->create(['value_max_cents' => 100_000]);
    Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        // Al boven anderhalf keer de bovenkant van de indicatie.
        'current_price_cents' => 200_000,
    ]);

    $this->artisan('aucvy:simulate', ['--once' => true])
        ->expectsOutputToContain('Geen veiling')
        ->assertSuccessful();
});
