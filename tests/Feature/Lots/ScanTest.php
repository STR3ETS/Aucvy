<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use App\Lots\Documents\DocumentVisibility;
use App\Lots\Provenance;
use App\Lots\Scan\Gtin;
use App\Models\AiRun;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\ProductLookup;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
| Camera, OCR en productherkenning (briefing nieuwe functies): een
| streepjescode is pas een productcode als het controlegetal klopt, de
| opzoeking gaat eerst langs de eigen cache, en Ivy leest de typeplaat.
| Elk veld krijgt een bron; het serienummer blijft van de kavelpagina.
*/

beforeEach(fn () => Storage::fake('local'));

it('keurt alleen productcodes met een kloppend controlegetal goed', function () {
    expect(Gtin::isValid('8712345678906'))->toBeTrue()
        ->and(Gtin::isValid('8712345678905'))->toBeFalse()
        ->and(Gtin::isValid('96385074'))->toBeTrue()
        ->and(Gtin::isValid('036000291452'))->toBeTrue()
        ->and(Gtin::normalise('87 1234 5678 906'))->toBe('8712345678906')
        ->and(Gtin::normalise('12345'))->toBeNull();
});

it('zet een gescande code op het concept en zoekt het product op via de cache en de EAN-dienst', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'brand' => null, 'model' => null]);

    $this->actingAs($seller)->postJson('/api/drafts/'.$lot->id.'/scan/gtin', ['code' => '8712345678905'])->assertUnprocessable();

    // Zonder sleutel: code opgeslagen, opzoeking volgt.
    $this->actingAs($seller)->postJson('/api/drafts/'.$lot->id.'/scan/gtin', ['code' => '8712345678906'])
        ->assertOk()->assertJsonPath('product.status', 'no_provider');
    expect($lot->fresh()->gtin)->toBe('8712345678906')
        ->and($lot->fresh()->specifications['ean']['value'])->toBe('8712345678906');

    // Met sleutel: de dienst wordt één keer gevraagd, daarna de cache.
    config(['services.go_upc.key' => 'sleutel']);
    Http::fake(['go-upc.com/*' => Http::response(['product' => ['name' => 'Hyster H2.5FT heftruck', 'brand' => 'Hyster', 'description' => 'Diesel heftruck', 'imageUrl' => 'https://img.example/h.jpg']])]);

    $this->actingAs($seller)->postJson('/api/drafts/'.$lot->id.'/scan/gtin', ['code' => '8712345678906'])
        ->assertOk()->assertJsonPath('product.status', 'found')->assertJsonPath('product.source', 'Go-UPC');

    $lot->refresh();
    expect($lot->brand)->toBe('Hyster')
        ->and($lot->model)->toBe('H2.5FT heftruck')
        ->and($lot->field_sources['brand']['source'])->toBe('ean')
        ->and($lot->field_sources['brand']['needs_confirmation'])->toBeTrue()
        ->and($lot->draft_meta['ean_product']['licence_checked'])->toBeFalse()
        ->and(ProductLookup::query()->where('gtin', '8712345678906')->value('status'))->toBe('found');

    $other = Lot::factory()->create(['seller_id' => $seller->id]);
    $this->actingAs($seller)->postJson('/api/drafts/'.$other->id.'/scan/gtin', ['code' => '8712345678906'])->assertOk();
    Http::assertSentCount(1);

    // Wat de verkoper zelf typte, blijft staan.
    $own = Lot::factory()->create(['seller_id' => $seller->id, 'brand' => 'Toyota']);
    Provenance::markSeller($own, ['brand']);
    $this->actingAs($seller)->postJson('/api/drafts/'.$own->id.'/scan/gtin', ['code' => '8712345678906'])->assertOk();
    expect($own->fresh()->brand)->toBe('Toyota');
});

it('laat Ivy de typeplaat lezen als intern document, met bron per veld en een verborgen serienummer', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'brand' => null, 'model' => null, 'year' => null]);

    $response = $this->actingAs($seller)->post('/api/drafts/'.$lot->id.'/scan/nameplate', ['file' => UploadedFile::fake()->image('typeplaat.jpg', 800, 600)])
        ->assertCreated()->assertJsonPath('document.kind', 'nameplate');

    $document = LotDocument::query()->where('lot_id', $lot->id)->firstOrFail();
    expect($document->visibility)->toBe(DocumentVisibility::AFTER_SALE)
        ->and($lot->fresh()->media()->count())->toBe(0);

    $run = AiRun::query()->findOrFail($response->json('run.id'));
    expect($run->status)->toBe(AiRunStatus::Completed)->and($run->task)->toBe('read_nameplate');

    $this->actingAs($seller)->getJson('/api/ivy/tasks/'.$run->id)->assertOk()->assertJsonPath('run.status', 'completed');

    $lot->refresh();
    expect($lot->brand)->toBe('Hyster')
        ->and($lot->model)->toBe('H2.5FT')
        ->and($lot->year)->toBe(2018)
        ->and($lot->specifications['vermogen']['value'])->toBe('45 kW')
        ->and($lot->serial_number)->toStartWith('SN-')
        ->and($lot->field_sources['brand']['source'])->toBe('nameplate')
        ->and($lot->field_sources['year']['needs_confirmation'])->toBeTrue();

    $this->actingAs($seller)->getJson('/api/drafts/'.$lot->id)->assertOk()->assertJsonPath('fields.brand.label', 'Ivy, van de typeplaat (95% zeker)');
});
