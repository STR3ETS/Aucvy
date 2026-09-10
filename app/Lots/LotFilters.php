<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\LotCondition;
use App\Enums\SaleType;
use Illuminate\Http\Request;

/**
 * De filters zoals ze in de URL staan.
 *
 * Elke filter is een querystringparameter, en dat is geen implementatiedetail:
 * het maakt een filtercombinatie deelbaar, laat de terugknop werken, en geeft
 * de servercache iets om op te werken (hfst. 13.2).
 *
 * Dit object is de enige plek die de querystring leest. Zou een controller of
 * een Blade-view er zelf in graaien, dan is de vraag "welke filters zijn er
 * eigenlijk" niet meer te beantwoorden.
 */
final readonly class LotFilters
{
    /**
     * @param  array<int, string>  $saleTypes
     * @param  array<int, string>  $conditions
     * @param  array<int, string>  $provinces
     */
    public function __construct(
        public ?string $query = null,
        public ?string $category = null,
        public string $quick = self::QUICK_ALL,
        public array $saleTypes = [],
        public array $conditions = [],
        public array $provinces = [],
        public ?int $priceMinCents = null,
        public ?int $priceMaxCents = null,
        public bool $viewingOnly = false,
        public bool $transportOnly = false,
        public string $sort = self::SORT_ENDING_SOON,
    ) {}

    // Snelfilters bovenaan, uit preview v4.
    public const QUICK_ALL = 'alles';

    public const QUICK_LIVE = 'live';

    public const QUICK_NEW = 'nieuw';

    public const QUICK_LAST_CHANCE = 'laatste-kans';

    public const QUICK_BELOW_VALUE = 'onder-marktwaarde';

    /**
     * De briefing noemt ook "faillissementen" als snelfilter. Die staat hier
     * bewust niet: er is geen veld dat vastlegt of een kavel uit een
     * faillissement komt, en die vraag is niet technisch maar inhoudelijk -
     * wie bepaalt dat, en op basis waarvan. Zie het open punt in de
     * samenvatting van stap 7.
     */
    public const SORT_ENDING_SOON = 'sluit-eerst';

    public const SORT_NEWEST = 'nieuwste';

    public const SORT_PRICE_ASC = 'prijs-op';

    public const SORT_PRICE_DESC = 'prijs-af';

    public const SORT_MOST_BIDS = 'meeste-biedingen';

    public const SORT_VALUE_GAP = 'onder-waarde';

    public const SORT_RELEVANCE = 'relevantie';

    public static function fromRequest(Request $request): self
    {
        return new self(
            query: self::trimmed($request->query('q')),
            category: self::trimmed($request->query('categorie')),
            quick: self::oneOf($request->query('toon'), self::quickOptions(), self::QUICK_ALL),
            saleTypes: self::manyOf($request->query('vorm'), SaleType::values()),
            conditions: self::manyOf($request->query('conditie'), LotCondition::values()),
            provinces: self::many($request->query('provincie')),
            priceMinCents: self::euroToCents($request->query('vanaf')),
            priceMaxCents: self::euroToCents($request->query('tot')),
            viewingOnly: $request->boolean('kijkdag'),
            transportOnly: $request->boolean('transport'),
            sort: self::oneOf($request->query('sorteer'), self::sortOptions(), self::SORT_ENDING_SOON),
        );
    }

    /**
     * Hoeveel filters er actief zijn. De mobiele filterknop toont dit aantal,
     * zodat je onder een dichtgeklapte bottom sheet niet vergeet dat er nog
     * iets aan staat (hfst. 13.2).
     */
    public function activeCount(): int
    {
        return count(array_filter([
            $this->category !== null,
            $this->quick !== self::QUICK_ALL,
            $this->saleTypes !== [],
            $this->conditions !== [],
            $this->provinces !== [],
            $this->priceMinCents !== null,
            $this->priceMaxCents !== null,
            $this->viewingOnly,
            $this->transportOnly,
        ]));
    }

    public function hasAny(): bool
    {
        return $this->activeCount() > 0 || $this->query !== null;
    }

    /**
     * Dezelfde filters, met er eentje weggehaald.
     *
     * Gebruikt door de lege staat: die rekent uit welke filter de meeste
     * resultaten tegenhoudt en stelt precies díé voor om los te laten.
     */
    public function without(string $filter): self
    {
        return new self(
            query: $filter === 'query' ? null : $this->query,
            category: $filter === 'category' ? null : $this->category,
            quick: $filter === 'quick' ? self::QUICK_ALL : $this->quick,
            saleTypes: $filter === 'saleTypes' ? [] : $this->saleTypes,
            conditions: $filter === 'conditions' ? [] : $this->conditions,
            provinces: $filter === 'provinces' ? [] : $this->provinces,
            priceMinCents: $filter === 'price' ? null : $this->priceMinCents,
            priceMaxCents: $filter === 'price' ? null : $this->priceMaxCents,
            viewingOnly: $filter === 'viewingOnly' ? false : $this->viewingOnly,
            transportOnly: $filter === 'transportOnly' ? false : $this->transportOnly,
            sort: $this->sort,
        );
    }

    /**
     * Dezelfde filters met een ander snelfilter. Gebruikt om te tellen wat
     * elke tab zou opleveren zonder de rest van de filters los te laten.
     */
    public function withQuick(string $quick): self
    {
        return new self(
            query: $this->query,
            category: $this->category,
            quick: $quick,
            saleTypes: $this->saleTypes,
            conditions: $this->conditions,
            provinces: $this->provinces,
            priceMinCents: $this->priceMinCents,
            priceMaxCents: $this->priceMaxCents,
            viewingOnly: $this->viewingOnly,
            transportOnly: $this->transportOnly,
            sort: $this->sort,
        );
    }

    /**
     * De querystring die bij deze filters hoort, eventueel met een wijziging.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function toQuery(array $changes = []): array
    {
        $query = array_filter([
            'q' => $this->query,
            'categorie' => $this->category,
            'toon' => $this->quick === self::QUICK_ALL ? null : $this->quick,
            'vorm' => $this->saleTypes ?: null,
            'conditie' => $this->conditions ?: null,
            'provincie' => $this->provinces ?: null,
            'vanaf' => $this->priceMinCents !== null ? intdiv($this->priceMinCents, 100) : null,
            'tot' => $this->priceMaxCents !== null ? intdiv($this->priceMaxCents, 100) : null,
            'kijkdag' => $this->viewingOnly ? 1 : null,
            'transport' => $this->transportOnly ? 1 : null,
            'sorteer' => $this->sort === self::SORT_ENDING_SOON ? null : $this->sort,
        ], fn (mixed $value): bool => $value !== null);

        foreach ($changes as $key => $value) {
            if ($value === null || $value === [] || $value === false) {
                unset($query[$key]);

                continue;
            }

            $query[$key] = $value;
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public static function quickOptions(): array
    {
        return [
            self::QUICK_ALL,
            self::QUICK_LIVE,
            self::QUICK_NEW,
            self::QUICK_LAST_CHANCE,
            self::QUICK_BELOW_VALUE,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function sortOptions(): array
    {
        return [
            self::SORT_RELEVANCE,
            self::SORT_ENDING_SOON,
            self::SORT_NEWEST,
            self::SORT_PRICE_ASC,
            self::SORT_PRICE_DESC,
            self::SORT_MOST_BIDS,
            self::SORT_VALUE_GAP,
        ];
    }

    // Parsen ---------------------------------------------------------------

    private static function trimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 120);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private static function oneOf(mixed $value, array $allowed, string $fallback): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
    }

    /**
     * @return array<int, string>
     */
    private static function many(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $item): string => is_string($item) ? trim($item) : '', $value),
        )));
    }

    /**
     * @param  array<int, string>  $allowed
     * @return array<int, string>
     */
    private static function manyOf(mixed $value, array $allowed): array
    {
        // Onbekende waarden stil laten vallen: een verzonnen querystring hoort
        // geen lege lijst op te leveren maar genegeerd te worden.
        return array_values(array_intersect(self::many($value), $allowed));
    }

    private static function euroToCents(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $cents = (int) round((float) $value * 100);

        return $cents >= 0 ? $cents : null;
    }
}
