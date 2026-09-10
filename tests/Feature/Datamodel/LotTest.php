<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Enums\ValueConfidence;
use App\Models\Lot;

it('zet het kavelnummer in de slug zodat een titelwijziging de url niet breekt', function () {
    $lot = Lot::factory()->create();

    expect($lot->slug)->toEndWith('-'.$lot->public_id);
});

describe('waarde-indicatie', function () {
    it('toont niets als de range onvolledig is', function () {
        // De drie velden horen altijd samen. Een losse ondergrens leest als
        // een belofte (hfst. 5).
        $lot = Lot::factory()->create([
            'value_min_cents' => 1_500_000,
            'value_max_cents' => null,
            'value_confidence' => ValueConfidence::High,
        ]);

        expect($lot->hasValueEstimate())->toBeFalse()
            ->and($lot->mayShowValueComparison())->toBeFalse();
    });

    it('verbergt het percentage-label bij lage zekerheid', function () {
        // Bij lage zekerheid is de indicatie niet scherp genoeg om als
        // koopargument te dienen (hfst. 13.2).
        $lot = Lot::factory()
            ->withValueEstimate(confidence: ValueConfidence::Low)
            ->create();

        expect($lot->hasValueEstimate())->toBeTrue()
            ->and($lot->mayShowValueComparison())->toBeFalse();
    });

    it('toont het percentage-label vanaf redelijke zekerheid', function () {
        $lot = Lot::factory()
            ->withValueEstimate(confidence: ValueConfidence::Medium)
            ->create();

        expect($lot->mayShowValueComparison())->toBeTrue();
    });
});

describe('zichtbaarheid', function () {
    it('houdt concepten buiten de publieke lijst', function () {
        Lot::factory()->create(['status' => LotStatus::Draft]);
        Lot::factory()->live()->create();

        expect(Lot::public()->count())->toBe(1);
    });

    it('rekent de slotfase mee als lopend', function () {
        // `Closing` is juist het moment waarop het hardst geboden wordt.
        Lot::factory()->create(['status' => LotStatus::Closing]);
        Lot::factory()->create(['status' => LotStatus::Sold]);

        expect(Lot::live()->count())->toBe(1);
    });
});

it('bewaart een verwijderde kavel zodat de biedhistorie reconstrueerbaar blijft', function () {
    $lot = Lot::factory()->live()->create();

    $lot->delete();

    expect(Lot::count())->toBe(0)
        ->and(Lot::withTrashed()->count())->toBe(1);
});

it('houdt de specificaties bereikbaar ondanks de hernoemde kolom', function () {
    // De briefing noemt deze kolom `attributes`; die naam botst met Eloquents
    // eigen $attributes. Zie de migratie voor de toelichting.
    $lot = Lot::factory()->create([
        'specifications' => [
            'operating_hours' => ['value' => 4200, 'source' => 'seller'],
        ],
    ]);

    expect($lot->fresh()->specifications)
        ->toBe(['operating_hours' => ['value' => 4200, 'source' => 'seller']]);
});
