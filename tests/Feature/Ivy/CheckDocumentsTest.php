<?php

declare(strict_types=1);

use App\Enums\DocumentKind;
use App\Lots\DraftAccess;
use App\Models\AiRun;
use App\Models\Lot;
use App\Models\LotDocument;

/*
| Documentcontrole (hfst. 8: Lex). PHP telt wat ontbreekt; het model kijkt
| naar de inhoud. Concept-gebonden: dezelfde poort als de rest van de funnel.
*/

beforeEach(function () {
    $this->withCredentials();

    $this->postJson('/api/drafts');
    $this->lot = Lot::sole();
    $this->lot->update(['title' => 'Horecakeuken']);
});

it('controleert de documenten en benoemt wat ontbreekt', function () {
    LotDocument::factory()->create([
        'lot_id' => $this->lot->id,
        'kind' => DocumentKind::Invoice,
    ]);

    $started = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'check_documents', 'lot_id' => $this->lot->id])
        ->assertCreated()
        ->json('run');

    $response = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->getJson("/api/ivy/tasks/{$started['id']}")
        ->assertOk();

    // Factuur aanwezig, eigendomsbewijs niet: dat is deterministische input
    // en het hoort terug te komen in de samenvatting.
    expect($response->json('run.status'))->toBe('completed')
        ->and($response->json('run.output.summary'))->toContain('ownership')
        ->and($response->json('run.output.documents.0.readable'))->toBeTrue();

    // En de bevinding landt op het document zelf - daar wachtte het
    // datamodel al op.
    $document = $this->lot->documents()->sole();

    expect($document->ai_checked_at)->not->toBeNull()
        ->and($document->ai_findings['readable'])->toBeTrue();
});

it('maakt de cachesleutel document-precies via checksums', function () {
    LotDocument::factory()->create(['lot_id' => $this->lot->id, 'kind' => DocumentKind::Invoice]);

    $first = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'check_documents', 'lot_id' => $this->lot->id])
        ->json('run');

    // Zelfde documenten: tweede controle is een cachetreffer, kosten nul.
    $second = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'check_documents', 'lot_id' => $this->lot->id])
        ->json('run');

    expect(AiRun::find($second['id'])->cost_cents)->toBe(0)
        ->and(AiRun::find($first['id'])->cache_key)
        ->toBe(AiRun::find($second['id'])->cache_key);

    // Nieuw document, andere checksum: nieuwe analyse.
    LotDocument::factory()->create(['lot_id' => $this->lot->id, 'kind' => DocumentKind::Ownership]);

    $third = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
        ->postJson('/api/ivy/tasks', ['task' => 'check_documents', 'lot_id' => $this->lot->id])
        ->json('run');

    expect(AiRun::find($third['id'])->cache_key)
        ->not->toBe(AiRun::find($first['id'])->cache_key);
});

it('blijft een concepttaak: vreemden krijgen een 404', function () {
    $this->withCookie(DraftAccess::COOKIE, 'verkeerd-token')
        ->postJson('/api/ivy/tasks', ['task' => 'check_documents', 'lot_id' => $this->lot->id])
        ->assertNotFound();
});
