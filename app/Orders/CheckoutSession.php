<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * Wat een provider teruggeeft bij het starten van een betaling: zijn eigen
 * kenmerk en de URL waar de koper heen moet.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $providerId,
        public string $checkoutUrl,
    ) {}
}
