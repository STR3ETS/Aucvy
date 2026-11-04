<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Live\LiveProvider;
use App\Live\LiveSessions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhooks van de mediaserver (LiveKit): kijkersaantallen en het einde
 * van een kamer, met controle van de handtekening.
 */
final class LiveWebhookController extends Controller
{
    public function __invoke(Request $request, LiveProvider $provider, LiveSessions $sessions): Response
    {
        $event = $provider->webhook($request);

        abort_if($event === null, 403);

        $sessions->handleWebhook($event);

        return response()->noContent();
    }
}
