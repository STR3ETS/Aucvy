<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\VatRegime;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\PlatformFacts;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hammer = 1_285_000;
        $transport = 34_000;

        return [
            'order_number' => 'AUC-'.fake()->unique()->numerify('########'),
            'lot_id' => Lot::factory(),
            'buyer_id' => User::factory()->verified(),
            'seller_id' => User::factory()->verified()->business(),

            'hammer_cents' => $hammer,
            // Nul in de MVP. Het veld bestaat, de waarde is nul.
            'buyer_premium_cents' => 0,
            'transport_cents' => $transport,
            // Margeregeling: geen verrekenbare btw voor de koper.
            // De administratiekosten per order (N4) met btw, zoals Money::forOrder ze rekent.
            'admin_fee_cents' => PlatformFacts::adminFeeCents(),
            'vat_cents' => Money::percentageOf(PlatformFacts::adminFeeCents(), Money::vatRatePercent()),
            'total_cents' => $hammer + $transport + PlatformFacts::adminFeeCents() + Money::percentageOf(PlatformFacts::adminFeeCents(), Money::vatRatePercent()),
            'vat_regime' => VatRegime::Margin,

            'status' => OrderStatus::AwaitingPayment,
            'payment_deadline_at' => now()->addHours(config('aucvy.orders.payment_deadline_hours')),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Delivered,
            'paid_at' => now()->subDays(3),
            'delivered_at' => now(),
            'dispute_window_ends_at' => now()->addHours(config('aucvy.orders.dispute_window_hours')),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Completed,
            'paid_at' => now()->subDays(6),
            'delivered_at' => now()->subDays(3),
            'dispute_window_ends_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }
}
