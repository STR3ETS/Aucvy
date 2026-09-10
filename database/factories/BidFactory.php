<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BidStatus;
use App\Enums\BidType;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Alleen voor het opzetten van testdata en fixtures.
 *
 * In productiecode ontstaat een bod uitsluitend via
 * App\Auctions\Actions\PlaceBid - niet via deze factory (hfst. 3).
 *
 * @extends Factory<Bid>
 */
class BidFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'user_id' => User::factory()->verified(),
            'amount_cents' => 100_000,
            'type' => BidType::Manual,
            'status' => BidStatus::Winning,
            'sequence' => 1,
            'idempotency_key' => (string) Str::uuid(),
            'placed_at' => now(),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function outbid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BidStatus::Outbid,
        ]);
    }

    public function automatic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BidType::Proxy,
        ]);
    }
}
