<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auctions\Actions\PlaceBid;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceBidRequest;
use App\Http\Resources\AuctionStateResource;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;

final class BidController extends Controller
{
    /**
     * Plaatst een bod en geeft de nieuwe stand terug.
     *
     * De controller rekent niets uit en beslist niets: hij geeft door aan de
     * Action en vertaalt de uitkomst naar JSON. Een geweigerd bod komt als
     * BidRejected naar boven en wordt centraal afgehandeld (bootstrap/app.php).
     */
    public function store(PlaceBidRequest $request, Auction $auction, PlaceBid $placeBid): JsonResponse
    {
        $bid = $placeBid->handle(
            auction: $auction,
            user: $request->user(),
            amountCents: $request->amountCents(),
            idempotencyKey: $request->idempotencyKey(),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            // Of dit bod leidt, of meteen is overboden door een automatisch
            // maximum van iemand anders.
            'bid' => [
                'id' => $bid->id,
                'amount_cents' => $bid->amount_cents,
                'status' => $bid->status->value,
                'sequence' => $bid->sequence,
            ],
            'state' => AuctionStateResource::make($auction->fresh(), $request->user())->toArray(),
        ], 201);
    }
}
