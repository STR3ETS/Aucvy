<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\View\View;

/**
 * De publieke veilingpagina (briefing sectie 1: veilingdetail met meerdere
 * kavels): de kavels van één veiling met het sluitschema, bezichtiging,
 * ophalen en de voorwaarden van de verkoper.
 */
final class SaleController extends Controller
{
    public function show(Sale $sale): View
    {
        abort_unless($sale->isPublished(), 404);

        $sale->load(['seller', 'company']);

        $lots = $sale->lots()
            ->public()
            ->with(['auction', 'media', 'category'])
            ->get()
            ->sortBy(fn ($lot) => [$lot->auction?->isClosed() ? 1 : 0, (int) $lot->sale_position])
            ->values();

        return view('sales.show', [
            'sale' => $sale,
            'lots' => $lots,
            'state' => $sale->state(),
            'pending' => $sale->lots()->count() - $lots->count(),
        ]);
    }
}
