<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publicId = (string) fake()->unique()->numberBetween(1000, 999_999);
        $title = 'Veiling '.Str::ucfirst(fake()->words(3, true));

        return [
            'public_id' => $publicId,
            'seller_id' => User::factory(),
            'company_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.$publicId,
            'description' => fake()->paragraph(),
            'ends_at' => now()->addDays(7)->setTime(20, 0),
            'closing_gap_seconds' => 120,
            'published_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['published_at' => now()]);
    }
}
