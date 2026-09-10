<?php

declare(strict_types=1);

namespace App\Auctions\Events;

use App\Models\Auction;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Een bod in de slotfase heeft de veiling verlengd.
 *
 * Dit is een verkoopargument, geen randgeval: de UI laat de klok zichtbaar
 * verspringen, telt "2x verlengd" op en zegt in één regel waaróm. Verstop het
 * niet (hfst. 13.3).
 */
final class AuctionExtended implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Auction $auction,
        public readonly CarbonImmutable $previousEndsAt,
        public readonly int $extensionCount,
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
        return 'auction.extended';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'sequence' => $this->auction->last_sequence,
            'previous_ends_at' => $this->previousEndsAt->toIso8601String(),
            'ends_at' => $this->auction->ends_at->toIso8601String(),
            'extension_count' => $this->extensionCount,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
