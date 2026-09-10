<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Borg vóór het bieden boven een drempelbedrag. Vrijgeven gebeurt automatisch
 * bij verliezen; bij winnen wordt de borg verrekend met de order (hfst. 9).
 */
enum DepositStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Authorised = 'authorised';
    case Released = 'released';
    case Captured = 'captured';
    case Failed = 'failed';

    /**
     * Alleen een geslaagde reservering geeft toegang tot het biedpaneel.
     */
    public function coversBidding(): bool
    {
        return $this === self::Authorised;
    }
}
