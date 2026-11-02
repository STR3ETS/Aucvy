<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Enums\VatRegime;
use App\Models\Category;
use App\Models\Location;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publicId = (string) fake()->unique()->numberBetween(1000, 999_999);
        $title = Str::ucfirst(fake()->words(4, true));

        return [
            'public_id' => $publicId,
            'seller_id' => User::factory(),
            'category_id' => Category::factory(),
            'location_id' => Location::factory(),

            'title' => $title,
            // De slug bevat het public_id, zodat een titelwijziging de URL
            // niet breekt (hfst. 5).
            'slug' => Str::slug($title).'-'.$publicId,
            'description' => fake()->paragraph(),

            'brand' => fake()->company(),
            'model' => Str::upper(fake()->bothify('??-###')),
            'year' => fake()->numberBetween(2005, 2024),
            'quantity' => 1,
            'unit' => 'stuk',

            'condition' => LotCondition::Good,
            'specifications' => [],

            'status' => LotStatus::Draft,
            'sale_type' => SaleType::Auction,
            'vat_regime' => VatRegime::Margin,

            'completeness_score' => fake()->numberBetween(40, 95),
            'pickup_only' => true,
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LotStatus::Live,
            'published_at' => now()->subHours(2),
        ]);
    }

    public function ofType(SaleType $saleType): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_type' => $saleType,
        ]);
    }

    /**
     * Met een Ivy-waarderange. De drie velden horen altijd samen - een losse
     * ondergrens leest als een belofte (hfst. 5).
     */
    public function withValueEstimate(
        int $minCents = 1_500_000,
        int $maxCents = 1_950_000,
        ValueConfidence $confidence = ValueConfidence::Medium,
    ): static {
        return $this->state(fn (array $attributes) => [
            'value_min_cents' => $minCents,
            'value_max_cents' => $maxCents,
            'value_confidence' => $confidence,
        ]);
    }

    /**
     * Een concept uit de funnel dat nog aan een cookie-token hangt en dus nog
     * geen eigenaar heeft (hfst. 13.4).
     */
    public function anonymousDraft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LotStatus::Draft,
            'draft_token' => Str::random(48),
        ]);
    }
}
