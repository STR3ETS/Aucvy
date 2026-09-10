<?php

declare(strict_types=1);

namespace App\Auctions\Events;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Iemand is niet langer de hoogste bieder.
 *
 * Dit gaat naar het privékanaal van de gebruiker, niet naar het publieke
 * veilingkanaal. Twee redenen: het is een persoonlijk bericht, en het publieke
 * kanaal kan het zelf afleiden - de nieuwste `bid.placed` ís de leider. Een
 * apart publiek outbid-event zou de eventstroom verdubbelen zonder dat een
 * client er iets mee kan.
 *
 * E-mail is hiervoor te laat: tegen de tijd dat iemand het leest is de veiling
 * voorbij. Web push, binnen twee seconden - en dat is een productmetriek, geen
 * technisch detail (hfst. 14).
 */
final class Outbid implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Auction $auction,
        public readonly User $user,
        public readonly int $newPriceCents,
        /** Overboden door een automatisch meestappend maximum van een ander. */
        public readonly bool $byAutomaticBid = false,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->user->id)];
    }

    public function broadcastAs(): string
    {
        return 'outbid';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'auction_id' => $this->auction->id,
            'lot_public_id' => $this->auction->lot->public_id,
            'new_price_cents' => $this->newPriceCents,
            'ends_at' => $this->auction->ends_at->toIso8601String(),
            // De UI zegt dan "je bent direct overboden door een automatisch
            // bod" in plaats van alleen "je bent overboden".
            'by_automatic_bid' => $this->byAutomaticBid,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
