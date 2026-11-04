<?php

declare(strict_types=1);

use App\Enums\DocumentKind;
use App\Enums\OrderStatus;
use App\Lots\Documents\DocumentVisibility;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotDocument;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\TransitionOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/*
| Documenten-oogje (briefing nieuwe functies): elk document krijgt een
| niveau (openbaar, na gunning, privé), bekijken gaat na een controle op de
| server via een getekende URL van vijf minuten, en een privé document
| geeft 403 voor de koper, ook met een oude getekende URL.
*/

beforeEach(fn () => Storage::fake('local'));

function documentOn(Lot $lot, DocumentKind $kind, ?string $visibility = null): LotDocument
{
    Storage::disk('local')->put("documents/{$lot->public_id}/{$kind->value}.pdf", '%PDF-1.4 test');

    return LotDocument::factory()->create([
        'lot_id' => $lot->id,
        'kind' => $kind,
        'visibility' => $visibility ?? DocumentVisibility::defaultFor($kind),
        'path' => "documents/{$lot->public_id}/{$kind->value}.pdf",
    ]);
}

it('geeft elke soort document het standaardniveau uit de briefing', function () {
    expect(DocumentVisibility::defaultFor(DocumentKind::Invoice))->toBe('private')
        ->and(DocumentVisibility::defaultFor(DocumentKind::Ownership))->toBe('private')
        ->and(DocumentVisibility::defaultFor(DocumentKind::Warranty))->toBe('after_sale')
        ->and(DocumentVisibility::defaultFor(DocumentKind::Nameplate))->toBe('after_sale')
        ->and(DocumentVisibility::defaultFor(DocumentKind::Manual))->toBe('public')
        ->and(DocumentVisibility::defaultFor(DocumentKind::Inspection))->toBe('public');
});

it('opent een openbaar document voor iedereen en houdt een privé document dicht', function () {
    $lot = Lot::factory()->live()->create();
    $manual = documentOn($lot, DocumentKind::Manual);
    $invoice = documentOn($lot, DocumentKind::Invoice);

    $this->get(route('lots.show', $lot))->assertOk()->assertSee('Bekijken')->assertSee('Privé');

    $this->get(route('documents.view', $manual))->assertOk()->assertSee('/bestand?', escape: false)->assertDontSee('documents/'.$lot->public_id, escape: false);
    $this->get(route('documents.view', $invoice))->assertOk()->assertSee('Privé document')->assertDontSee('/bestand?');

    expect($manual->fresh()->view_count)->toBe(1)
        ->and($invoice->fresh()->view_count)->toBe(0)
        ->and(LotActivity::query()->where('source', 'document')->where('type', 'viewed')->count())->toBe(1);
});

it('geeft het bestand alleen via een geldige getekende URL en met de juiste rechten', function () {
    $lot = Lot::factory()->live()->create();
    $manual = documentOn($lot, DocumentKind::Manual);
    $invoice = documentOn($lot, DocumentKind::Invoice);
    $buyer = User::factory()->verified()->create();

    $this->get(route('documents.file', $manual))->assertForbidden();

    $signed = URL::temporarySignedRoute('documents.file', now()->addMinutes(5), ['document' => $manual->id]);
    $this->get($signed)->assertOk()->assertHeader('Content-Security-Policy');

    // Een oude of vervalste link werkt niet; een link naar een privé document ook niet voor een koper.
    $this->travel(6)->minutes();
    $this->get($signed)->assertForbidden();
    $this->travelBack();

    $private = URL::temporarySignedRoute('documents.file', now()->addMinutes(5), ['document' => $invoice->id]);
    $this->actingAs($buyer)->get($private)->assertForbidden();
    $this->actingAs($lot->seller)->get($private)->assertOk();
});

it('geeft de winnaar toegang tot documenten na gunning, en logt inzage door beheer op privé', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    $lot = $order->lot->fresh();
    $warranty = documentOn($lot, DocumentKind::Warranty);
    $invoice = documentOn($lot, DocumentKind::Invoice);
    $stranger = User::factory()->verified()->create();

    $this->actingAs($order->buyer)->get(route('documents.view', $warranty))->assertOk()->assertSee('/bestand?', escape: false);
    $this->actingAs($stranger)->get(route('documents.view', $warranty))->assertOk()->assertSee('Te zien na gunning');
    $this->actingAs($order->buyer)->get(route('documents.view', $invoice))->assertOk()->assertSee('Privé document');

    $staff = User::factory()->create(['is_staff' => true]);
    $this->actingAs($staff)->get(route('documents.view', $invoice))->assertOk()->assertSee('/bestand?', escape: false);

    expect(AuditLog::query()->where('action', 'document.viewed')->where('actor_id', $staff->id)->exists())->toBeTrue();
});

it('laat de verkoper het niveau per document kiezen, met spoor', function () {
    $lot = Lot::factory()->live()->create(['seller_id' => User::factory()->verified()->business()->create()->id]);
    $invoice = documentOn($lot, DocumentKind::Invoice);

    $this->actingAs($lot->seller)->get(route('dashboard.lots.show', [$lot, 'tab' => 'documenten']))->assertOk()->assertSee('Zichtbaar voor');

    $this->actingAs($lot->seller)
        ->patch(route('dashboard.lots.documents.visibility', [$lot, $invoice]), ['visibility' => 'public'])
        ->assertRedirect();

    expect($invoice->fresh()->visibility)->toBe('public')
        ->and(AuditLog::query()->where('action', 'document.visibility_changed')->exists())->toBeTrue()
        ->and(LotActivity::query()->where('source', 'document')->where('type', 'visibility')->exists())->toBeTrue();

    $this->actingAs(User::factory()->verified()->business()->create())
        ->patch(route('dashboard.lots.documents.visibility', [$lot, $invoice]), ['visibility' => 'private'])
        ->assertNotFound();
});

it('geeft een nieuw document bij het uploaden het niveau van zijn soort, ook een typeplaatfoto', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id]);

    $this->actingAs($seller)
        ->postJson("/api/drafts/{$lot->id}/documents", ['file' => UploadedFile::fake()->image('typeplaat.jpg'), 'kind' => 'nameplate'])
        ->assertCreated();

    expect($lot->documents()->sole()->visibility)->toBe('after_sale');
});
