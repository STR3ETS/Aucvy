<?php

declare(strict_types=1);

namespace App\Support\Shipping\Providers;

use App\Models\Lot;
use App\Support\Shipping\Contracts\ShippingProvider;
use App\Support\Shipping\ShippingQuote;
use Carbon\CarbonImmutable;

/**
 * Deterministische indicatie zonder externe partner: het vanaftarief van de
 * kavel plus een afstandstoeslag op basis van de postcodegebieden. Dezelfde
 * kavel en postcode geven altijd hetzelfde bedrag - belangrijk voor de
 * cache én voor het vertrouwen van wie twee keer kijkt.
 */
final class FakeShippingProvider implements ShippingProvider
{
    /**
     * Toeslag per "postcodegebied" verschil (de eerste twee cijfers zeggen
     * grofweg waar in het land je bent).
     */
    private const SURCHARGE_PER_REGION_CENTS = 450;

    public function quote(Lot $lot, string $postalCode): ?ShippingQuote
    {
        if ($lot->pickup_only && $lot->transport_from_cents === null) {
            return null;
        }

        $base = $lot->transport_from_cents;

        if ($base === null) {
            return null;
        }

        $fromRegion = (int) substr($lot->location?->postal_code ?? '3500', 0, 2);
        $toRegion = (int) substr($postalCode, 0, 2);
        $distance = abs($fromRegion - $toRegion);

        return new ShippingQuote(
            amountCents: $base + $distance * self::SURCHARGE_PER_REGION_CENTS,
            validUntil: CarbonImmutable::now()->addHours(
                (int) config('aucvy.shipping.quote_ttl_hours'),
            ),
        );
    }
}
