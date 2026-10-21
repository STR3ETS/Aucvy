<?php

declare(strict_types=1);

namespace App\Auctions\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * Een bod dat niet door mocht gaan.
 *
 * Draagt altijd een reden mee, en waar het over een bedrag gaat ook het bedrag
 * dat wél had gemogen - zodat het biedpaneel niet alleen kan zeggen dat het
 * misging, maar ook wat de gebruiker nu moet doen.
 */
final class BidRejected extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly BidRejectionReason $reason,
        public readonly ?int $minimumCents = null,
        public readonly ?int $currentPriceCents = null,
    ) {
        parent::__construct($reason->label());
    }

    public static function because(BidRejectionReason $reason): self
    {
        return new self($reason);
    }

    public static function tooLow(int $minimumCents, int $currentPriceCents): self
    {
        return new self(BidRejectionReason::TooLow, $minimumCents, $currentPriceCents);
    }

    public static function autoBidNotIncreasing(int $minimumCents): self
    {
        return new self(BidRejectionReason::AutoBidNotIncreasing, $minimumCents);
    }

    /**
     * De afslagprijs zakte verder dan wat de koper op zijn scherm had.
     *
     * De actuele prijs gaat mee terug, zodat de UI meteen de nieuwe knop kan
     * tonen in plaats van alleen te melden dat het misging (hfst. 7).
     */
    public static function priceMoved(int $actualPriceCents): self
    {
        return new self(BidRejectionReason::PriceMoved, currentPriceCents: $actualPriceCents);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'reason' => $this->reason->value,
            'message' => $this->reason->label(),
            'minimum_cents' => $this->minimumCents,
            'current_price_cents' => $this->currentPriceCents,
            'resolution_route' => $this->reason->resolutionRoute(),
        ], fn (mixed $value): bool => $value !== null);
    }
}
