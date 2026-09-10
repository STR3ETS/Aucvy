<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use App\Models\Bid;
use Carbon\CarbonImmutable;

/**
 * Wat er uit één biedronde is gerold, en wat er ná de commit verstuurd moet
 * worden.
 */
final readonly class WriteResult
{
    /**
     * @param  array<int, Bid>  $bids  in schrijfvolgorde; de eerste is die van de uitdager
     * @param  array<int, int>  $outbidUserIds  wie een bericht moet krijgen
     */
    public function __construct(
        public array $bids,
        public ?CarbonImmutable $previousEndsAt,
        public array $outbidUserIds,
        public bool $buyNowDisabled = false,
    ) {}

    public function challengerBid(): Bid
    {
        return $this->bids[0];
    }

    public function wasExtended(): bool
    {
        return $this->previousEndsAt !== null;
    }
}
