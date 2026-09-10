<?php

declare(strict_types=1);

namespace App\Auctions\Events;

use App\Models\Auction;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * De veiling is gesloten.
 *
 * `$order` is null als er geen winnaar was: geen biedingen, of de reserveprijs
 * niet gehaald. In dat geval krijgt de verkoper de kavel terug en ontstaat er
 * geen order (hfst. 6).
 */
final class AuctionClosed implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Auction $auction,
        public readonly ?Order $order = null,
    ) {}

    public function hasWinner(): bool
    {
        return $this->order !== null;
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('auction.'.$this->auction->id)];
    }

    public function broadcastAs(): string
    {
        return 'auction.closed';
    }

    /**
     * Wie er gewonnen heeft gaat niet over dit publieke kanaal - dat is een
     * bericht voor de winnaar zelf. Publiek is alleen: het is voorbij, en
     * tegen welke prijs.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'sequence' => $this->auction->last_sequence,
            'sold' => $this->hasWinner(),
            'final_price_cents' => $this->auction->current_price_cents,
            'reserve_met' => $this->auction->reserve_met,
            'bid_count' => $this->auction->bid_count,
            'bidder_count' => $this->auction->bidder_count,
            'extension_count' => $this->auction->extension_count,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
