<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De append-only gebeurtenissenstroom per veiling. Dit is de bron voor replay
 * na een netwerkdip én voor het auditlog (hfst. 5).
 */
enum AuctionEventType: string
{
    use HasLabel;

    case BidPlaced = 'bid_placed';
    case Outbid = 'outbid';
    case Extended = 'extended';
    case PriceDropped = 'price_dropped';
    case BuyNowDisabled = 'buy_now_disabled';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
