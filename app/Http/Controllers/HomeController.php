<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Lots\CategoryCounts;
use App\Models\Category;
use App\Models\Lot;
use App\Support\Experiments\HomeExperiment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final class HomeController extends Controller
{
    /**
     * "Live op Aucvy - de handel van dit moment."
     *
     * Zes uitgelichte kavels, aflopend op eindtijd, met minimaal één per
     * verkoopvorm. De eindtijden lopen client-side door (hfst. 13.1).
     *
     * Bewust géén websocket op de home: dat zijn honderd verbindingen voor
     * niets. De kaarten kloppen weer bij de volgende paginaweergave.
     *
     * De A/B-test bepaalt welk ontwerp rendert: 'home' (het huidige) of
     * 'home-v4' (preview v4). Beide krijgen dezelfde data; alleen de
     * presentatie verschilt, want dat is wat de test vergelijkt.
     */
    public function __invoke(Request $request, HomeExperiment $experiment, CategoryCounts $categoryCounts): View
    {
        $variant = $experiment->resolve($request);
        $categories = Category::roots()->with('children')->get();

        return view($variant->view(), [
            'variant' => $variant,
            // In de vergelijkingspagina (iframe) blijft de wisselknop weg.
            'demo' => $request->boolean(HomeExperiment::DEMO),
            'featured' => $this->featured(),
            // Acht rijen plus hun subcategorieën. Cachen levert hier niets op en
            // zou een nieuwe categorie tien minuten onzichtbaar maken.
            'categories' => $categories,
            // Het aantal zichtbare kavels per hoofdcategorie, voor de kaarten.
            'categoryTotals' => $categoryCounts->perRoot($categories),
        ]);
    }

    /**
     * @return Collection<int, Lot>
     */
    private function featured(): Collection
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
        $ids = Cache::remember('home.featured.ids', 60, fn (): array => $this->featuredIds());

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
    private function featuredIds(): array
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
