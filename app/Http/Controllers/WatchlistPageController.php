<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lot;
use App\Models\WatchlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * De volglijst (briefing sectie 3): volgen en ontvolgen, met de stand en
 * de sluittijd per kavel. Volgen zelf gebeurt op de kavelpagina.
 */
final class WatchlistPageController extends Controller
{
    public function index(Request $request): View
    {
        $items = WatchlistItem::query()
            ->where('user_id', $request->user()->id)
            ->with(['lot.auction', 'lot.category', 'lot.media'])
            ->latest('id')
            ->limit(500)
            ->get()
            ->filter(fn (WatchlistItem $item): bool => $item->lot !== null);

        return view('dashboard.watchlist', [
            'items' => $items,
            'endingSoon' => $items->filter(fn (WatchlistItem $item): bool => $item->lot->auction !== null
                && ! $item->lot->auction->isClosed()
                && ! $item->lot->auction->isPaused()
                && $item->lot->auction->ends_at->lessThanOrEqualTo(now()->addDay()))->count(),
            'closed' => $items->filter(fn (WatchlistItem $item): bool => $item->lot->auction?->isClosed() ?? false)->count(),
        ]);
    }

    public function destroy(Request $request, Lot $lot): RedirectResponse
    {
        $removed = WatchlistItem::query()
            ->where('user_id', $request->user()->id)
            ->where('lot_id', $lot->id)
            ->delete();

        // De titel alleen terugzeggen als de kavel echt gevolgd werd: anders
        // is dit een manier om titels van willekeurige id's op te vragen.
        return back()->with('status', $removed > 0
            ? '"'.$lot->title.'" staat niet meer op je volglijst.'
            : 'Deze kavel stond niet op je volglijst.');
    }
}
