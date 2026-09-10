<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;

/*
| Biedadvies (hfst. 8): bedragen uit PHP, duiding uit het model, en de
| toegang is per kijker - wie de run niet startte, ziet hem niet.
| Queue draait sync met de FakeProvider.
*/

function adviceLot(): Lot
{
    $lot = Lot::factory()->live()->withValueEstimate()->create();
    Auction::factory()->create(['lot_id' => $lot->id]);

    return $lot;
}

it('geeft een ingelogde kijker advies met zekerheidslabel', function () {
    $lot = adviceLot();
    $viewer = User::factory()->verified()->create();

    $started = $this->actingAs($viewer)
        ->postJson('/api/ivy/tasks', ['task' => 'bid_advice', 'lot_id' => $lot->id])
        ->assertCreated()
        ->json('run');

    $response = $this->actingAs($viewer)
        ->getJson("/api/ivy/tasks/{$started['id']}")
        ->assertOk();

    expect($response->json('run.status'))->toBe('completed')
        ->and($response->json('run.output.assessment'))->toBeString()
        ->and($response->json('run.confidence_level'))->toBeIn(['low', 'medium', 'high'])
        // Geen conceptpayload: dit is geen concepttaak.
        ->and($response->json('draft'))->toBeNull();
});

it('staat als paneel op de kavelpagina voor ingelogde kijkers', function () {
    $lot = adviceLot();

    $this->actingAs(User::factory()->verified()->create())
        ->get(route('lots.show', $lot))
        ->assertOk()
        ->assertSee('Biedadvies van Atlas')
        ->assertSee('Jij beslist')
        // Bieden is een bewuste sleep, geen klik.
        ->assertSee('slide-confirm', false)
        ->assertSee('Sleep om');
});

it('blijft van de pagina voor anonieme bezoekers', function () {
    // Advies is voor bieders; het paneel staat achter @auth.
    $this->get(route('lots.show', adviceLot()))
        ->assertOk()
        ->assertDontSee('Biedadvies van Atlas');
});

it('vraagt een account - advies is voor bieders, niet voor bots', function () {
    $lot = adviceLot();

    $this->postJson('/api/ivy/tasks', ['task' => 'bid_advice', 'lot_id' => $lot->id])
        ->assertStatus(401);
});

it('bestaat niet voor concepten of kavels zonder veiling', function () {
    $draft = Lot::factory()->anonymousDraft()->create();

    $this->actingAs(User::factory()->verified()->create())
        ->postJson('/api/ivy/tasks', ['task' => 'bid_advice', 'lot_id' => $draft->id])
        ->assertNotFound();
});

it('verbergt de run voor iedereen behalve de aanvrager', function () {
    $lot = adviceLot();

    $started = $this->actingAs(User::factory()->verified()->create())
        ->postJson('/api/ivy/tasks', ['task' => 'bid_advice', 'lot_id' => $lot->id])
        ->json('run');

    $this->actingAs(User::factory()->verified()->create())
        ->getJson("/api/ivy/tasks/{$started['id']}")
        ->assertNotFound();
});

it('is voorzichtig zonder waardeschatting: lage zekerheid, geen exacte bedragen', function () {
    $lot = Lot::factory()->live()->create(['value_min_cents' => null, 'value_max_cents' => null, 'value_confidence' => null]);
    Auction::factory()->create(['lot_id' => $lot->id]);

    $viewer = User::factory()->verified()->create();

    $started = $this->actingAs($viewer)
        ->postJson('/api/ivy/tasks', ['task' => 'bid_advice', 'lot_id' => $lot->id])
        ->json('run');

    $response = $this->actingAs($viewer)->getJson("/api/ivy/tasks/{$started['id']}");

    // De ConfidencePolicy: laag betekent dat de UI geen exact maximum toont.
    expect($response->json('run.confidence_level'))->toBe('low');
});
