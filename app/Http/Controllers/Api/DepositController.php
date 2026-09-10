<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Deposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * De borg vóór het bieden (hfst. 9): boven de drempel vraagt het platform
 * een reservering voordat iemand mag bieden.
 *
 * De echte pre-autorisatie bij Mollie hoort bij de derdengeldenkeuze
 * (hfst. 18); tot die tijd loopt de reservering via dezelfde nepcheckout als
 * de betalingen. Vrijgeven gebeurt al automatisch: verliezers bij het
 * sluiten, de winnaar bij de betaling van zijn order.
 */
final class DepositController extends Controller
{
    public function store(Request $request, Auction $auction): JsonResponse
    {
        abort_if($auction->isClosed(), 422);

        // Eén borg per bieder per veiling (unieke index); een eerdere poging
        // wordt hergebruikt in plaats van gedupliceerd.
        $deposit = Deposit::query()->firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'auction_id' => $auction->id,
            ],
            [
                'amount_cents' => (int) config('aucvy.deposit.threshold_cents'),
                'provider' => (string) config('aucvy.payments.provider'),
                'status' => DepositStatus::Pending,
            ],
        );

        if ($deposit->status === DepositStatus::Authorised) {
            return response()->json(['covered' => true]);
        }

        if (config('aucvy.payments.provider') !== 'fake') {
            /*
             | Eerlijk falen: de Mollie-pre-autorisatie bestaat nog niet.
             | Beter een duidelijke fout dan een borg die niets reserveert.
             */
            return response()->json([
                'message' => __('auctions.deposit.provider_missing'),
            ], 501);
        }

        return response()->json([
            'covered' => false,
            'checkout_url' => route('dev.deposit', $deposit),
        ]);
    }
}
