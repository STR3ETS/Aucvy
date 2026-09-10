<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\WatchlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WatchlistController extends Controller
{
    /**
     * Volgen kan zonder verificatie: die blokkeert alleen het bieden, niet het
     * rondkijken (hfst. 13.6).
     */
    public function store(Request $request, Lot $lot): JsonResponse
    {
        WatchlistItem::firstOrCreate([
            'user_id' => $request->user()->id,
            'lot_id' => $lot->id,
        ]);

        return response()->json(['watching' => true], 201);
    }

    public function destroy(Request $request, Lot $lot): JsonResponse
    {
        WatchlistItem::query()
            ->where('user_id', $request->user()->id)
            ->where('lot_id', $lot->id)
            ->delete();

        return response()->json(['watching' => false]);
    }
}
