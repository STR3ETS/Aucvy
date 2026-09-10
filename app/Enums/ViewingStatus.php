<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Bezichtigingen. Het adres gaat pas per mail ná bevestiging - nooit op de
 * kavelpagina (hfst. 7).
 */
enum ViewingStatus: string
{
    use HasLabel;

    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function revealsAddress(): bool
    {
        return in_array($this, [self::Confirmed, self::Completed], true);
    }
}
