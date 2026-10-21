<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Waar een veiling (Auction Studio) staat. Afgeleid van de datums en de
 * kavels, niet opgeslagen: zo kan hij nooit achterlopen.
 */
enum SaleState: string
{
    use HasLabel;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function isPublic(): bool
    {
        return in_array($this, [self::Scheduled, self::Live, self::Closed], true);
    }
}
