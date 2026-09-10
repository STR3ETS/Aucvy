<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auctions\Actions\BuyNow;
use App\Http\Controllers\Controller;
use App\Http\Requests\BuyNowRequest;
use App\Http\Resources\AuctionStateResource;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;

final class BuyNowController extends Controller
{
    /**
     * Twee stappen in één endpoint (hfst. 7):
     *
     *   confirm=false   geeft de kostenopbouw terug, koopt niets
     *   confirm=true    voert de aankoop uit
     *
     * Dat is geen omslachtigheid maar een eis uit de mobiele buildlijst: geen
     * schuifknop, maar een knop plus een expliciete bevestiging. Per ongeluk
     * € 13.750 uitgeven mag niet één tik kosten.
     */
    public function store(BuyNowRequest $request, Auction $auction, BuyNow $buyNow): JsonResponse
    {
        if (! $request->isConfirmed()) {
            return response()->json([
                'confirmed' => false,
                'costs' => $buyNow->preview($auction, $request->transportCents())->toArray(),
            ]);
        }

        $order = $buyNow->handle(
            auction: $auction,
            user: $request->user(),
            idempotencyKey: $request->idempotencyKey(),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'confirmed' => true,
            'order' => [
                'order_number' => $order->order_number,
                'total_cents' => $order->total_cents,
                'payment_deadline_at' => $order->payment_deadline_at?->toIso8601String(),
            ],
            'state' => AuctionStateResource::make($auction->fresh(), $request->user())->toArray(),
        ], 201);
    }
}
