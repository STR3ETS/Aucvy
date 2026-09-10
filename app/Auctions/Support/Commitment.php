<?php

declare(strict_types=1);

namespace App\Auctions\Support;

/**
 * Wat één deelnemer maximaal over heeft voor de kavel.
 *
 * Voor een automatisch bod is dat het ingestelde plafond; voor een staand
 * handmatig bod is het het bedrag zelf. `placedOrder` dient uitsluitend als
 * tie-break: bij gelijke bedragen wint de vroegste inzending.
 */
final readonly class Commitment
{
    public function __construct(
        public int $userId,
        public int $amountCents,
        public int $placedOrder,
    ) {}
}
