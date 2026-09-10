<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuctionEventType;
use App\Models\Auction;
use App\Models\AuctionEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuctionEvent>
 */
class AuctionEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'sequence' => 1,
            'type' => AuctionEventType::BidPlaced,
            'payload' => [],
            'created_at' => now(),
        ];
    }
}
