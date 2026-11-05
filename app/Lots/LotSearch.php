<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Models\Lot;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Zoeken en filteren over de kavels.
 *
 * ---------------------------------------------------------------------------
 * Waarom hier geen Meilisearch staat
 * ---------------------------------------------------------------------------
 *
 * De briefing schrijft Meilisearch via Scout voor. Dat is een losse dienst die
 * apart draait en beheerd moet worden, en op de omvang van de MVP levert hij
 * nog niets op wat MySQL niet kan: dezelfde facettellingen, dezelfde
 * sorteringen, dezelfde filters.
 *
 * Wat Meilisearch wél brengt is tekstzoeken dat tegen typefouten kan, en dat
 * wordt merkbaar zodra de catalogus groeit. Daarom zit alles wat de
 * zoekopdracht bouwt in déze klasse: omzetten is dan één bestand en niet een
 * zoektocht door controllers en views.
 *
 * ---------------------------------------------------------------------------
 * De prijs van een afslag
 * ---------------------------------------------------------------------------
 *
 * Bij een afslag zakt de prijs met de klok en loopt de opgeslagen kolom
 * bewust achter (hfst. 6). Filteren en sorteren op die kolom zou een afslag
 * van € 20.000 tonen die inmiddels € 8.000 kost. Daarom rekent de query hem
 * met dezelfde formule uit als de server en de client.
 */
final class LotSearch
{
    private const PER_PAGE = 24;

