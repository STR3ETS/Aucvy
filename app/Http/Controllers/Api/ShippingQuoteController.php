<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Support\Shipping\Contracts\ShippingProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Transportindicatie (hfst. 7): postcode in, prijs uit. Per kavel en
 * postcodegebied 24 uur gecached, zodat een echte partner straks niet bij
 * elke paginaweergave bevraagd wordt - en twee kijkers hetzelfde zien.
 */
final class ShippingQuoteController extends Controller
{
    public function store(Request $request, ShippingProvider $provider): JsonResponse
    {
        $validated = $request->validate([
            'lot_id' => ['required', 'integer', 'exists:lots,id'],
            'postcode' => ['required', 'string', 'regex:/^[1-9][0-9]{3}\s?[a-zA-Z]{2}$/'],
        ], [
            'postcode.regex' => 'Vul een Nederlandse postcode in, bijvoorbeeld 3512 AB.',
        ]);

        $lot = Lot::query()->findOrFail($validated['lot_id']);

        if (! $lot->status->isPublic()) {
            abort(404);
        }

        $postcode = strtoupper(str_replace(' ', '', $validated['postcode']));

        $quote = Cache::remember(
            "shipping:{$lot->id}:{$postcode}",
            now()->addHours((int) config('aucvy.shipping.quote_ttl_hours')),
            fn () => $provider->quote($lot, $postcode)?->toArray(),
        );

        if ($quote === null) {
            return response()->json([
                'message' => 'Deze kavel wordt niet bezorgd - ophalen in overleg.',
            ], 422);
        }

        return response()->json(['quote' => $quote]);
    }
}
