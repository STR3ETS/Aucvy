<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentKind;
use App\Models\Lot;
use App\Models\LotDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LotDocument>
 */
class LotDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lot_id' => Lot::factory(),
            'kind' => DocumentKind::Invoice,
            'path' => 'documents/'.fake()->uuid().'.pdf',
            'original_filename' => 'factuur.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(50_000, 2_000_000),
        ];
    }

    public function checkedByIvy(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_checked_at' => now(),
            'ai_findings' => ['complete' => true, 'missing' => []],
        ]);
    }
}
