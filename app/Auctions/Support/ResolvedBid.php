<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use App\Enums\BidStatus;
use App\Enums\BidType;

/**
 * Eén biedrij die geschreven moet worden.
 *
 * De resolver bepaalt wélke rijen er komen; PlaceBid schrijft ze. Die scheiding
 * maakt de rekenkant testbaar zonder database en houdt het schrijfpad kort -
 * en dat pad zit binnen een rij-lock.
 */
final readonly class ResolvedBid
{
    public function __construct(
        public int $userId,
        public int $amountCents,
        public BidType $type,
        public BidStatus $status,
    ) {}

    public function isWinning(): bool
    {
        return $this->status === BidStatus::Winning;
    }
}
