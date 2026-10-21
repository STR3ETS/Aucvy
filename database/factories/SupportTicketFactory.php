<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'order_number' => null,
            'status' => SupportTicket::OPEN,
            'ip' => fake()->ipv4(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SupportTicket::CLOSED,
            'closed_at' => now(),
        ]);
    }
}
