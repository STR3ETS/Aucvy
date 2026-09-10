<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * Eén regel in de kostenopbouw, klaar om te tonen.
 *
 * De UI rekent nooit zelf: hij toont wat hier uit komt. Zou de kavelpagina het
 * totaal client-side narekenen, dan is een afwijking tussen scherm en factuur
 * een kwestie van tijd (hfst. 13.3).
 */
final readonly class CostLine
{
    public function __construct(
        public string $key,
        public string $label,
        public int $amountCents,
        /** Kleine toelichting onder de regel, bv. "niet verrekenbaar". */
        public ?string $note = null,
        /** Regels zonder bedrag tonen een streepje in plaats van "€ 0,00". */
        public bool $isZeroMeaningful = true,
    ) {}

    public function formatted(): string
    {
        return Money::format($this->amountCents);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'amount_cents' => $this->amountCents,
            'formatted' => $this->formatted(),
            'note' => $this->note,
        ];
    }
}
