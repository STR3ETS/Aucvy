<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\LotFilters;
use App\Lots\LotSearch;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

final class LotIndexController extends Controller
{
    public function __construct(private readonly LotSearch $search) {}

    /**
     * Ontdekken en zoeken.
     *
     * Alle filters komen uit de querystring en gaan er ook weer in terug. Er
     * wordt niets in de sessie onthouden: een gefilterde lijst moet je kunnen
     * delen, en de terugknop moet doen wat je verwacht (hfst. 13.2).
     */
    public function index(Request $request, ?Category $category = null): View
    {
        $filters = LotFilters::fromRequest($request);

        // /veilingen/{category:slug} is dezelfde pagina met de categorie
        // voorgeselecteerd (hfst. 7).
        if ($category !== null) {
            $filters = new LotFilters(
                query: $filters->query,
                category: $category->slug,
                quick: $filters->quick,
                saleTypes: $filters->saleTypes,
                conditions: $filters->conditions,
                provinces: $filters->provinces,
                priceMinCents: $filters->priceMinCents,
                priceMaxCents: $filters->priceMaxCents,
                viewingOnly: $filters->viewingOnly,
                transportOnly: $filters->transportOnly,
                sort: $filters->sort,
            );
        }

        return $this->render($filters);
    }

    /**
     * Direct kopen als eigen overzicht (briefing sectie 1): dezelfde pagina,
     * de verkoopvorm staat vast.
     */
    public function buyNow(Request $request): View
    {
        $filters = LotFilters::fromRequest($request);

        $filters = new LotFilters(
            query: $filters->query,
            category: $filters->category,
            quick: $filters->quick,
            saleTypes: ['buy_now'],
            conditions: $filters->conditions,
            provinces: $filters->provinces,
            priceMinCents: $filters->priceMinCents,
            priceMaxCents: $filters->priceMaxCents,
            viewingOnly: $filters->viewingOnly,
            transportOnly: $filters->transportOnly,
            sort: $filters->sort,
        );

        return $this->render($filters, [
            'pageTitle' => 'Direct kopen',
            'pageIntro' => 'Kavels met een vaste prijs: geen biedstrijd, meteen van jou. Ook deze lopen via de betaling en zekerheid van het platform.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function render(LotFilters $filters, array $extra = []): View
    {
        $results = $this->search->results($filters);

        return view('lots.index', $extra + [
            'filters' => $filters,
            'results' => $results,
            // Tien tellingen over de hele open set per bezoek; een minuut
            // cache per filtercombinatie houdt dat van de kritieke route af.
            'facets' => Cache::remember(
                'lots:facets:'.md5(json_encode($filters->toQuery())),
                now()->addMinute(),
                fn (): array => $this->search->facets($filters),
            ),
            'categories' => Category::roots()->get(),
            /*
             | Alleen uitrekenen als er niets gevonden is. De lege staat mag
             | nooit doodlopen, maar acht extra tellingen op elke gevulde
             | pagina is verspilling.
             */
            'suggestion' => $results->total() === 0
                ? $this->search->broadeningSuggestion($filters)
                : null,
        ]);
    }
}
