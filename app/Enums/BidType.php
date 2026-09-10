<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum BidType: string
{
    use HasLabel;

    case Manual = 'manual';
    case Proxy = 'proxy';
    case BuyNow = 'buy_now';
    case Dutch = 'dutch';

    /**
     * De biedhistorie in de UI toont "· automatisch" achter een proxy-bod.
     * Dat moet kloppen - het is het verschil tussen "iemand bood" en "een
     * ingesteld maximum stapte mee" (hfst. 6).
     */
    public function isAutomatic(): bool
    {
        return $this === self::Proxy;
    }
}
