<?php

declare(strict_types=1);

namespace App\Support\Money;

/**
 * Wat de verkoper overhoudt: de hamerslag min de succesfee.
 *
 * Bewust los van OrderCosts. De koperkant en de verkoperkant zijn twee
 * verschillende sommen over hetzelfde bedrag, en ze door elkaar halen is hoe
 * je een fee twee keer of nul keer rekent.
 */
final readonly class SellerProceeds
{
    public function __construct(
        public int $hammerCents,
        public float $feePercentage,
        public int $feeCents,
        public int $payoutCents,
    ) {}

    /**
     * @return array<int, CostLine>
     */
    public function lines(): array
    {
        return [
            new CostLine('hammer', __('money.lines.hammer'), $this->hammerCents),
            new CostLine(
                key: 'fee',
                label: __('money.lines.fee', ['rate' => $this->formattedFeePercentage()]),
                amountCents: -$this->feeCents,
            ),
            new CostLine('payout', __('money.lines.payout'), $this->payoutCents),
        ];
    }

    public function formattedFeePercentage(): string
    {
        return rtrim(rtrim(number_format($this->feePercentage, 2, ',', ''), '0'), ',');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hammer_cents' => $this->hammerCents,
            'fee_percentage' => $this->feePercentage,
            'fee_cents' => $this->feeCents,
            'payout_cents' => $this->payoutCents,
            'payout_formatted' => Money::format($this->payoutCents),
        ];
    }
}
