<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

/**
 * "Wat doet de vraag straks?" - acht gemeten weken, drie voorspelde
 * (hfst. 13.5).
 *
 * De voorspelling blijft een bandbreedte met een zichtbare grens tussen meten
 * en schatten - nooit één getal. En hij is deterministisch PHP: een
 * voortschrijdend gemiddelde met een marge uit de spreiding van de metingen
 * zelf. Geen AI in het rekenpad; Ivy mag er hoogstens iets bíj zeggen.
 */
final class DemandForecast
{
    private const MEASURED_WEEKS = 8;

    private const PREDICTED_WEEKS = 3;

    /**
     * @return array{
     *     weeks: list<array{label: string, measured: bool, low_cents: int, high_cents: int}>,
     *     has_history: bool,
     * }
     */
    public function forSeller(User $user): array
    {
        $start = now()->startOfWeek();
        $weeks = [];

        foreach (range(self::MEASURED_WEEKS - 1, 0) as $weeksAgo) {
            $weekStart = $start->subWeeks($weeksAgo);

            $cents = (int) Order::query()
                ->where('seller_id', $user->id)
                ->whereIn('status', [
                    OrderStatus::Paid, OrderStatus::ReadyForPickup, OrderStatus::Shipped,
                    OrderStatus::Delivered, OrderStatus::Completed,
                ])
                ->whereBetween('paid_at', [$weekStart, $weekStart->addWeek()])
                ->sum('hammer_cents');

            $weeks[] = [
                'label' => $weekStart->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM'),
                'measured' => true,
                'low_cents' => $cents,
                'high_cents' => $cents,
            ];
        }

        $measured = array_column($weeks, 'low_cents');
        $average = intdiv((int) array_sum($measured), max(1, count($measured)));

        /*
         | De marge komt uit de spreiding van de metingen zelf: hoe grilliger
         | de afgelopen weken, hoe breder de band. Minimaal ±30% - een strakke
         | band op vier datapunten zou zekerheid veinzen die er niet is.
         */
        $spread = max($average - min($measured), max($measured) - $average, intdiv($average * 3, 10));

        foreach (range(1, self::PREDICTED_WEEKS) as $ahead) {
            $weeks[] = [
                'label' => $start->addWeeks($ahead)->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM'),
                'measured' => false,
                'low_cents' => max(0, $average - $spread),
                'high_cents' => $average + $spread,
            ];
        }

        return [
            'weeks' => $weeks,
            'has_history' => array_sum($measured) > 0,
        ];
    }
}
