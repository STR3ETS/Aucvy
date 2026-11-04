<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auctions\Events\AuctionClosed;
use App\Auctions\Events\BidPlaced;
use App\Auctions\Events\Outbid;
use App\Ivy\Alerts\AlertRules;
use Throwable;

/**
 * Brug van de veilingevents naar de alertregels; een alert mag nooit een
 * bod of sluiting laten mislukken.
 */
final class RaiseIvyAlerts
{
    public function __construct(private readonly AlertRules $rules) {}

    public function handleOutbid(Outbid $event): void
    {
        $this->safely(fn () => $this->rules->handleOutbid($event));
    }

    public function handleBidPlaced(BidPlaced $event): void
    {
        $this->safely(fn () => $this->rules->handleBidPlaced($event));
    }

    public function handleAuctionClosed(AuctionClosed $event): void
    {
        $this->safely(fn () => $this->rules->handleAuctionClosed($event));
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
