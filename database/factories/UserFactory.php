<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CompanyRole;
use App\Enums\KycStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Standaard: een niet-geverifieerde particulier die nog niet mag bieden.
     *
     * Dat is bewust de zuinigste staat. Een test die wél wil bieden moet dat
     * expliciet zeggen met ->verified(), zodat je in de test terugziet dat
     * verificatie een voorwaarde is (hfst. 13.6).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Expliciet null in plaats van weglaten: een factory hoort een
            // volledig gehydrateerd model op te leveren. Zonder deze regels
            // ontbreekt het veld op de instantie, en dan struikelt elke
            // accessor die het leest over strict mode.
            'display_name' => null,
            'is_staff' => false,
            'company_id' => null,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'type' => UserType::Private,
            'phone' => null,
            'phone_verified_at' => null,
            'kyc_status' => KycStatus::Unverified,
            'kyc_verified_at' => null,
            'can_bid' => false,
            'blocked_at' => null,
            'blocked_reason' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Volledig geverifieerd en dus bevoegd om te bieden.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_status' => KycStatus::Verified,
            'kyc_verified_at' => now(),
            'can_bid' => true,
            'phone' => fake()->numerify('+316########'),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Een zakelijk account met een geverifieerd bedrijf eronder.
     */
    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => UserType::Business,
            'company_id' => Company::factory()->verified(),
            'company_role' => CompanyRole::Owner,
        ]);
    }
}
