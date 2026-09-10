<?php

declare(strict_types=1);

namespace App\Auctions\Events;

use App\Models\Auction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Koop-direct is vervallen doordat er tot aan of boven de vaste prijs geboden
 * is.
 *
 * Een eigen bericht, zodat elke open pagina de knop ziet verdwijnen mét uitleg.
 * Zou de client dit pas bij het klikken merken, dan krijgt hij een fout op het
 * moment dat hij dacht te kopen (hfst. 6).
 */
final class BuyNowDisabled implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Auction $auction,
        public readonly int $buyNowPriceCents,
        public readonly int $currentPriceCents,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('auction.'.$this->auction->id)];
    }

    public function broadcastAs(): string
    {
        return 'buynow.disabled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'sequence' => $this->auction->last_sequence,
            'buy_now_price_cents' => $this->buyNowPriceCents,
            'current_price_cents' => $this->currentPriceCents,
        ];
    }
}
