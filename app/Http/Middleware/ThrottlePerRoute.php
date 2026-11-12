<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * `throttle:30,1` met een teller per route.
 *
 * Laravel bouwt de sleutel van een limiet zonder naam alleen uit de gebruiker
 * (of het IP-adres). Alle routes met zo'n limiet deelden dus één teller: het
 * pollen van Ivy tijdens een analyse (ongeveer veertig keer per minuut) liet
 * daarna elke route met een lagere limiet een 429 geven, zoals een concept
 * verwijderen of de volgende Ivy-taak starten. Met de route erbij telt elke
 * limiet alleen zijn eigen verzoeken.
 */
final class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        return parent::resolveRequestSignature($request).'|'.($route?->getName() ?? $route?->uri() ?? '');
    }
}
