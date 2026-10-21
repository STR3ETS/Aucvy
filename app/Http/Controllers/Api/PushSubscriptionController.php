<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web-push-inschrijvingen (hfst. 14). De browser levert het endpoint en de
 * sleutels; per apparaat één rij, gekoppeld aan de ingelogde gebruiker.
 *
 * Het endpoint wordt later vanaf de server aangeroepen. Daarom moet het een
 * https-adres van een pushdienst zijn en geen intern IP: anders is "schrijf
 * je in met endpoint http://10.0.0.5" een manier om de server naar binnen te
 * laten kijken bij de volgende overboden-melding.
 */
final class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url:https', $this->publicHost()],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            'aes128gcm',
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['subscribed' => false]);
    }

    /**
     * Een hostnaam met een punt erin, geen IP-adres en geen localhost.
     */
    private function publicHost(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));

            if ($host === '' || $host === 'localhost' || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP) !== false) {
                $fail(__('Dit is geen geldig push-endpoint.'));
            }
        };
    }
}
