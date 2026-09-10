<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Betaalstatus is nooit af te leiden uit de redirect van de provider - alleen
 * uit de webhook (hfst. 14).
 */
enum PaymentStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
