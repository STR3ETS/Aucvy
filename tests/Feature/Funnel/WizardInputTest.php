<?php

declare(strict_types=1);

use App\Lots\DraftAccess;
use App\Models\Lot;

/*
| Invoer in de wizard (briefing sectie 5, stap 1): dicteren en een label of
| barcode scannen zitten in de browser zelf; de gevonden code landt als
| kenmerk op het concept.
*/

beforeEach(fn () => $this->withCredentials());

it('biedt dicteren en een barcodescan aan in de wizard', function () {
    $this->get(route('sell'))
        ->assertOk()
        ->assertSee('Dicteren')
        ->assertSee('Label of barcode scannen')
        ->assertSee('scanBarcode(', escape: false)
        ->assertSee("dictate('description')", escape: false);
});

it('bewaart een gescande barcode als kenmerk van de verkoper', function () {
    $this->postJson('/api/drafts')->assertCreated();
    $lot = Lot::sole();

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->patchJson("/api/drafts/{$lot->id}", ['specifications' => ['ean' => '8712345678906']])
        ->assertOk()
        ->assertJsonPath('draft.specifications.ean.value', '8712345678906')
        ->assertJsonPath('draft.specifications.ean.source', 'seller');
});
