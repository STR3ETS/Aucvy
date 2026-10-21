<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\AiRunStatus;
use App\Models\AiRun;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;

/**
 * "Dit heeft het team al gedaan" (hfst. 13.5) - geteld uit wat er écht
 * gebeurde, de afgelopen dertig dagen. Geen verzonnen aantallen: elke regel
 * is een query op ai_runs, documenten, orders of payouts. Een teller op nul
 * verschijnt niet - een leeg schouderklopje is erger dan geen.
 *
 * @phpstan-type Activity array{agent: string, text: string}
 */
final class TeamActivity
{
    /**
     * @return list<array{agent: string, text: string}>
     */
    public function forSeller(User $user): array
    {
        $since = now()->subDays(30);
        $lotIds = $user->lots()->select('id');

        $runs = fn (string $task): int => AiRun::query()
            ->where('task', $task)
            ->where('status', AiRunStatus::Completed)
            ->where('subject_type', Lot::class)
            ->whereIn('subject_id', $lotIds)
            ->where('created_at', '>=', $since)
            ->count();

        $counts = [
            ['agent' => 'ivy', 'count' => $runs('recognise_lot'), 'key' => 'recognised'],
            ['agent' => 'nova', 'count' => $runs('draft_listing'), 'key' => 'listings'],
            ['agent' => 'atlas', 'count' => $runs('estimate_value'), 'key' => 'estimates'],
            ['agent' => 'lex', 'count' => LotDocument::query()
                ->whereIn('lot_id', $lotIds)
                ->where('created_at', '>=', $since)
                ->count(), 'key' => 'documents'],
            ['agent' => 'flow', 'count' => Order::query()
                ->where('seller_id', $user->id)
                ->where('created_at', '>=', $since)
                ->count(), 'key' => 'orders'],
            ['agent' => 'finn', 'count' => Payout::query()
                ->where('seller_id', $user->id)
                ->where('created_at', '>=', $since)
                ->count(), 'key' => 'payouts'],
        ];

        return collect($counts)
            ->filter(fn (array $activity): bool => $activity['count'] > 0)
            ->map(fn (array $activity): array => [
                'agent' => $activity['agent'],
                'text' => trans_choice('dashboard.done.'.$activity['key'], $activity['count'], ['count' => $activity['count']]),
            ])
            ->values()
            ->all();
    }
}
