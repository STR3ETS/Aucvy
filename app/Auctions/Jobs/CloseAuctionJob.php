<?php

declare(strict_types=1);

namespace App\Auctions\Jobs;

use App\Auctions\Actions\CloseAuction;
use App\Models\Auction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;

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

        if ($auction === null || $auction->isClosed() || $auction->paused_at !== null) {
            return;
        }

        if ($auction->ends_at->isFuture()) {
            /*
             | Verlengd sinds het inplannen: opnieuw in de agenda zetten. Draait
             | de job synchroon (geen echte wachtrij), dan zou dat meteen weer
             | hier uitkomen, eindeloos; dan laat hij het aan het vangnet
             | auctions:close-overdue.
             */
            if (! $this->job instanceof SyncJob) {
                self::dispatch($this->auctionId)->delay($auction->ends_at);
            }

            return;
        }

        $closeAuction->handle($auction, onlyIfDue: true);

        // Werd er verlengd terwijl de sluiting op de lock wachtte, dan is er
        // niets gesloten: opnieuw in de agenda op de nieuwe eindtijd.
        $auction->refresh();

        if (! $auction->isClosed() && $auction->paused_at === null && $auction->ends_at->isFuture() && ! $this->job instanceof SyncJob) {
            self::dispatch($this->auctionId)->delay($auction->ends_at);
        }
    }
}
