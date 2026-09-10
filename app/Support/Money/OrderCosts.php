<?php

declare(strict_types=1);

namespace App\Support\Money;

use App\Enums\VatRegime;
use App\Models\Order;

/**
 * De volledige kostenopbouw van één transactie.
 *
 * Dit object is de enige bron van waarheid voor drie plekken die het niet
 * oneens mogen zijn: het totaal dat de koper ziet vóór hij bevestigt, het
 * bedrag op de order, en het bedrag dat naar de betaalprovider gaat. Lopen ze
 * uiteen, dan is dat per definitie een bug en geen afronding (hfst. 9).
 */
final readonly class OrderCosts
{
    public function __construct(
        public int $hammerCents,
        public int $buyerPremiumCents,
        public int $transportCents,
        public int $vatCents,
        public int $totalCents,
        public VatRegime $vatRegime,
    ) {}

    /**
     * Leest de opbouw terug van een bestaande order.
     *
     * Bedoeld om te vergelijken met een verse berekening: als
     * `Money::forOrder(...)->equals(OrderCosts::fromOrder($order))` onwaar is,
     * is er onderweg iets verschoven.
     */
    public static function fromOrder(Order $order): self
    {
        return new self(
            hammerCents: $order->hammer_cents,
            buyerPremiumCents: $order->buyer_premium_cents,
            transportCents: $order->transport_cents,
            vatCents: $order->vat_cents,
            totalCents: $order->total_cents,
            vatRegime: $order->vat_regime,
        );
    }

    public function equals(self $other): bool
    {
        return $this->hammerCents === $other->hammerCents
            && $this->buyerPremiumCents === $other->buyerPremiumCents
            && $this->transportCents === $other->transportCents
            && $this->vatCents === $other->vatCents
            && $this->totalCents === $other->totalCents
            && $this->vatRegime === $other->vatRegime;
    }

    /**
     * Het bedrag waarover btw berekend is. Bij de margeregeling is dat niets.
     */
    public function taxableBaseCents(): int
    {
        return $this->vatRegime->isDeductible()
            ? $this->hammerCents + $this->buyerPremiumCents + $this->transportCents
            : 0;
    }

    /**
     * De regels zoals het CostBreakdown-component ze toont.
     *
     * Het opgeld staat er alleen als het niet nul is: een regel "Opgeld € 0,00"
     * roept de vraag op of er ooit wél opgeld komt, en dat is niet het gesprek
     * dat je op de kavelpagina wilt voeren.
     *
     * @return array<int, CostLine>
     */
    public function lines(): array
    {
        $lines = [
            new CostLine('hammer', __('money.lines.hammer'), $this->hammerCents),
        ];

        if ($this->buyerPremiumCents > 0) {
            $lines[] = new CostLine('buyer_premium', __('money.lines.buyer_premium'), $this->buyerPremiumCents);
        }

        $lines[] = new CostLine(
            key: 'transport',
            label: __('money.lines.transport'),
            amountCents: $this->transportCents,
            note: $this->transportCents === 0 ? __('money.notes.pickup') : null,
        );

        $lines[] = new CostLine(
            key: 'vat',
            label: $this->vatRegime->isDeductible()
                ? __('money.lines.vat', ['rate' => $this->vatRatePercent()])
                : __('money.lines.margin'),
            amountCents: $this->vatCents,
            note: $this->vatRegime->isDeductible() ? null : __('money.notes.margin'),
        );

        return $lines;
    }

    private function vatRatePercent(): string
    {
        return rtrim(rtrim(number_format(Money::vatRatePercent(), 1, ',', ''), '0'), ',');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hammer_cents' => $this->hammerCents,
            'buyer_premium_cents' => $this->buyerPremiumCents,
            'transport_cents' => $this->transportCents,
            'vat_cents' => $this->vatCents,
            'total_cents' => $this->totalCents,
            'vat_regime' => $this->vatRegime->value,
            'total_formatted' => Money::format($this->totalCents),
            'lines' => array_map(fn (CostLine $line): array => $line->toArray(), $this->lines()),
        ];
    }
}
