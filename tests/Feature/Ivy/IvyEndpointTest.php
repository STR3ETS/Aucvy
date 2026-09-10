<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use App\Lots\DraftAccess;
use App\Models\AiRun;
use App\Models\Lot;

/*
| De Ivy-endpoints (hfst. 7 en 8): starten geeft direct een run-id, pollen
| geeft de stand. Toegang loopt via het onderwerp - wie het concept niet mag
| zien, ziet ook de run niet. In tests draait de queue sync, dus direct na
| de POST staat de uitkomst al in de database.
*/

beforeEach(function () {
    $this->withCredentials();

    $this->postJson('/api/drafts');
    $this->lot = Lot::sole();
    $this->lot->update(['description' => 'Complete horecakeuken van Siemens.']);
});

it('start een taak en levert het resultaat via polling', function () {
    $started = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'recognise_lot', 'lot_id' => $this->lot->id])
        ->assertCreated()
        ->json('run');

    expect($started['task'])->toBe('recognise_lot')
        ->and($started['agent'])->toBe('ivy');

    // De queue draait hier sync: de eerstvolgende poll heeft de uitkomst,
    // inclusief de verse conceptstand om als autosave toe te passen.
    $response = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->getJson("/api/ivy/tasks/{$started['id']}")
        ->assertOk();

    expect($response->json('run.status'))->toBe('completed')
        ->and($response->json('run.confidence_level'))->toBeIn(['low', 'medium', 'high'])
        ->and($response->json('run.confidence_hint'))->not->toBeNull()
        ->and($response->json('draft.brand'))->toBe('Siemens')
        ->and($response->json('completeness.score'))->toBeGreaterThan(0);
});

it('weigert een onbekende taaknaam', function () {
    $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'hack_the_planet', 'lot_id' => $this->lot->id])
        ->assertStatus(422);
});

it('geeft een vreemde 404, geen 403', function () {
    $this->withCookie(DraftAccess::COOKIE, 'verkeerd-token')
        ->postJson('/api/ivy/tasks', ['task' => 'recognise_lot', 'lot_id' => $this->lot->id])
        ->assertNotFound();
});

it('verbergt ook de run zelf voor vreemden', function () {
    $started = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'recognise_lot', 'lot_id' => $this->lot->id])
        ->json('run');

    $this->withCookie(DraftAccess::COOKIE, 'verkeerd-token')
        ->getJson("/api/ivy/tasks/{$started['id']}")
        ->assertNotFound();
});

it('slaat direct over als de noodrem om staat', function () {
    // Geen wachtrij, geen wachttijd: de POST zelf zegt al `skipped`, zodat de
    // funnel meteen op handmatig kan terugvallen (hfst. 8).
    config(['aucvy.ai.enabled' => false]);

    $response = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'recognise_lot', 'lot_id' => $this->lot->id])
        ->assertCreated();

    expect($response->json('run.status'))->toBe('skipped')
        ->and($response->json('run.error'))->toContain('Noodrem')
        ->and(AiRun::sole()->status)->toBe(AiRunStatus::Skipped);
});

it('rendert de funnelpagina ook met de noodrem om', function () {
    // Ivy mag nooit een blokkade zijn: zonder AI toont stap 3 de handmatige
    // route en blijft de rest van de funnel gewoon werken.
    config(['aucvy.ai.enabled' => false]);

    $this->get('/verkopen')
        ->assertOk()
        ->assertSee('Ivy staat op dit moment uit');
});
