<?php

declare(strict_types=1);

namespace App\Auctions\Jobs;

use App\Auctions\Actions\CloseAuction;
use App\Models\Auction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sluit een veiling op zijn eindtijd.
 *
 * Wordt ingepland bij publicatie én opnieuw bij elke verlenging. Omdat een
 * ingeplande job niet weet dat de eindtijd intussen is opgeschoven, controleert
 * hij dat zelf en plant zich zo nodig opnieuw in (hfst. 6).
 *
 * Draait op de `realtime`-wachtrij: een veiling die dichtgaat mag nooit achter
 * een AI-taak van veertig seconden in de rij staan.
 */
final class CloseAuctionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $auctionId)
    {
        $this->onQueue('realtime');
    }

    public function handle(CloseAuction $closeAuction): void
    {
        $auction = Auction::find($this->auctionId);

        if ($auction === null || $auction->isClosed()) {
            return;
        }

        if ($auction->ends_at->isFuture()) {
            // Verlengd sinds het inplannen: opnieuw in de agenda zetten.
            self::dispatch($this->auctionId)->delay($auction->ends_at);

            return;
        }

        $closeAuction->handle($auction);
    }
}
