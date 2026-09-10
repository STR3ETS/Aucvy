<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Lot;
use App\Models\LotMedia;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LotMedia>
 */
class LotMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lot_id' => Lot::factory(),
            'type' => MediaType::Image,
            'path' => 'lots/'.fake()->uuid().'.jpg',
            'variants' => [],
            'width' => 1600,
            'height' => 1200,
            'position' => 0,
            'alt' => fake()->sentence(4),
            // Vanaf dag één vullen: dit is later de dubbele-fotodetectie.
            'checksum' => hash('sha256', Str::random(32)),
            'size_bytes' => fake()->numberBetween(200_000, 4_000_000),
        ];
    }
}
