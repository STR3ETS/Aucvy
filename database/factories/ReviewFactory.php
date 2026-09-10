<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Alleen een afgeronde order geeft recht op een review.
            'order_id' => Order::factory()->completed(),
            'reviewer_id' => User::factory(),
            'seller_id' => User::factory(),
            'rating' => fake()->numberBetween(4, 5),
            'body' => fake()->sentence(12),
            'published_at' => now(),
        ];
    }

    /**
     * Kritische reviews blijven staan en horen dus ook in de testdata.
     */
    public function critical(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => fake()->numberBetween(1, 3),
            'body' => 'Ophalen liep een week uit door miscommunicatie.',
        ]);
    }
}
