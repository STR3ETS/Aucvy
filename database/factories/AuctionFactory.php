<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\Lot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auction>
 */
class AuctionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endsAt = now()->addDays(3);

        return [
            'lot_id' => Lot::factory()->live(),

            'starts_at' => now()->subHours(2),
            'ends_at' => $endsAt,
            'original_ends_at' => $endsAt,

            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,

            /*
             | De staffel wordt uit config gekopieerd bij het aanmaken en daarna
             | niet meer gelezen. Verandert de config, dan verandert de staffel
             | van een lopende veiling niet mee (hfst. 5).
             */
            'increment_table' => config('aucvy.auction.increment_table'),

            'anti_snipe_window_s' => config('aucvy.auction.anti_snipe_window_seconds'),
            'anti_snipe_extend_s' => config('aucvy.auction.anti_snipe_extend_seconds'),
        ];
    }

    /**
     * Een veiling die over `$seconds` sluit. Gebruik dit voor de
     * anti-snipingtests: `endingIn(5)` zet je precies in de slotfase.
     */
    public function endingIn(int $seconds): static
    {
        return $this->state(function (array $attributes) use ($seconds) {
            $endsAt = now()->addSeconds($seconds);

            return [
                'ends_at' => $endsAt,
                'original_ends_at' => $endsAt,
            ];
        });
    }

    public function withReserve(int $reserveCents): static
    {
        return $this->state(fn (array $attributes) => [
            'reserve_price_cents' => $reserveCents,
        ]);
    }

    public function withBuyNow(int $priceCents): static
    {
        return $this->state(fn (array $attributes) => [
            'buy_now_price_cents' => $priceCents,
            'buy_now_active' => true,
        ]);
    }

    /**
     * Een afslag. De prijs is een functie van de tijd, dus er is geen
     * `current_price_cents` die bijgehouden wordt - alleen de parameters.
     */
    public function dutch(
        int $startCents = 2_000_000,
        int $floorCents = 500_000,
        int $stepCents = 25_000,
        int $intervalSeconds = 20,
    ): static {
        return $this->state(fn (array $attributes) => [
            // De verkoopvorm staat op de kavel; zonder dit zou de motor een
            // afslag als gewone veiling behandelen.
            'lot_id' => Lot::factory()->live()->ofType(SaleType::Dutch),
            'dutch_start_cents' => $startCents,
            'dutch_floor_cents' => $floorCents,
            'dutch_step_cents' => $stepCents,
            'dutch_interval_s' => $intervalSeconds,
            'current_price_cents' => $startCents,
            'start_price_cents' => $startCents,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->subHour(),
            'original_ends_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(59),
        ]);
    }
}
