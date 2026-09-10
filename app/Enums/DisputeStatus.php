<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De geschilroute met de termijnen die de zekerheidspagina belooft:
 * melding binnen 48u na levering -> verkoper reageert (72u) -> een medewerker
 * beoordeelt, geen algoritme -> uitkomst met motivering. De uitbetaling staat
 * de hele route stil (hfst. 9).
 */
enum DisputeStatus: string
{
    use HasLabel;

    case Reported = 'reported';
    case SellerResponding = 'seller_responding';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Rejected], true);
    }
}
