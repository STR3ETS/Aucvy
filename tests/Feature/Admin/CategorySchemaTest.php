<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;

/*
| Attributenschema's per categorie in de backoffice (briefing sectie 7):
| velden, types, opties en volgorde; subcategorieën erven van de hoofdcategorie.
*/

it('laat beheer het schema van een categorie opbouwen en de wizard neemt het over', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $root = Category::factory()->create(['name' => 'Heftrucks', 'slug' => 'heftrucks', 'specifications_schema' => null]);
    $child = Category::factory()->create(['name' => 'Elektrisch', 'slug' => 'heftrucks-elektrisch', 'parent_id' => $root->id, 'specifications_schema' => null]);

    $this->actingAs($staff)->get(route('admin.categories.schema', $root))->assertOk()->assertSee('Kenmerken van Heftrucks');

    $this->actingAs($staff)
        ->put(route('admin.categories.schema.update', $root), ['fields' => [
            ['key' => 'capacity', 'label' => 'Hefvermogen', 'type' => 'integer', 'unit' => 'kg', 'options' => '', 'required' => '1'],
            ['key' => 'fuel', 'label' => 'Aandrijving', 'type' => 'select', 'unit' => '', 'options' => 'Elektrisch, Diesel , LPG'],
            ['key' => 'cabin', 'label' => 'Cabine', 'type' => 'boolean'],
        ]])
        ->assertRedirect(route('admin.categories.schema', $root))
        ->assertSessionHasNoErrors();

    $schema = $root->fresh()->specifications_schema;

    expect($schema)->toHaveCount(3)
        ->and($schema[0])->toEqual(['key' => 'capacity', 'label' => 'Hefvermogen', 'type' => 'integer', 'unit' => 'kg', 'required' => true])
        ->and($schema[1]['options'])->toBe(['Elektrisch', 'Diesel', 'LPG'])
        ->and($schema[2])->toEqual(['key' => 'cabin', 'label' => 'Cabine', 'type' => 'boolean'])
        ->and($child->fresh()->effectiveSpecificationsSchema())->toBe($schema);

    $this->actingAs($staff)->get(route('admin.categories.schema', $child))->assertOk()->assertSee('Nu in gebruik via Heftrucks')->assertSee('Hefvermogen');
});

it('weigert dubbele of foute sleutels en een keuzelijst zonder opties', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $root = Category::factory()->create(['name' => 'Kantoor', 'slug' => 'kantoor']);

    $this->actingAs($staff)
        ->from(route('admin.categories.schema', $root))
        ->put(route('admin.categories.schema.update', $root), ['fields' => [
            ['key' => 'Kleur', 'label' => 'Kleur', 'type' => 'string'],
            ['key' => 'kleur', 'label' => 'Kleur', 'type' => 'string'],
            ['key' => 'merk', 'label' => 'Merk', 'type' => 'select', 'options' => ''],
        ]])
        ->assertSessionHasErrors(['fields.0.key', 'fields.2.options']);

    $this->actingAs($staff)
        ->put(route('admin.categories.schema.update', $root), ['fields' => [
            ['key' => 'kleur', 'label' => 'Kleur', 'type' => 'string'],
            ['key' => 'kleur', 'label' => 'Kleur 2', 'type' => 'string'],
        ]])
        ->assertSessionHasErrors('fields');

    $this->actingAs(User::factory()->create())->get(route('admin.categories.schema', $root))->assertForbidden();
});

it('zet een subcategorie terug op het schema van de hoofdcategorie', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $root = Category::factory()->create(['name' => 'Horeca', 'slug' => 'horeca', 'specifications_schema' => [['key' => 'brand', 'label' => 'Merk', 'type' => 'string']]]);
    $child = Category::factory()->create(['name' => 'Koeling', 'slug' => 'horeca-koeling', 'parent_id' => $root->id, 'specifications_schema' => [['key' => 'volume', 'label' => 'Inhoud', 'type' => 'integer', 'unit' => 'l']]]);

    $this->actingAs($staff)
        ->put(route('admin.categories.schema.update', $child), ['inherit' => '1'])
        ->assertRedirect(route('admin.categories.schema', $child));

    expect($child->fresh()->specifications_schema)->toBeNull()
        ->and($child->fresh()->effectiveSpecificationsSchema()[0]['key'])->toBe('brand');
});
