<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Experiments\HomeExperiment;
use App\Support\Privacy\CookieConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * De keuze uit de cookiebanner of de cookiepagina opslaan. Werkt met en
 * zonder JavaScript: een gewoon formulier gaat terug naar de pagina, de
 * banner krijgt JSON.
 */
final class CookieConsentController extends Controller
{
    public function store(Request $request, CookieConsent $consent): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'choice' => ['nullable', 'in:all,necessary,custom'],
            'statistics' => ['nullable', 'boolean'],
            'marketing' => ['nullable', 'boolean'],
        ]);

        [$statistics, $marketing] = match ($validated['choice'] ?? 'custom') {
            'all' => [true, true],
            'necessary' => [false, false],
            default => [$request->boolean('statistics'), $request->boolean('marketing')],
        };

        $cookie = $consent->cookie($statistics, $marketing);

        // Statistiek ingetrokken: ook de testgroep van de homepage vergeten.
        if (! $statistics) {
            Cookie::queue(Cookie::forget(HomeExperiment::COOKIE));
        }

        $response = $request->expectsJson()
            ? response()->json(['statistics' => $statistics, 'marketing' => $marketing])
            : back()->with('status', 'Je cookievoorkeur is opgeslagen.');

        return $response->withCookie($cookie);
    }
}
