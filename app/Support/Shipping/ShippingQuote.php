<?php

declare(strict_types=1);

namespace App\Support\Shipping;

use App\Support\Money\Money;
use Carbon\CarbonImmutable;

/**
 * Eén transportindicatie: een bedrag met een houdbaarheidsdatum. Het is een
 * indicatie, geen offerte - dat zegt de UI er ook altijd bij.
 */
final readonly class ShippingQuote
{
    public function __construct(
        public int $amountCents,
        public CarbonImmutable $validUntil,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount_cents' => $this->amountCents,
            'amount_formatted' => Money::format($this->amountCents),
            'valid_until' => $this->validUntil->toIso8601String(),
        ];
    }
}
