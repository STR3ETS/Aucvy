<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum LotStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Closing = 'closing';
    case Sold = 'sold';
    case Unsold = 'unsold';
    case Cancelled = 'cancelled';

    /**
     * Zichtbaar voor het publiek. Een concept is dat nooit, ook niet via een
     * gedeelde URL.
     */
    public function isPublic(): bool
    {
        return in_array($this, [
            self::Scheduled,
            self::Live,
            self::Closing,
            self::Sold,
            self::Unsold,
        ], true);
    }

    /**
     * Er mag alleen geboden worden op een lopende veiling. `Closing` telt mee:
     * dat is de slotfase, juist het moment waarop het hardst geboden wordt.
     */
    public function acceptsBids(): bool
    {
        return in_array($this, [self::Live, self::Closing], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Sold, self::Unsold, self::Cancelled], true);
    }
}
