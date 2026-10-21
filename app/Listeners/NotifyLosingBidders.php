<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auctions\Events\AuctionClosed;
use App\Enums\BidStatus;
use App\Models\User;
use App\Notifications\AuctionLost;

/**
 * Na de hamerslag krijgt elke bieder die niet won één mail (briefing
 * sectie 8: e-mailtemplate "verloren"). De winnaar hoort het al via de
 * order; zonder winnaar (reserve niet gehaald) valt er niets te verliezen
 * en gaat er niets uit.
 */
final class NotifyLosingBidders
{
    public function handle(AuctionClosed $event): void
    {
        if (! $event->hasWinner()) {
            return;
        }

        $auction = $event->auction;

        User::query()
            ->whereIn('id', $auction->bids()
                ->where('status', '!=', BidStatus::Rejected)
                ->where('user_id', '!=', $auction->winner_id)
                ->select('user_id'))
            ->get()
            ->each(fn (User $bidder) => $bidder->notify(new AuctionLost($auction)));
    }
}
