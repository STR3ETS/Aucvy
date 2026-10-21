<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompanyInvitation>
 */
class CompanyInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->verified(),
            'invited_by' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => CompanyRole::Editor,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(CompanyInvitation::VALID_DAYS),
            'accepted_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subDay()]);
    }
}
