<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use LogicException;

/**
 * De uitkomst van één biedronde: wie leidt, tegen welke prijs, en welke rijen
 * dat oplevert.
 */
final readonly class ProxyOutcome
{
    /**
     * @param  array<int, ResolvedBid>  $bids  op volgorde van schrijven
     */
    public function __construct(
        public array $bids,
        public int $leaderId,
        public int $priceCents,
        public int $challengerId,
    ) {}

    public function challengerLeads(): bool
    {
        return $this->leaderId === $this->challengerId;
    }

    /**
     * Waar of niet: er kwam een automatisch tegenbod van een andere bieder.
     * De UI zegt dan "je bent direct overboden door een automatisch bod" in
     * plaats van alleen "je bent overboden".
     */
    public function wasCounteredAutomatically(): bool
    {
        return ! $this->challengerLeads();
    }

    /**
     * De rij die de nieuwe stand bepaalt.
     */
    public function winningBid(): ResolvedBid
    {
        foreach ($this->bids as $bid) {
            if ($bid->isWinning()) {
                return $bid;
            }
        }

        // De resolver levert altijd precies één winnende rij; komen we hier,
        // dan is er iets structureel mis en willen we dat luid horen.
        throw new LogicException('Een biedronde zonder winnende rij is niet mogelijk.');
    }
}
