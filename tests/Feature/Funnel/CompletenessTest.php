<?php

declare(strict_types=1);

use App\Enums\DocumentKind;
use App\Enums\LotCondition;
use App\Enums\MediaType;
use App\Lots\CompletenessScore;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;

/*
| De score wordt in PHP geteld uit tellbare feiten - niet door de LLM. Een
| score die per aanroep varieert is geen score (hfst. 13.4). Deze tests pinnen
| de vier drempels en de determinismegarantie vast.
*/

function bareDraft(): Lot
{
    return Lot::factory()->anonymousDraft()->create([
        'title' => null,
        'description' => null,
        'category_id' => null,
        'condition' => null,
        'brand' => null,
        'model' => null,
        'pickup_only' => false,
        'viewing_available' => false,
    ]);
}

it('begint op nul met de starttekst', function () {
    $result = app(CompletenessScore::class)->for(bareDraft());

    expect($result['score'])->toBe(0)
        ->and($result['label'])->toBe('start')
        ->and($result['missing'])->toContain('title', 'photos', 'documents');
});

it('geeft dezelfde score bij dezelfde invoer', function () {
    $lot = bareDraft();
    $lot->update(['title' => 'Complete horecakeuken met toebehoren']);

    $first = app(CompletenessScore::class)->for($lot->fresh());
    $second = app(CompletenessScore::class)->for($lot->fresh());

    expect($first['score'])->toBe($second['score']);
});

it('telt fotos zwaarder naarmate er meer zijn', function () {
    $lot = bareDraft();

    $score = fn (): int => app(CompletenessScore::class)->for($lot->fresh())['score'];
    $none = $score();

    LotMedia::factory()->create(['lot_id' => $lot->id]);
    $one = $score();

    LotMedia::factory()->count(3)->create(['lot_id' => $lot->id]);
    $four = $score();

    expect($one)->toBeGreaterThan($none)
        ->and($four)->toBeGreaterThan($one);
});

it('bereikt compleet met alles erop en eraan', function () {
    $lot = bareDraft();
    $lot->update([
        'title' => 'Complete horecakeuken met Siemens-apparatuur',
        'description' => str_repeat('Een eerlijke en volledige omschrijving. ', 5),
        'category_id' => Category::factory()->create()->id,
        'condition' => LotCondition::Good,
        'brand' => 'Siemens',
        'pickup_only' => true,
        'viewing_available' => true,
    ]);

    LotMedia::factory()->count(8)->create(['lot_id' => $lot->id]);
    LotMedia::factory()->create(['lot_id' => $lot->id, 'type' => MediaType::Video]);
    LotDocument::factory()->create(['lot_id' => $lot->id, 'kind' => DocumentKind::Invoice]);
    LotDocument::factory()->create(['lot_id' => $lot->id, 'kind' => DocumentKind::Ownership]);

    $result = app(CompletenessScore::class)->for($lot->fresh());

    expect($result['score'])->toBeGreaterThanOrEqual(90)
        ->and($result['label'])->toBe('complete')
        ->and($result['missing'])->toBe([]);
});

it('hanteert de vier drempels', function () {
    $calculator = new ReflectionClass(CompletenessScore::class);
    $label = $calculator->getMethod('label');

    $instance = app(CompletenessScore::class);

    expect($label->invoke($instance, 0))->toBe('start')
        ->and($label->invoke($instance, 40))->toBe('good')
        ->and($label->invoke($instance, 70))->toBe('strong')
        ->and($label->invoke($instance, 90))->toBe('complete');
});
