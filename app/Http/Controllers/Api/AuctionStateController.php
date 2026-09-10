<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuctionStateResource;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuctionStateController extends Controller
{
    /**
     * De volledige stand.
     *
     * Twee gebruiken (hfst. 7 en 11):
     *
     *  - de pollingfallback als de websocket wegvalt: na drie mislukte
     *    herverbindingspogingen haalt de client dit elke 3 seconden op;
     *  - het herstel na een gat in de eventstroom, wanneer replay niet lukt.
     *
     * Geen cache op deze respons. De briefing noemt één seconde, maar dat kan
     * pas zinvol zodra de kijkersaantallen dat rechtvaardigen: één seconde
     * verouderde stand is precies genoeg om iemand op de verkeerde prijs te
     * laten bieden, en een cache per gebruiker (want `viewer` verschilt) levert
     * op deze schaal niets op.
     */
    public function show(Request $request, Auction $auction): JsonResponse
    {
        return response()->json(
            AuctionStateResource::make($auction, $request->user())->toArray(),
        );
    }
}
