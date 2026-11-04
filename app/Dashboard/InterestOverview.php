<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\LotStatus;
use App\Lots\Interest\Interest;
use App\Models\AiInsight;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Interesse per kavel" en de lijst met actieve kavels op het
 * verkopersdashboard: de live kavels met hun gemeten interesse, de kavel
 * met de meeste kijkers bovenaan, en de concepten die nog af moeten.
 *
 * @phpstan-type InterestRow array{lot: Lot, stats: array{viewers: int, views: int, saved: int, shared: int, documents: int, bidders: int, sources: array<string, int>, score: int, hot: bool, window_hours: int}, tip: string|null}
 */
final class InterestOverview
{
    /**
     * @param  Collection<int, AiInsight>  $weekly
     * @return list<InterestRow>
     */
    public function forSeller(User $user, Collection $weekly, int $limit = 3): array
    {
        return $this->liveLots($user)
            ->map(fn (Lot $lot): array => ['lot' => $lot, 'stats' => Interest::forSeller($lot), 'tip' => $this->tipFor($lot, $weekly)])
            ->sortByDesc(fn (array $row): int => $row['stats']['viewers'] * 10 + $row['stats']['bidders'])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Lot>
     */
    public function liveLots(User $user, int $limit = 6): Collection
    {
        return Lot::query()
            ->whereIn('seller_id', $user->lotSellerIds())
            ->whereIn('status', [LotStatus::Live, LotStatus::Closing])
            ->with(['auction', 'media'])
            ->orderByDesc('is_hot')
            ->orderByDesc('interest_score')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Lot>
     */
    public function drafts(User $user, int $limit = 3): Collection
    {
        return Lot::query()
            ->whereIn('seller_id', $user->lotSellerIds())
            ->where('status', LotStatus::Draft)
            ->with('media')
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, AiInsight>  $weekly
     */
    private function tipFor(Lot $lot, Collection $weekly): ?string
    {
        $insight = $weekly->first(fn (AiInsight $insight): bool => ($insight->action_params['lot'] ?? null) === $lot->slug
            || str_contains((string) $insight->title, (string) $lot->title));

        return $insight?->title;
    }
}
