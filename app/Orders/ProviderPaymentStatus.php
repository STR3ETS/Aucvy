<?php

declare(strict_types=1);

namespace App\Orders;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/**
 * De stand van één betaling volgens de provider, vertaald naar onze enum.
 */
final readonly class ProviderPaymentStatus
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $method = null,
        public ?CarbonImmutable $paidAt = null,
    ) {}
}
