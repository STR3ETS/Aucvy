<?php

declare(strict_types=1);

namespace App\Support\Shipping\Contracts;

use App\Models\Lot;
use App\Support\Shipping\ShippingQuote;

/**
 * Transportindicaties achter een interface (hfst. 14): een echte partner
 * (Brenger, Wuunder, Transmission) is straks één klasse erbij; de rest van
 * de applicatie merkt daar niets van.
 */
interface ShippingProvider
{
    /**
     * Een indicatie voor het bezorgen van deze kavel op deze postcode,
     * of null als deze kavel niet bezorgd wordt.
     */
    public function quote(Lot $lot, string $postalCode): ?ShippingQuote;
}
