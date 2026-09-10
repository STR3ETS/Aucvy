<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Lots\DraftAccess;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
| getJson/patchJson sturen cookies alleen mee met withCredentials() - zonder
| deze regel test je per ongeluk altijd de "andere browser"-route.
*/
beforeEach(function () {
    $this->withCredentials();
});

describe('een concept zonder account', function () {
    it('ontstaat met een cookie-token', function () {
        // Stap 1 t/m 4 werken zonder account (hfst. 13.4).
        $response = $this->postJson('/api/drafts')->assertCreated();

        $lot = Lot::sole();

        expect($lot->status)->toBe(LotStatus::Draft)
            ->and($lot->seller_id)->toBeNull()
            ->and($lot->draft_token)->not->toBeNull()
            ->and($response->json('draft.public_id'))->toBe($lot->public_id);

        $response->assertCookie(DraftAccess::COOKIE);
    });

    it('is alleen bereikbaar met het juiste token', function () {
        $this->postJson('/api/drafts')->assertCreated();
        $lot = Lot::sole();

        // Zelfde browser: mag erbij.
        $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
            ->getJson("/api/drafts/{$lot->id}")
            ->assertOk();

        // Andere browser: 404, geen 403 - een 403 bevestigt dat het bestaat.
        $this->withCookie(DraftAccess::COOKIE, 'verkeerd-token')
            ->getJson("/api/drafts/{$lot->id}")
            ->assertNotFound();

        $this->getJson("/api/drafts/{$lot->id}")->assertNotFound();
    });

    it('slaat wijzigingen op via autosave', function () {
        $this->postJson('/api/drafts')->assertCreated();
        $lot = Lot::sole();

        $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
            ->patchJson("/api/drafts/{$lot->id}", [
                'title' => 'Complete horecakeuken',
                'sale_type' => 'auction',
                'start_price_cents' => 80_000,
            ])
            ->assertOk()
            ->assertJsonPath('draft.title', 'Complete horecakeuken')
            ->assertJsonPath('draft.meta.start_price_cents', 80_000);

        // Prijzen wachten in draft_meta tot de publicatie er een veiling van
        // maakt - er bestaat nog geen veilingrij.
        expect($lot->fresh()->draft_meta['sale_type'])->toBe('auction')
            ->and($lot->fresh()->auction()->exists())->toBeFalse();
    });

    it('is nooit publiek zichtbaar, ook niet met een geraden url', function () {
        $this->postJson('/api/drafts');
        $lot = Lot::sole();
        $lot->update(['slug' => 'geraden-slug-'.$lot->public_id]);

        $this->get('/kavel/geraden-slug-'.$lot->public_id)->assertNotFound();
    });
});

describe('overzetten bij inloggen', function () {
    it('zet het concept op naam van wie inlogt', function () {
        // Wie in de funnel begon en pas bij het publiceren inlogt, raakt zijn
        // werk niet kwijt.
        $this->postJson('/api/drafts')->assertCreated();
        $lot = Lot::sole();

        $user = User::factory()->create(['password' => 'wachtwoord']);

        $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
            ->post('/inloggen', ['email' => $user->email, 'password' => 'wachtwoord'])
            ->assertRedirect();

        expect($lot->fresh()->seller_id)->toBe($user->id);
    });

    it('kaapt geen concept dat al van iemand anders is', function () {
        $owner = User::factory()->create();
        $lot = Lot::factory()->anonymousDraft()->create(['seller_id' => $owner->id]);

        $attacker = User::factory()->create(['password' => 'wachtwoord']);

        $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
            ->post('/inloggen', ['email' => $attacker->email, 'password' => 'wachtwoord']);

        expect($lot->fresh()->seller_id)->toBe($owner->id);
    });
});

describe('uploads', function () {
    beforeEach(function () {
        Storage::fake('public');
        Storage::fake('local');

        $this->postJson('/api/drafts');
        $this->lot = Lot::sole();
    });

    it('slaat een foto op met checksum en afmetingen', function () {
        $response = $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
            ->post("/api/drafts/{$this->lot->id}/media", [
                'file' => UploadedFile::fake()->image('keuken.jpg', 1600, 1200),
            ])
            ->assertCreated();

        $media = $this->lot->media()->sole();

        // Checksum vanaf dag één: dat is later de dubbele-fotodetectie (hfst. 5).
        expect($media->checksum)->toHaveLength(64)
            ->and($media->width)->toBe(1600)
            ->and($response->json('completeness.score'))->toBeGreaterThan(0);
    });

    it('weigert een pdf die zich voordoet als foto', function () {
        // Het MIME-type komt uit de inhoud, niet uit de bestandsnaam (hfst. 15).
        $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
            ->post("/api/drafts/{$this->lot->id}/media", [
                'file' => UploadedFile::fake()->create('nep-foto.jpg', 200, 'application/pdf'),
            ])
            ->assertStatus(422);
    });

    it('zet documenten op de private disk', function () {
        $this->withCookie(DraftAccess::COOKIE, $this->lot->draft_token)
            ->post("/api/drafts/{$this->lot->id}/documents", [
                'file' => UploadedFile::fake()->create('factuur.pdf', 300, 'application/pdf'),
                'kind' => 'invoice',
            ])
            ->assertCreated();

        $document = $this->lot->documents()->sole();

        // Privé, nooit publiek: een eigendomsbewijs hoort niet achter een
        // raadbare URL (hfst. 15).
        Storage::disk('local')->assertExists($document->path);
        Storage::disk('public')->assertMissing($document->path);
    });

    it('weigert uploads van een ander', function () {
        $this->withCookie(DraftAccess::COOKIE, 'verkeerd-token')
            ->post("/api/drafts/{$this->lot->id}/media", [
                'file' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertNotFound();
    });
});

describe('de funnelpagina', function () {
    it('rendert zonder account', function () {
        $this->get('/verkopen')
            ->assertOk()
            ->assertSee('Kavel plaatsen')
            ->assertSee('Geen account nodig');
    });

    it('laadt een bestaand concept van deze browser', function () {
        $this->postJson('/api/drafts');
        $lot = Lot::sole();
        $lot->update(['title' => 'Half afgemaakte keuken']);

        $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
            ->get('/verkopen')
            ->assertOk()
            ->assertSee('Half afgemaakte keuken');
    });
});
