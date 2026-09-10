<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * De taxonomie uit preview v4: acht hoofdcategorieën met subcategorieën.
 *
 * Deze stond in hfst. 18 nog als open beslissing, maar v4 heeft hem al gemaakt.
 * Wat nog wél open staat zijn de specificatieschema's per subcategorie - en
 * daarvoor geldt de regel uit hfst. 5: begin met drie categorieën volledig
 * uitgewerkt en de rest generiek. Een half schema is erger dan geen schema,
 * want het suggereert volledigheid die er niet is.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->taxonomy() as $position => $root) {
            $parent = $this->upsert(
                name: $root['name'],
                position: $position,
                schema: $root['schema'] ?? $this->genericSchema(),
            );

            foreach ($root['children'] as $childPosition => $child) {
                $name = is_array($child) ? $child['name'] : $child;
                $schema = is_array($child) ? ($child['schema'] ?? null) : null;

                $this->upsert(
                    name: $name,
                    position: $childPosition,
                    // Geen eigen schema? Dan valt de subcategorie via
                    // Category::effectiveSpecificationsSchema() terug op de
                    // hoofdcategorie.
                    schema: $schema,
                    parent: $parent,
                );
            }
        }
    }

    private function upsert(string $name, int $position, ?array $schema, ?Category $parent = null): Category
    {
        $slug = Str::slug($parent !== null ? $parent->name.' '.$name : $name);

        return Category::updateOrCreate(
            ['slug' => $slug],
            [
                'parent_id' => $parent?->id,
                'name' => $name,
                'position' => $position,
                'specifications_schema' => $schema,
            ],
        );
    }

    /**
     * De acht hoofdcategorieën met hun subcategorieën.
     *
     * @return array<int, array{name: string, schema?: array, children: array<int, string|array>}>
     */
    private function taxonomy(): array
    {
        return [
            [
                'name' => 'Machines & techniek',
                'children' => [
                    [
                        'name' => 'Graafmachines',
                        // Volledig uitgewerkt (1 van 3).
                        'schema' => [
                            $this->field('operating_hours', 'Urenstand', 'integer', unit: 'uur', required: true),
                            $this->field('build_year', 'Bouwjaar', 'integer', required: true),
                            $this->field('weight_kg', 'Bedrijfsgewicht', 'integer', unit: 'kg'),
                            $this->field('power_kw', 'Vermogen', 'integer', unit: 'kW'),
                            $this->field('track_type', 'Onderstel', 'select', options: ['Rups', 'Banden']),
                            $this->field('has_ce_marking', 'CE-markering aanwezig', 'boolean'),
                        ],
                    ],
                    'Heftrucks',
                    'Metaalbewerking',
                    'Houtbewerking',
                    'Compressoren & pompen',
                ],
            ],
            [
                'name' => 'Horeca & keuken',
                'children' => [
                    'Koeling & vriescellen',
                    [
                        'name' => 'Complete keukens',
                        // Volledig uitgewerkt (2 van 3).
                        'schema' => [
                            $this->field('kitchen_brand', 'Merk', 'string', required: true),
                            $this->field('width_cm', 'Breedte', 'integer', unit: 'cm', required: true),
                            $this->field('depth_cm', 'Diepte', 'integer', unit: 'cm'),
                            $this->field('worktop_material', 'Werkblad', 'select', options: [
                                'Composiet', 'Graniet', 'Hout', 'Kunststof', 'RVS',
                            ]),
                            $this->field('appliance_brand', 'Merk apparatuur', 'string'),
                            $this->field('includes_appliances', 'Inclusief apparatuur', 'boolean'),
                        ],
                    ],
                    'Kookapparatuur',
                    'Horecameubilair',
                ],
            ],
            [
                'name' => 'Kantoor & inventaris',
                'children' => [
                    'Bureaustoelen',
                    'Bureaus & kasten',
                    'IT & elektronica',
                    'Complete inventaris',
                ],
            ],
            [
                'name' => 'Voertuigen & transport',
                'children' => [
                    [
                        'name' => 'Bestelwagens',
                        // Volledig uitgewerkt (3 van 3).
                        'schema' => [
                            $this->field('licence_plate', 'Kenteken', 'string', required: true),
                            $this->field('mileage_km', 'Kilometerstand', 'integer', unit: 'km', required: true),
                            $this->field('build_year', 'Bouwjaar', 'integer', required: true),
                            $this->field('fuel', 'Brandstof', 'select', options: [
                                'Diesel', 'Benzine', 'Elektrisch', 'Hybride', 'LPG',
                            ], required: true),
                            $this->field('transmission', 'Transmissie', 'select', options: ['Handgeschakeld', 'Automaat']),
                            $this->field('apk_until', 'APK geldig tot', 'date'),
                            $this->field('has_tow_bar', 'Trekhaak', 'boolean'),
                        ],
                    ],
                    "Personenauto's",
                    'Aanhangers & opleggers',
                ],
            ],
            [
                'name' => 'Bouw & materieel',
                'children' => ['Steigers', 'Gereedschap', 'Grondverzet'],
            ],
            [
                'name' => 'Winkel & voorraad',
                'children' => ['Kleding & mode', 'Restpartijen', 'Displays & inrichting'],
            ],
            [
                'name' => 'Boten & recreatie',
                'children' => ['Zeil- & motorjachten', 'Sloepen', 'Campers & caravans'],
            ],
            [
                'name' => 'Elektronica',
                'children' => ["Camera's & optiek", 'Computers', 'Audio & video'],
            ],
        ];
    }

    /**
     * Wat elke categorie minimaal vraagt zolang er nog geen eigen schema is.
     *
     * @return array<int, array<string, mixed>>
     */
    private function genericSchema(): array
    {
        return [
            $this->field('brand', 'Merk', 'string'),
            $this->field('model', 'Type of model', 'string'),
            $this->field('build_year', 'Bouwjaar', 'integer'),
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private function field(
        string $key,
        string $label,
        string $type,
        ?string $unit = null,
        ?array $options = null,
        bool $required = false,
    ): array {
        return array_filter([
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'unit' => $unit,
            'options' => $options,
            'required' => $required,
        ], fn (mixed $value): bool => $value !== null && $value !== false);
    }
}
