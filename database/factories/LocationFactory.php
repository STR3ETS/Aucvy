<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Magazijn',
            'street' => fake()->streetName(),
            'house_number' => (string) fake()->buildingNumber(),
            'postal_code' => fake()->numerify('####').fake()->lexify('??'),
            'city' => fake()->city(),
            'province' => fake()->randomElement([
                'Utrecht', 'Noord-Holland', 'Zuid-Holland', 'Gelderland',
                'Noord-Brabant', 'Overijssel', 'Limburg', 'Friesland',
            ]),
            'country' => 'NL',
        ];
    }
}
