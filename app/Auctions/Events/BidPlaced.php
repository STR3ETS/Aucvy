<?php

declare(strict_types=1);

namespace App\Auctions\Events;

use App\Models\Auction;
use App\Models\Bid;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Wordt uitsluitend ná de commit verstuurd.
 *
 * Uitzenden binnen de transactie stuurt een stand rond die nog kan terugrollen
 * - en dan biedt iemand op informatie die nooit heeft bestaan (hfst. 6).
 */
final class BidPlaced implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Auction $auction,
        public readonly Bid $bid,
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
        return 'bid.placed';
    }

    /**
     * Wat er over de lijn gaat.
     *
     * Nooit een volledige naam, nooit een e-mailadres, nooit een maximumbod.
     * Precies de velden uit hoofdstuk 7 en geen enkel veld meer: elk extra veld
     * hier is een veld dat je nooit meer terugkrijgt zodra het ooit is
     * uitgezonden.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'sequence' => $this->bid->sequence,
            'amount_cents' => $this->bid->amount_cents,
            'bidder_label' => $this->bid->user->publicName(),
            'type' => $this->bid->type->value,
            'is_automatic' => $this->bid->type->isAutomatic(),
            'ends_at' => $this->auction->ends_at->toIso8601String(),
            'bid_count' => $this->auction->bid_count,
            'bidder_count' => $this->auction->bidder_count,
        ];
    }

    /**
     * De biedmotor draait op zijn eigen wachtrij. Een uitzending mag nooit
     * achter een AI-taak van veertig seconden in de rij komen te staan
     * (hfst. 3).
     */
    public function broadcastQueue(): string
    {
        return 'realtime';
    }
}
