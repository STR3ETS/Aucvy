<?php

declare(strict_types=1);

namespace App\Lots\Import;

use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Lots\PublicId;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotImport;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Van csv-rijen naar concepten (briefing sectie 4). Elke rij wordt een
 * concept in de la: de verkoper loopt de wizard nog langs (foto's,
 * eigendom, preflight) en publiceert zelf. Rijen die niet kloppen komen in
 * het rapport, de rest gaat door.
 *
 * @phpstan-type Row array{line: int, values: array<string, mixed>, errors: list<string>}
 */
final class LotImporter
{
    /** @var array<string, string> Veld naar label, in de volgorde van de mapping. */
    public const FIELDS = [
        'title' => 'Titel',
        'description' => 'Omschrijving',
        'category' => 'Categorie',
        'condition' => 'Conditie',
        'brand' => 'Merk',
        'model' => 'Model',
        'year' => 'Bouwjaar',
        'quantity' => 'Aantal',
        'sale_type' => 'Verkoopvorm',
        'start_price' => 'Startprijs (euro)',
        'reserve_price' => 'Reserveprijs (euro)',
        'buy_now_price' => 'Koop-directprijs (euro)',
        'pickup_only' => 'Alleen ophalen',
        'transport_from' => 'Transport vanaf (euro)',
    ];

