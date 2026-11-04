<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Live\LiveSessions;
use App\Models\LiveSession;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kijken mag zonder account: een kijkerstoken voor de lopende uitzending
 * van een kavel, alleen ontvangen. Bieden blijft de bestaande biedmotor.
 */
final class LiveViewerController extends Controller
{
    public function __invoke(Request $request, Lot $lot, LiveSessions $sessions): JsonResponse
    {
        $session = LiveSession::query()->where('lot_id', $lot->id)->where('status', LiveSession::LIVE)->latest('id')->first();

        if (! LiveSessions::enabled() || $session === null || ! $lot->status->isPublic()) {
            return response()->json(['live' => false]);
        }

        if ($session->viewers_now >= $session->max_viewers) {
            return response()->json(['live' => true, 'full' => true, 'message' => 'De uitzending zit vol. Bieden kan gewoon; de prijs loopt live mee.'], 200);
        }

        $validated = $request->validate(['token' => ['nullable', 'string', 'max:64']]);

        return response()->json([
            'live' => true,
            'full' => false,
            'room' => $session->room,
            'url' => $sessions->clientUrl(),
            'token' => $sessions->viewerToken($session, $request->user(), (string) ($validated['token'] ?? $request->ip())),
            'viewers' => $session->viewers_now,
        ]);
    }
}
