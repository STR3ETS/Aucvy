<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Models\Auction;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->verified(),
            'auction_id' => Auction::factory(),
            // Groot genoeg voor elke borgtrede (N6).
            'amount_cents' => 100_000,
            'provider' => 'mollie',
            'provider_id' => 'tr_'.fake()->bothify('??????????'),
            'status' => DepositStatus::Pending,
        ];
    }

    /**
     * Een geslaagde reservering: hiermee mag er geboden worden boven de
     * drempel.
     */
    public function authorised(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DepositStatus::Authorised,
        ]);
    }

    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DepositStatus::Released,
            'released_at' => now(),
        ]);
    }
}
