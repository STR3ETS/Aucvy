<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\Lot;

/*
| De kijkershartslag achter "x kijken nu" (hfst. 7): tabblad-tokens in de
| cache, alleen een aantal naar buiten, en wie stopt met kloppen telt na
| anderhalve minuut niet meer mee.
*/

beforeEach(function () {
    $lot = Lot::factory()->live()->create();

    $this->auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);
});

it('telt unieke tabbladen, niet losse hartslagen', function () {
    $this->postJson("/api/auctions/{$this->auction->id}/presence", ['token' => 'tab-een-12345678'])
        ->assertOk()
        ->assertJsonPath('watching_now', 1);

    // Zelfde tabblad klopt nogmaals: nog steeds één kijker.
    $this->postJson("/api/auctions/{$this->auction->id}/presence", ['token' => 'tab-een-12345678'])
        ->assertJsonPath('watching_now', 1);

    $this->postJson("/api/auctions/{$this->auction->id}/presence", ['token' => 'tab-twee-1234567'])
        ->assertJsonPath('watching_now', 2);
});

it('werkt zonder account - gasten tellen mee', function () {
    $this->assertGuest();

    $this->postJson("/api/auctions/{$this->auction->id}/presence", ['token' => 'gast-tab-1234567'])
        ->assertOk();
});

it('vergeet wie gestopt is met kijken', function () {
    $this->postJson("/api/auctions/{$this->auction->id}/presence", ['token' => 'tab-een-12345678']);

    $this->travel(2)->minutes();

    $this->getJson("/api/auctions/{$this->auction->id}/state")
        ->assertOk()
        ->assertJsonPath('watching_now', 0);
});

it('zet het aantal kijkers in de veilingstand, nooit wie', function () {
    $this->postJson("/api/auctions/{$this->auction->id}/presence", ['token' => 'tab-een-12345678']);

    $response = $this->getJson("/api/auctions/{$this->auction->id}/state")
        ->assertOk()
        ->assertJsonPath('watching_now', 1);

    expect($response->content())->not->toContain('tab-een-12345678');
});