    public function results(LotFilters $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with(['auction', 'category', 'location', 'media'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function count(LotFilters $filters): int
    {
        return $this->query($filters)->count();
    }

    /**
     * De kavels die sinds een moment zijn gepubliceerd en aan de filters
     * voldoen: de treffers van een alert.
     *
     * @return Collection<int, Lot>
     */
    public function newSince(LotFilters $filters, ?CarbonInterface $since, int $limit = 10, ?CarbonInterface $until = null): Collection
    {
        return $this->query($filters)
            ->when($since !== null, fn (Builder $query) => $query->where('lots.published_at', '>', $since))
            ->when($until !== null, fn (Builder $query) => $query->where('lots.published_at', '<=', $until))
            ->with(['auction', 'category'])
            ->reorder('lots.published_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * De aantallen achter elke filteroptie.
     *
     * Elk facet wordt geteld met álle andere filters actief, maar zonder
     * zichzelf. Anders zou "veiling (12)" naar 12 zakken zodra je hem
     * aanvinkt, en kun je nooit zien wat "afslag" zou opleveren.
     *
     * Een optie die nul oplevert wordt uitgeschakeld getoond in plaats van
     * verborgen - dat scheelt doodlopende klikken (hfst. 13.2).
     *
     * @return array<string, array<string, int>>
     */
    public function facets(LotFilters $filters): array
    {
        return [
            'sale_type' => $this->countBy($filters->without('saleTypes'), 'lots.sale_type', SaleType::values()),
            'condition' => $this->countBy($filters->without('conditions'), 'lots.condition', LotCondition::values()),
            'province' => $this->countBy($filters->without('provinces'), 'locations.province'),
            'quick' => $this->countQuickFilters($filters),
            'toggles' => [
                'kijkdag' => $this->query($filters->without('viewingOnly'))
                    ->where('lots.viewing_available', true)->count(),
                'transport' => $this->query($filters->without('transportOnly'))
                    ->whereNotNull('lots.transport_from_cents')->count(),
            ],
        ];
    }

    /**
     * Welke filter loslaten het meeste oplevert.
     *
     * Dit is de motor onder de lege staat: nooit een doodlopende pagina, maar
     * één concreet voorstel (hfst. 13.2).
     *
     * Bewust deterministisch en niet door een model bedacht: welke filter de
     * meeste resultaten tegenhoudt is een telling, geen inschatting. In stap 9
     * krijgt Ivy hier haar formulering overheen - de keuze zelf blijft hier.
     */
    public function broadeningSuggestion(LotFilters $filters): ?array
    {
        $candidates = [
            'provinces' => __('lots.filters.province'),
            'price' => __('lots.filters.price'),
            'conditions' => __('lots.filters.condition'),
            'saleTypes' => __('lots.filters.sale_type'),
            'category' => __('lots.filters.category'),
            'quick' => __('lots.filters.quick'),
            'viewingOnly' => __('lots.filters.viewing'),
            'transportOnly' => __('lots.filters.transport'),
        ];

        $best = null;

        foreach ($candidates as $key => $label) {
            $without = $filters->without($key);

            // Niets veranderd? Dan stond die filter niet aan.
            if ($without->toQuery() === $filters->toQuery()) {
                continue;
            }

            $count = $this->count($without);

            if ($count > 0 && ($best === null || $count > $best['count'])) {
                $best = ['filter' => $key, 'label' => $label, 'count' => $count, 'filters' => $without];
            }
        }

        return $best;
    }

    // De query -------------------------------------------------------------

    /**
     * @return Builder<Lot>
     */
    private function query(LotFilters $filters): Builder
    {
        $query = Lot::query()
            ->join('auctions', 'auctions.lot_id', '=', 'lots.id')
            ->leftJoin('locations', 'locations.id', '=', 'lots.location_id')
            ->select('lots.*')
            ->whereIn('lots.status', [LotStatus::Live, LotStatus::Closing, LotStatus::Scheduled])
            ->whereNull('auctions.closed_at');

        $this->applyText($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applyQuick($query, $filters);

        if ($filters->saleTypes !== []) {
            $query->whereIn('lots.sale_type', $filters->saleTypes);
        }

        if ($filters->conditions !== []) {
            $query->whereIn('lots.condition', $filters->conditions);
        }

        if ($filters->provinces !== []) {
            $query->whereIn('locations.province', $filters->provinces);
        }

        if ($filters->priceMinCents !== null) {
            $query->whereRaw($this->priceExpression().' >= ?', [$filters->priceMinCents]);
        }

        if ($filters->priceMaxCents !== null) {
            $query->whereRaw($this->priceExpression().' <= ?', [$filters->priceMaxCents]);
        }

        if ($filters->viewingOnly) {
            $query->where('lots.viewing_available', true);
        }

        if ($filters->transportOnly) {
            $query->whereNotNull('lots.transport_from_cents');
        }

        $this->applySort($query, $filters);

        return $query;
    }

    /**
     * @param  Builder<Lot>  $query
     */
    private function applyText(Builder $query, LotFilters $filters): void
    {
        if ($filters->query === null) {
            return;
        }

        /*
         | Simpel en eerlijk: LIKE over de velden die een mens zou intypen,
         | per woord. Elk woord moet ergens voorkomen, in welke volgorde dan
         | ook, en een verbuiging telt mee: "professioneel gasfornuis" vindt
         | "Professionele gasfornuis". Dit is nog steeds het punt waar
         | Meilisearch later verschil maakt - "graafmachiene" vindt hier niets.
         */
        foreach (self::words($filters->query) as $word) {
            // De backslash eerst: die is zelf het ontsnappingsteken van LIKE.
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], self::stem($word)).'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('lots.title', 'like', $term)
                    ->orWhere('lots.brand', 'like', $term)
                    ->orWhere('lots.model', 'like', $term)
                    ->orWhere('lots.description', 'like', $term);
            });
        }
    }

    /**
     * De losse woorden van een zoekvraag, zonder leestekens aan de randen.
     * Een koppelteken blijft: "zit-sta" is één woord.
     *
     * @return list<string>
     */
    public static function words(string $query): array
    {
        $words = preg_split('/\s+/u', trim($query)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $word): string => trim($word, " \t\n\r\0\x0B.,;:!?\"'()"),
            $words,
        ), fn (string $word): bool => $word !== ''));
    }

    /**
     * Het deel van een woord dat bij een verbuiging gelijk blijft:
     * bureaus en bureau, stoelen en stoel, professioneel en professionele.
     * Korte woorden en woorden met een cijfer blijven zoals ze zijn.
     */
    public static function stem(string $word): string
    {
        $lower = mb_strtolower($word);

        if (mb_strlen($lower) <= 4 || preg_match('/\d/', $lower) === 1) {
            return $lower;
        }

        foreach (['eel', 'ele'] as $ending) {
            if (str_ends_with($lower, $ending)) {
                return mb_substr($lower, 0, -2);
            }
        }

        foreach (['en', 'e', 's'] as $ending) {
            if (str_ends_with($lower, $ending) && mb_strlen($lower) - mb_strlen($ending) >= 4) {
                return mb_substr($lower, 0, -mb_strlen($ending));
            }
        }

        return $lower;
    }

    /**
     * @param  Builder<Lot>  $query
     */
    private function applyCategory(Builder $query, LotFilters $filters): void
    {
        if ($filters->category === null) {
            return;
        }

        // Een hoofdcategorie toont ook alles wat eronder hangt.
        $query->whereExists(function ($sub) use ($filters): void {
            $sub->select(DB::raw(1))
                ->from('categories')
                ->whereColumn('categories.id', 'lots.category_id')
                ->where(function ($where) use ($filters): void {
                    $where->where('categories.slug', $filters->category)
                        ->orWhereExists(function ($parent) use ($filters): void {
                            $parent->select(DB::raw(1))
                                ->from('categories as parents')
                                ->whereColumn('parents.id', 'categories.parent_id')
                                ->where('parents.slug', $filters->category);
                        });
                });
        });
    }

    /**
     * @param  Builder<Lot>  $query
     */
    private function applyQuick(Builder $query, LotFilters $filters): void
    {
        match ($filters->quick) {
            LotFilters::QUICK_LIVE => $query->whereIn('lots.status', [LotStatus::Live, LotStatus::Closing]),
            LotFilters::QUICK_NEW => $query->where('lots.published_at', '>=', now()->subDays(2)),
            LotFilters::QUICK_LAST_CHANCE => $query->where('auctions.ends_at', '<=', now()->addDay()),
            // Alleen bij voldoende zekerheid: bij een lage zekerheid is de
            // indicatie niet scherp genoeg om als koopargument te dienen.
            LotFilters::QUICK_BELOW_VALUE => $query
                ->whereNotNull('lots.value_min_cents')
                ->whereIn('lots.value_confidence', [ValueConfidence::Medium, ValueConfidence::High])
                ->whereRaw($this->priceExpression().' < lots.value_min_cents'),
            default => null,
        };
    }

    /**
     * @param  Builder<Lot>  $query
     */
    private function applySort(Builder $query, LotFilters $filters): void
    {
        match ($filters->sort) {
            LotFilters::SORT_NEWEST => $query->orderByDesc('lots.published_at'),
            LotFilters::SORT_PRICE_ASC => $query->orderByRaw($this->priceExpression().' asc'),
            LotFilters::SORT_PRICE_DESC => $query->orderByRaw($this->priceExpression().' desc'),
            LotFilters::SORT_MOST_BIDS => $query->orderByDesc('auctions.bidder_count'),
            LotFilters::SORT_VALUE_GAP => $query
                ->orderByRaw('(lots.value_min_cents - '.$this->priceExpression().') desc'),
            // Relevantie zonder zoekterm is betekenisloos; dan is "sluit
            // eerst" het nuttigste antwoord.
            default => $query->orderBy('auctions.ends_at'),
        };

        // Altijd een tweede sleutel: zonder deze verspringt de volgorde tussen
        // pagina's bij gelijke waarden.
        $query->orderBy('lots.id');
    }

    /**
     * De geldende prijs, ook voor een afslag.
     *
     *     max(bodem, start − stap × floor((nu − starts_at) / interval))
     */
    private function priceExpression(): string
    {
        /*
         | Alle vier de instellingen moeten er zijn. Alleen op start_cents
         | controleren zou bij een half ingericht kavel een deling door NULL
         | opleveren, en dan valt de hele rij uit de resultaten.
         */
        return "(CASE
            WHEN lots.sale_type = 'dutch'
                AND auctions.dutch_start_cents IS NOT NULL
                AND auctions.dutch_floor_cents IS NOT NULL
                AND auctions.dutch_step_cents IS NOT NULL
                AND auctions.dutch_interval_s > 0
            THEN GREATEST(
                auctions.dutch_floor_cents,
                auctions.dutch_start_cents - auctions.dutch_step_cents * FLOOR(
                    GREATEST(0, TIMESTAMPDIFF(SECOND, auctions.starts_at, NOW())) / auctions.dutch_interval_s
                )
            )
            ELSE auctions.current_price_cents
        END)";
    }

    // Facettellingen -------------------------------------------------------

    /**
     * @param  array<int, string>|null  $expected
     * @return array<string, int>
     */
    private function countBy(LotFilters $filters, string $column, ?array $expected = null): array
    {
        $counts = $this->query($filters)
            ->reorder()
            ->select($column.' as facet_value', DB::raw('count(distinct lots.id) as total'))
            ->groupBy($column)
            ->pluck('total', 'facet_value')
            ->all();

        if ($expected === null) {
            unset($counts['']);

            return array_filter($counts, fn (mixed $key): bool => $key !== null, ARRAY_FILTER_USE_KEY);
        }

        // Ook de opties met nul teruggeven: die worden uitgeschakeld getoond,
        // niet verborgen.
        $complete = [];

        foreach ($expected as $value) {
            $complete[$value] = (int) ($counts[$value] ?? 0);
        }

        return $complete;
    }

    /**
     * @return array<string, int>
     */
    private function countQuickFilters(LotFilters $filters): array
    {
        $counts = [];

        foreach (LotFilters::quickOptions() as $option) {
            $counts[$option] = $this->count($filters->withQuick($option));
        }

        return $counts;
    }
}
