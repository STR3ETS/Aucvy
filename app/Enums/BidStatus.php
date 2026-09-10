<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Het enige veld op `bids` dat na aanmaken nog wijzigt. Een bod wordt nooit
 * verwijderd en de rest van de rij nooit aangepast (hfst. 5).
 */
enum BidStatus: string
{
    use HasLabel;

    case Winning = 'winning';
    case Outbid = 'outbid';
    case Rejected = 'rejected';
}
