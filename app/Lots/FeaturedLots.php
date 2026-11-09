<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Nu live": vier uitgelichte kavels, aflopend op eindtijd, met minimaal één
 * per verkoopvorm. Voor de home en het categorie-overzicht.
 */
final class FeaturedLots
{
    /**
     * @return Collection<int, Lot>
     */
    public function get(): Collection
    {
        /*
         | Alleen de id's gaan de cache in, geen modellen.
         |
         | Eloquent-modellen cachen ziet er zuinig uit maar is dat niet: de
         | serialisatie bevat de volledige klassestructuur, en zodra die
         | verandert komt er een __PHP_Incomplete_Class terug in plaats van een
         | model. Een lijst met id's overleeft elke codewijziging.
         |
         | Wat de cache hier bespaart is het echte werk: de join en de sortering
         | over alle lopende kavels.
         */
        $ids = Cache::remember('home.featured.ids', 60, fn (): array => $this->ids());

        if ($ids === []) {
            return collect();
        }

        return Lot::query()
            ->with(['auction', 'media', 'category', 'location'])
            ->whereIn('id', $ids)
            ->get()
            // De volgorde uit de cache aanhouden; whereIn geeft geen garantie.
            ->sortBy(fn (Lot $lot): int => array_search($lot->id, $ids, true))
            ->values();
    }

    /**
     * @return array<int, int>
     */
    private function ids(): array
    {
        $base = fn (): Builder => Lot::query()
            ->whereIn('lots.status', [LotStatus::Live, LotStatus::Closing])
            ->join('auctions', 'auctions.lot_id', '=', 'lots.id')
            ->whereNull('auctions.closed_at')
            ->select('lots.id')
            ->orderBy('auctions.ends_at');

        /*
         | Eerst één per verkoopvorm reserveren, dan aanvullen. Anders staat er
         | op een rustige dag zes keer een gewone veiling en lijkt het alsof
         | afslag en koop-direct niet bestaan.
         */
        $showcase = collect(SaleType::cases())
            ->map(fn (SaleType $type): ?int => $base()->where('lots.sale_type', $type)->value('lots.id'))
            ->filter()
            ->values();

        // Vier kaarten, zoals het ontwerp: het grid is repeat(4, 1fr).
        $rest = $base()
            ->whereNotIn('lots.id', $showcase->all())
            ->limit(max(0, 4 - $showcase->count()))
            ->pluck('lots.id');

        return $showcase->concat($rest)->take(4)->map(fn ($id): int => (int) $id)->all();
    }
}
