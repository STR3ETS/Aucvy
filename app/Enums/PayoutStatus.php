<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De houdtermijn is een kolom (`payouts.hold_until`) en een scheduled job die
 * vrijgeeft - geen handmatige handeling. De zekerheidspagina belooft die
 * termijn; het systeem dwingt hem af (hfst. 9).
 */
enum PayoutStatus: string
{
    use HasLabel;

    case Scheduled = 'scheduled';
    case Held = 'held';
    case Released = 'released';
    case Paid = 'paid';
    case Failed = 'failed';
}
