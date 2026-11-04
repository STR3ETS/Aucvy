<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auctions\Events\AuctionClosed;
use App\Jobs\WithdrawLotFromChannels;

/**
 * Bij sluiting (verkocht of niet) gaan de advertenties offline en vervalt
 * de rest van de social planning.
 */
final class WithdrawChannelsOnClose
{
    public function handle(AuctionClosed $event): void
    {
        WithdrawLotFromChannels::dispatch($event->auction->lot_id)->afterCommit();
    }
}
