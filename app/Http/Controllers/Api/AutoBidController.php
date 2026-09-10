<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auctions\Actions\CancelAutoBid;
use App\Auctions\Actions\PlaceAutoBid;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuctionStateResource;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AutoBidController extends Controller
{
    /**
     * Zet of verhoogt een maximum. Verlagen geeft 422 met uitleg (hfst. 7).
     */
    public function store(Request $request, Auction $auction, PlaceAutoBid $placeAutoBid): JsonResponse
    {
        $validated = $request->validate([
            'max_amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        $autoBid = $placeAutoBid->handle(
            auction: $auction,
            user: $request->user(),
            maxAmountCents: (int) $validated['max_amount_cents'],
        );

        return response()->json([
            // Alleen aan de eigenaar zelf teruggeven. Voor iedereen anders
            // bestaat dit bedrag niet.
            'auto_bid' => [
                'max_amount_cents' => $autoBid->max_amount_cents,
                'active' => $autoBid->active,
            ],
            'state' => AuctionStateResource::make($auction->fresh(), $request->user())->toArray(),
        ], 201);
    }

    /**
     * Haalt het maximum weg.
     *
     * Het reeds geplaatste bod blijft staan - dat zegt de UI er expliciet bij,
     * want de verwachting is vaak het tegenovergestelde (hfst. 7).
     */
    public function destroy(Request $request, Auction $auction, CancelAutoBid $cancelAutoBid): JsonResponse
    {
        $cancelled = $cancelAutoBid->handle($auction, $request->user());

        return response()->json([
            'cancelled' => $cancelled !== null,
            'standing_bid_remains' => true,
            'state' => AuctionStateResource::make($auction->fresh(), $request->user())->toArray(),
        ]);
    }
}
