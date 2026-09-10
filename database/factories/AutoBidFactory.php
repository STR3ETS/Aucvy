<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutoBid>
 */
class AutoBidFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auction_id' => Auction::factory(),
            'user_id' => User::factory()->verified(),
            'max_amount_cents' => 500_000,
            'active' => true,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
            'cancelled_at' => now(),
        ]);
    }
}
