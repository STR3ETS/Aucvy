<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\LotFilters;
use App\Lots\LotSearch;
use App\Models\Category;
use Illuminate\Http\Request;
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

        $results = $this->search->results($filters);

        return view('lots.index', [
            'filters' => $filters,
            'results' => $results,
            'facets' => $this->search->facets($filters),
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
