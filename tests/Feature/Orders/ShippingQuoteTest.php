<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Lot;

/*
| De transportindicatie (hfst. 7 en 14): postcode in, prijs uit, 24 uur
| gecached per kavel-postcodecombinatie. Providervrij - dit test de fake,
| maar vooral het contract: deterministisch, gecached, en eerlijk als een
| kavel niet bezorgd wordt.
*/

it('geeft een indicatie met houdbaarheid', function () {
    $lot = Lot::factory()->live()->create(['transport_from_cents' => 34_000]);

    $fromRegion = (int) substr($lot->location->postal_code, 0, 2);
    $expected = 34_000 + abs($fromRegion - 10) * 450;

    $this->postJson('/api/shipping/quote', ['lot_id' => $lot->id, 'postcode' => '1012 AB'])
        ->assertOk()
        ->assertJsonPath('quote.amount_cents', $expected)
        ->assertJsonStructure(['quote' => ['amount_cents', 'amount_formatted', 'valid_until']]);
});

it('rekent de kostenopbouw server-side mee met de indicatie', function () {
    $lot = Lot::factory()->live()->create(['transport_from_cents' => 34_000]);
    Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 800_000,
        'current_price_cents' => 800_000,
    ]);

    $fromRegion = (int) substr($lot->location->postal_code, 0, 2);
    $expectedTransport = 34_000 + abs($fromRegion - 10) * 450;

    $this->postJson('/api/shipping/quote', ['lot_id' => $lot->id, 'postcode' => '1012 AB'])
        ->assertOk()
        ->assertJsonPath('costs.transport_cents', $expectedTransport)
        ->assertJsonPath('costs.hammer_cents', 800_000)
        ->assertJsonStructure(['costs' => ['lines', 'total_formatted', 'total_cents']]);
});

it('cachet per kavel en postcode - twee kijkers zien hetzelfde', function () {
    $lot = Lot::factory()->live()->create(['transport_from_cents' => 34_000]);

    $first = $this->postJson('/api/shipping/quote', ['lot_id' => $lot->id, 'postcode' => '1012 AB'])
        ->json('quote.valid_until');

    $this->travel(30)->minutes();

    // Zelfde postcode, ook zonder spatie en in kleine letters: zelfde cache.
    $second = $this->postJson('/api/shipping/quote', ['lot_id' => $lot->id, 'postcode' => '1012ab'])
        ->json('quote.valid_until');

    expect($second)->toBe($first);
});

it('is eerlijk over kavels die niet bezorgd worden', function () {
    $lot = Lot::factory()->live()->create(['transport_from_cents' => null, 'pickup_only' => true]);

    $this->postJson('/api/shipping/quote', ['lot_id' => $lot->id, 'postcode' => '1012 AB'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Deze kavel wordt niet bezorgd - ophalen in overleg.');
});

it('valideert de postcode', function () {
    $lot = Lot::factory()->live()->create(['transport_from_cents' => 34_000]);

    $this->postJson('/api/shipping/quote', ['lot_id' => $lot->id, 'postcode' => '123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('postcode');
});

it('kent geen concepten', function () {
    $lot = Lot::factory()->create(['status' => LotStatus::Draft, 'transport_from_cents' => 34_000]);

    $this->postJson('/api/shipping/quote', ['lot_id' => $lot->id, 'postcode' => '1012 AB'])
        ->assertNotFound();
});
