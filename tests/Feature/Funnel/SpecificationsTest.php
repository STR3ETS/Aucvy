<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Lots\DraftAccess;
use App\Models\Category;
use App\Models\Lot;

/*
| Kenmerken in de wizard (briefing sectie 5, stap 4): de velden uit het
| categorieschema plus vrije kenmerken, met herkomst per kenmerk. Wat Ivy
| zette en onveranderd blijft, houdt zijn bron.
*/

beforeEach(fn () => $this->withCredentials());

it('slaat kenmerken op met bron verkoper en bewaart de bron van Ivy als de waarde gelijk blijft', function () {
    $this->postJson('/api/drafts')->assertCreated();
    $lot = Lot::sole();
    $lot->forceFill(['specifications' => [
        'merk' => ['value' => 'Linde', 'source' => 'ai', 'confidence' => 0.8],
        'objecttype' => ['value' => 'Heftruck', 'source' => 'ai', 'confidence' => 0.8],
    ]])->save();

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->patchJson("/api/drafts/{$lot->id}", ['specifications' => [
            'merk' => 'Linde',
            'operating_hours' => 4200,
            'track_type' => 'Banden',
            'objecttype' => '',
        ]])
        ->assertOk()
        ->assertJsonPath('draft.specifications.merk.source', 'ai')
        ->assertJsonPath('draft.specifications.operating_hours.source', 'seller')
        ->assertJsonPath('draft.specifications.operating_hours.value', 4200)
        ->assertJsonMissingPath('draft.specifications.objecttype');

    expect($lot->fresh()->specifications)->toHaveKeys(['merk', 'operating_hours', 'track_type'])
        ->and($lot->fresh()->specifications['track_type']['source'])->toBe('seller');
});

it('geeft de wizard de schema\'s per categorie mee', function () {
    $parent = Category::factory()->create(['specifications_schema' => [
        ['key' => 'operating_hours', 'label' => 'Urenstand', 'type' => 'integer', 'unit' => 'uur'],
    ]]);
    $child = Category::factory()->create(['parent_id' => $parent->id, 'specifications_schema' => null]);

    $this->get('/verkopen')
        ->assertOk()
        ->assertSee('Urenstand')
        // De subcategorie zonder eigen schema krijgt dat van de ouder.
        ->assertViewHas('schemas', fn (array $schemas): bool => ($schemas[$child->id][0]['key'] ?? null) === 'operating_hours');
});

it('toont kenmerken op de kavelpagina, ook de vrije van Ivy en platte waarden', function () {
    $category = Category::factory()->create(['specifications_schema' => [
        ['key' => 'operating_hours', 'label' => 'Urenstand', 'type' => 'integer', 'unit' => 'uur'],
    ]]);
    $lot = Lot::factory()->create([
        'status' => LotStatus::Live,
        'category_id' => $category->id,
        'specifications' => [
            'operating_hours' => ['value' => 4200, 'source' => 'seller'],
            'merk' => ['value' => 'Linde', 'source' => 'ai'],
            'kleur' => 'rood',
        ],
    ]);

    $this->get(route('lots.show', $lot))
        ->assertOk()
        ->assertSee('Urenstand')
        ->assertSee('4200 uur')
        ->assertSee('Merk')
        ->assertSee('door Ivy')
        ->assertSee('Kleur')
        ->assertSee('rood');
});
