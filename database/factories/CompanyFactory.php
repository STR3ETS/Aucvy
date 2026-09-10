<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompanyPlan;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'kvk_number' => fake()->unique()->numerify('########'),
            'vat_number' => 'NL'.fake()->numerify('#########').'B01',
            'plan' => CompanyPlan::Start,
            'fee_percentage' => CompanyPlan::Start->defaultFeePercentage(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => now(),
            'ubo_verified_at' => now(),
            'payout_iban_last4' => fake()->numerify('####'),
            'payout_iban_name' => $attributes['name'] ?? fake()->company(),
        ]);
    }

    public function onPlan(CompanyPlan $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => $plan,
            'fee_percentage' => $plan->defaultFeePercentage(),
        ]);
    }
}
