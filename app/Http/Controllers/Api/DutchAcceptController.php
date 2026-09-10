<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auctions\Actions\AcceptDutchPrice;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptDutchPriceRequest;
use App\Http\Resources\AuctionStateResource;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;

final class DutchAcceptController extends Controller
{
    /**
     * Mijnt af tegen de prijs die de server berekent.
     *
     * De client stuurt mee wélke prijs hij op het scherm had. Wijkt die meer
     * dan één biedstap af, dan komt er een 409 met de actuele prijs terug in
     * plaats van een afrekening waar de koper niet om vroeg (hfst. 7). Die
     * 409 rolt uit BidRejected en wordt centraal afgehandeld.
     */
    public function store(
        AcceptDutchPriceRequest $request,
        Auction $auction,
        AcceptDutchPrice $acceptDutchPrice,
    ): JsonResponse {
        $order = $acceptDutchPrice->handle(
            auction: $auction,
            user: $request->user(),
            seenPriceCents: $request->seenPriceCents(),
            idempotencyKey: $request->idempotencyKey(),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'order' => [
                'order_number' => $order->order_number,
                'hammer_cents' => $order->hammer_cents,
                'total_cents' => $order->total_cents,
                'payment_deadline_at' => $order->payment_deadline_at?->toIso8601String(),
            ],
            'state' => AuctionStateResource::make($auction->fresh(), $request->user())->toArray(),
        ], 201);
    }
}