    /** @var array<string, list<string>> Kopteksten die we herkennen, per veld. */
    private const GUESSES = [
        'title' => ['titel', 'title', 'naam', 'omschrijving kort', 'kavel'],
        'description' => ['omschrijving', 'beschrijving', 'description', 'tekst'],
        'category' => ['categorie', 'category', 'rubriek'],
        'condition' => ['conditie', 'staat', 'condition'],
        'brand' => ['merk', 'brand', 'fabrikant'],
        'model' => ['model', 'type'],
        'year' => ['bouwjaar', 'jaar', 'year'],
        'quantity' => ['aantal', 'quantity', 'stuks'],
        'sale_type' => ['verkoopvorm', 'vorm', 'sale type', 'sale_type'],
        'start_price' => ['startprijs', 'start', 'startbod', 'start price'],
        'reserve_price' => ['reserveprijs', 'reserve', 'minimumprijs'],
        'buy_now_price' => ['koop direct', 'koopdirect', 'vaste prijs', 'buy now', 'prijs'],
        'pickup_only' => ['ophalen', 'alleen ophalen', 'pickup'],
        'transport_from' => ['transport', 'transport vanaf', 'verzendkosten'],
    ];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    /**
     * Raadt per veld welke kolom erbij hoort, op de koptekst.
     *
     * @param  list<string>  $headers
     * @return array<string, int|null> veld naar kolomindex
     */
    public function guessMapping(array $headers): array
    {
        $normalised = array_map(fn (string $header): string => Str::lower(trim($header)), $headers);
        $mapping = [];

        foreach (self::GUESSES as $field => $candidates) {
            $mapping[$field] = null;

            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $normalised, true);

                if ($index !== false && ! in_array($index, $mapping, true)) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * De rijen genormaliseerd, met fouten per rij; niets wordt opgeslagen.
     *
     * @param  array<string, int|null>  $mapping
     * @return Collection<int, Row>
     */
    public function preview(LotImport $import, array $mapping, ?int $limit = null): Collection
    {
        $rows = $this->reader->read($import->path)['rows'];
        $categories = Category::query()->get(['id', 'name', 'slug']);

        return collect($rows)
            ->when($limit !== null, fn (Collection $all) => $all->take($limit))
            ->map(fn (array $row, int $index): array => $this->normalise($row, $index + 2, $mapping, $categories))
            ->values();
    }

    /**
     * Maakt van elke geldige rij een concept en legt het rapport vast.
     *
     * @param  array<string, int|null>  $mapping
     */
    public function run(LotImport $import, array $mapping, User $seller): LotImport
    {
        $rows = $this->preview($import, $mapping);
        $created = 0;
        $errors = [];

        $alreadyDone = false;

        DB::transaction(function () use ($import, $rows, $seller, &$created, &$errors, &$alreadyDone): void {
            // Twee keer klikken: de tweede wacht op de lock en ziet een afgeronde import.
            if (LotImport::query()->whereKey($import->id)->lockForUpdate()->value('status') === LotImport::DONE) {
                $alreadyDone = true;

                return;
            }

            foreach ($rows as $row) {
                if ($row['errors'] !== []) {
                    $errors[] = ['line' => $row['line'], 'title' => $row['values']['title'] ?? null, 'errors' => $row['errors']];

                    continue;
                }

                $values = $row['values'];

                $lot = Lot::create([
                    'public_id' => PublicId::fresh(),
                    'seller_id' => $seller->id,
                    'company_id' => $seller->company_id,
                    'category_id' => $values['category_id'],
                    'status' => LotStatus::Draft,
                    'title' => $values['title'],
                    'description' => $values['description'],
                    'brand' => $values['brand'],
                    'model' => $values['model'],
                    'year' => $values['year'],
                    'quantity' => $values['quantity'] ?? 1,
                    'condition' => $values['condition'],
                    'pickup_only' => $values['pickup_only'] ?? true,
                    'transport_from_cents' => $values['transport_from_cents'],
                    'draft_token' => Str::random(48),
                    'draft_meta' => array_filter([
                        'sale_type' => $values['sale_type'],
                        'start_price_cents' => $values['start_price_cents'],
                        'reserve_price_cents' => $values['reserve_price_cents'],
                        'buy_now_price_cents' => $values['buy_now_price_cents'],
                    ], fn (mixed $value): bool => $value !== null),
                    'field_sources' => collect(['title', 'description', 'category_id', 'condition', 'brand', 'model', 'year'])
                        ->filter(fn (string $field): bool => ($values[$field] ?? null) !== null)
                        ->mapWithKeys(fn (string $field): array => [$field => ['source' => 'seller', 'confidence' => 1.0, 'needs_confirmation' => false, 'confirmed_at' => now()->toIso8601String(), 'run_id' => null, 'via' => 'import']])
                        ->all() ?: null,
                ]);

                Audit::record('lot.imported', $lot, $seller, after: ['import_id' => $import->id, 'line' => $row['line']]);
                $created++;
            }
        });

        if ($alreadyDone) {
            return $import->fresh();
        }

        $import->forceFill([
            'mapping' => $mapping,
            'status' => LotImport::DONE,
            'rows_total' => $rows->count(),
            'rows_created' => $created,
            'errors' => $errors,
        ])->save();

        return $import;
    }

    /**
     * @param  list<string>  $row
     * @param  array<string, int|null>  $mapping
     * @param  Collection<int, Category>  $categories
     * @return Row
     */
    private function normalise(array $row, int $line, array $mapping, Collection $categories): array
    {
        // Een nul is een waarde ("alleen ophalen: 0"); alleen leeg is leeg.
        $cell = function (string $field) use ($row, $mapping): ?string {
            if (! isset($mapping[$field]) || $mapping[$field] === null || $mapping[$field] === '') {
                return null;
            }

            $value = trim((string) ($row[(int) $mapping[$field]] ?? ''));

            return $value === '' ? null : $value;
        };

        $errors = [];
        $values = [];

        $values['title'] = $cell('title') !== null ? mb_substr($cell('title'), 0, 150) : null;
        if ($values['title'] === null) {
            $errors[] = 'Geen titel.';
        }

        $values['description'] = $cell('description') !== null ? mb_substr($cell('description'), 0, 8000) : null;
        $values['brand'] = $cell('brand') !== null ? mb_substr($cell('brand'), 0, 80) : null;
        $values['model'] = $cell('model') !== null ? mb_substr($cell('model'), 0, 80) : null;

        $values['category_id'] = null;
        if (($category = $cell('category')) !== null) {
            $match = $categories->first(fn (Category $candidate): bool => Str::lower($candidate->name) === Str::lower($category) || $candidate->slug === Str::slug($category));
            $match === null ? $errors[] = 'Onbekende categorie: '.$category.'.' : $values['category_id'] = $match->id;
        }

        $values['condition'] = null;
        if (($condition = $cell('condition')) !== null) {
            $found = $this->condition($condition);
            $found === null ? $errors[] = 'Onbekende conditie: '.$condition.'.' : $values['condition'] = $found;
        }

        $values['year'] = null;
        if (($year = $cell('year')) !== null) {
            is_numeric($year) && (int) $year >= 1900 && (int) $year <= (int) now()->year
                ? $values['year'] = (int) $year
                : $errors[] = 'Bouwjaar klopt niet: '.$year.'.';
        }

        $values['quantity'] = null;
        if (($quantity = $cell('quantity')) !== null) {
            is_numeric($quantity) && (int) $quantity >= 1 ? $values['quantity'] = (int) $quantity : $errors[] = 'Aantal klopt niet: '.$quantity.'.';
        }

        $values['sale_type'] = null;
        if (($saleType = $cell('sale_type')) !== null) {
            $found = $this->saleType($saleType);
            $found === null ? $errors[] = 'Onbekende verkoopvorm: '.$saleType.'.' : $values['sale_type'] = $found;
        }

        foreach (['start_price', 'reserve_price', 'buy_now_price', 'transport_from'] as $money) {
            $values[$money.'_cents'] = null;

            if (($amount = $cell($money)) !== null) {
                $cents = $this->cents($amount);
                $cents === null ? $errors[] = 'Bedrag klopt niet bij '.self::FIELDS[$money].': '.$amount.'.' : $values[$money.'_cents'] = $cents;
            }
        }

        $values['pickup_only'] = null;
        if (($pickup = $cell('pickup_only')) !== null) {
            $values['pickup_only'] = in_array(Str::lower($pickup), ['ja', 'yes', '1', 'true', 'x'], true);
        }

        return ['line' => $line, 'values' => $values, 'errors' => $errors];
    }

    private function condition(string $input): ?string
    {
        $lower = Str::lower($input);

        foreach (LotCondition::cases() as $condition) {
            if ($condition->value === $lower || Str::lower($condition->label()) === $lower) {
                return $condition->value;
            }
        }

        return match ($lower) {
            'nieuw' => LotCondition::New->value,
            'als nieuw', 'zo goed als nieuw' => LotCondition::LikeNew->value,
            'goed', 'gebruikt - goed' => LotCondition::Good->value,
            'gebruikt' => LotCondition::Used->value,
            'defect', 'voor onderdelen' => LotCondition::Defective->value,
            default => null,
        };
    }

    private function saleType(string $input): ?string
    {
        $lower = Str::lower($input);

        return match ($lower) {
            'auction', 'veiling' => SaleType::Auction->value,
            'dutch', 'afslag' => SaleType::Dutch->value,
            'buy_now', 'koop direct', 'koopdirect', 'vaste prijs' => SaleType::BuyNow->value,
            default => null,
        };
    }

    private function cents(string $input): ?int
    {
        $clean = preg_replace('/[^0-9,.\-]/', '', $input) ?? '';

        // "1.250,50" (NL) of "1,250.50" (EN) of "1250.50": de laatste scheider is het decimaalteken.
        if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d{1,2})?$/', $clean)) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})*(\.\d{1,2})?$/', $clean)) {
            $clean = str_replace(',', '', $clean);
        } else {
            $clean = str_replace(',', '.', $clean);
        }

        if (! is_numeric($clean) || (float) $clean < 0) {
            return null;
        }

        return (int) round((float) $clean * 100);
    }
}
