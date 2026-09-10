<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Verificatie blokkeert uitsluitend het bieden. Rondkijken, zoeken, volgen en
 * een concept maken kan zonder (briefing hfst. 13.6).
 */
enum KycStatus: string
{
    use HasLabel;

    case Unverified = 'unverified';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function allowsBidding(): bool
    {
        return $this === self::Verified;
    }
}
