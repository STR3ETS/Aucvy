<?php

declare(strict_types=1);

use App\Models\Category;
use Database\Seeders\CategorySeeder;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
});

it('heeft de acht hoofdcategorieen uit preview v4', function () {
    $roots = Category::roots()->pluck('name')->all();

    expect($roots)->toBe([
        'Machines & techniek',
        'Horeca & keuken',
        'Kantoor & inventaris',
        'Voertuigen & transport',
        'Bouw & materieel',
        'Winkel & voorraad',
        'Boten & recreatie',
        'Elektronica',
    ]);
});

it('hangt elke subcategorie onder een hoofdcategorie', function () {
    $orphans = Category::whereNotNull('parent_id')
        ->whereDoesntHave('parent')
        ->count();

    // 5 + 4 + 4 + 3 + 3 + 3 + 3 + 3 subcategorieën.
    expect($orphans)->toBe(0)
        ->and(Category::whereNotNull('parent_id')->count())->toBe(28);
});

it('werkt drie subcategorieen volledig uit en laat de rest terugvallen', function () {
    // De regel uit hfst. 5: liever drie complete schema's en de rest generiek,
    // dan overal een half schema dat volledigheid suggereert.
    $withOwnSchema = Category::whereNotNull('parent_id')
        ->whereNotNull('specifications_schema')
        ->pluck('name')
        ->sort()
        ->values()
        ->all();

    expect($withOwnSchema)->toBe(['Bestelwagens', 'Complete keukens', 'Graafmachines']);
});

it('laat een subcategorie zonder eigen schema terugvallen op de hoofdcategorie', function () {
    $heftrucks = Category::where('slug', 'machines-techniek-heftrucks')->firstOrFail();

    expect($heftrucks->specifications_schema)->toBeNull()
        ->and($heftrucks->effectiveSpecificationsSchema())->not->toBeEmpty();
});

it('vraagt bij graafmachines om urenstand en bouwjaar', function () {
    $schema = Category::where('slug', 'machines-techniek-graafmachines')
        ->firstOrFail()
        ->effectiveSpecificationsSchema();

    expect(array_column($schema, 'key'))
        ->toContain('operating_hours')
        ->toContain('build_year');
});

it('vraagt bij bestelwagens om kenteken en kilometerstand', function () {
    $schema = Category::where('slug', 'voertuigen-transport-bestelwagens')
        ->firstOrFail()
        ->effectiveSpecificationsSchema();

    expect(array_column($schema, 'key'))
        ->toContain('licence_plate')
        ->toContain('mileage_km');
});

it('kan opnieuw draaien zonder dubbele categorieen', function () {
    // De seeder hoort ook op productie te kunnen draaien: categorieën zijn
    // referentiedata, geen testdata.
    $before = Category::count();

    $this->seed(CategorySeeder::class);

    expect(Category::count())->toBe($before);
});
