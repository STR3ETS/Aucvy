<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Auctions\Support\ViewerPresence;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * De hartslag achter "x kijken nu". Bewust zonder auth: een gast die
 * meekijkt telt ook. Het token is een willekeurig tabblad-id, geen persoon.
 */
final class ViewerPresenceController extends Controller
{
    public function __construct(private readonly ViewerPresence $presence) {}

    public function store(Request $request, Auction $auction): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:8', 'max:64'],
        ]);

        return response()->json([
            'watching_now' => $this->presence->beat($auction->id, $validated['token']),
        ]);
    }
}
