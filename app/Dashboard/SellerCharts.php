<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\OrderStatus;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * De twee grafieken op het verkopersdashboard uit het ontwerp van november:
 * de opbrengst per maand en de biedingen per uur van de laatste 24 uur.
 * Alleen sommen en tellingen uit de eigen tabellen, in de weergavetijdzone.
 *
 * @phpstan-type Month array{key: string, label: string, revenue_cents: int}
 */
final class SellerCharts
{
    /**
     * @return array{months: list<Month>, revenue_cents: int, peak_cents: int, trend: int|null}
     */
    public function revenueByMonth(User $user, int $months = 12): array
    {
        $timezone = config('aucvy.display_timezone');
        $start = CarbonImmutable::now($timezone)->startOfMonth()->subMonths($months - 1);
        $labels = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'];

        $buckets = [];

        for ($index = 0; $index < $months; $index++) {
            $month = $start->addMonths($index);
            $buckets[$month->format('Y-m')] = ['key' => $month->format('Y-m'), 'label' => $labels[(int) $month->format('n') - 1], 'revenue_cents' => 0];
        }

        Order::query()
            ->whereIn('seller_id', $user->financeSellerIds())
            ->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', $start->utc())
            ->get(['hammer_cents', 'completed_at'])
            ->each(function (Order $order) use (&$buckets, $timezone): void {
                $key = $order->completed_at?->timezone($timezone)->format('Y-m');

                if ($key !== null && isset($buckets[$key])) {
                    $buckets[$key]['revenue_cents'] += $order->hammer_cents;
                }
            });

        $list = array_values($buckets);
        $last = $list[count($list) - 1]['revenue_cents'];
        $previous = $list[count($list) - 2]['revenue_cents'] ?? 0;

        return [
            'months' => $list,
            'revenue_cents' => array_sum(array_column($list, 'revenue_cents')),
            'peak_cents' => max(1, max(array_column($list, 'revenue_cents'))),
            'trend' => $previous > 0 ? (int) round(($last - $previous) / $previous * 100) : null,
        ];
    }

    /**
     * @return array{hours: list<array{label: string, count: int}>, total: int, peak_index: int, peak_count: int, peak_label: string}
     */
    public function bidsPerHour(User $user): array
    {
        $timezone = config('aucvy.display_timezone');
        $now = CarbonImmutable::now($timezone)->startOfHour();
        $first = $now->subHours(23);

        $hours = [];

        for ($index = 0; $index < 24; $index++) {
            $hours[] = ['label' => $first->addHours($index)->format('H:i'), 'count' => 0];
        }

        Bid::query()
            ->whereHas('auction.lot', fn ($query) => $query->whereIn('seller_id', $user->lotSellerIds()))
            ->where('created_at', '>=', $first->utc())
            ->get(['created_at'])
            ->each(function (Bid $bid) use (&$hours, $first, $timezone): void {
                $index = (int) $first->diffInHours($bid->created_at->timezone($timezone), absolute: true);

                if (isset($hours[$index])) {
                    $hours[$index]['count']++;
                }
            });

        $counts = array_column($hours, 'count');
        $peak = max($counts);
        $peakIndex = $peak > 0 ? (int) array_search($peak, $counts, true) : 23;

        return [
            'hours' => $hours,
            'total' => array_sum($counts),
            'peak_index' => $peakIndex,
            'peak_count' => $peak,
            'peak_label' => $hours[$peakIndex]['label'],
        ];
    }
}
