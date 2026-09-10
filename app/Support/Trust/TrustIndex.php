<?php

declare(strict_types=1);

namespace App\Support\Trust;

use App\Enums\OrderStatus;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * De Trust Index uit preview v4: één cijfer uit gemeten signalen, met de
 * opbouw er transparant naast. De regels die het cijfer eerlijk houden:
 *
 *  1. Onder de tien afgeronde transacties tonen we níéts - nieuwe verkopers
 *     scoren laag omdat er weinig te meten valt, niet omdat ze onbetrouwbaar
 *     zijn.
 *  2. Alleen factoren die we echt meten tellen mee; de gewichten worden
 *     over de aanwezige factoren herverdeeld. Reactietijd meten we nog
 *     niet, dus die factor bestaat hier bewust niet.
 *  3. Een hoge score is geen garantie, en dat zegt de UI er ook bij.
 */
final class TrustIndex
{
    /**
     * @return array{score: int, transactions: int, factors: array<int, array{key: string, label: string, weight: int, score: int, measures: string}>}|null
     */
    public function forSeller(User $seller, ?CarbonImmutable $asOf = null): ?array
    {
        $asOf ??= CarbonImmutable::now();

        $completed = Order::query()
            ->where('seller_id', $seller->id)
            ->where('status', OrderStatus::Completed)
            ->where('created_at', '<=', $asOf)
            ->count();

        if ($completed < (int) config('aucvy.trust.minimum_transactions')) {
            return null;
        }

        $factors = array_values(array_filter([
            $this->transactions($seller, $asOf, $completed),
            $this->documents($seller, $asOf),
            $this->satisfaction($seller, $asOf),
            $this->settlement($seller, $asOf),
            $this->delivery($seller, $asOf),
        ]));

        $totalWeight = array_sum(array_column($factors, 'weight'));

        if ($totalWeight === 0) {
            return null;
        }

        $weighted = 0;
        foreach ($factors as $factor) {
            $weighted += $factor['weight'] * $factor['score'];
        }

        return [
            'score' => (int) round($weighted / $totalWeight),
            'transactions' => $completed,
            'factors' => $factors,
        ];
    }

    /**
     * Het verloop van de afgelopen twaalf maanden, telkens berekend met
     * alleen de gegevens van vóór dat moment.
     *
     * @return array<int, array{label: string, score: int|null}>
     */
    public function historyForSeller(User $seller): array
    {
        $points = [];

        for ($monthsBack = 11; $monthsBack >= 0; $monthsBack--) {
            $moment = CarbonImmutable::now()->subMonths($monthsBack)->endOfMonth();

            $points[] = [
                'label' => $moment->timezone(config('aucvy.display_timezone'))->isoFormat('MMM'),
                'score' => $this->forSeller($seller, $moment)['score'] ?? null,
            ];
        }

        return $points;
    }

    /**
     * @return array{key: string, label: string, weight: int, score: int, measures: string}|null
     */
    private function transactions(User $seller, CarbonImmutable $asOf, int $completed): ?array
    {
        $cancelled = Order::query()
            ->where('seller_id', $seller->id)
            ->where('status', OrderStatus::Cancelled)
            ->where('created_at', '<=', $asOf)
            ->count();

        if ($completed + $cancelled === 0) {
            return null;
        }

        return $this->factor(
            'transactions', 'Afgeronde transacties', 25,
            (int) round($completed / ($completed + $cancelled) * 100),
            'Hoeveel verkopen daadwerkelijk zijn afgerond.',
        );
    }

    /**
     * @return array{key: string, label: string, weight: int, score: int, measures: string}|null
     */
    private function documents(User $seller, CarbonImmutable $asOf): ?array
    {
        $lots = Lot::query()
            ->where('seller_id', $seller->id)
            ->whereIn('status', ['live', 'closing', 'sold'])
            ->where('created_at', '<=', $asOf);

        $total = (clone $lots)->count();

        if ($total === 0) {
            return null;
        }

        $documented = (clone $lots)->whereHas('documents')->count();

        return $this->factor(
            'documents', 'Documentkwaliteit', 20,
            (int) round($documented / $total * 100),
            'Of eigendomsbewijs, facturen of handleidingen zijn aangeleverd.',
        );
    }

    /**
     * @return array{key: string, label: string, weight: int, score: int, measures: string}|null
     */
    private function satisfaction(User $seller, CarbonImmutable $asOf): ?array
    {
        $average = Review::query()
            ->where('seller_id', $seller->id)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $asOf)
            ->avg('rating');

        if ($average === null) {
            return null;
        }

        return $this->factor(
            'satisfaction', 'Koperstevredenheid', 20,
            (int) round((float) $average * 20),
            'Beoordelingen van kopers die de kavel echt hebben afgenomen.',
        );
    }

    /**
     * @return array{key: string, label: string, weight: int, score: int, measures: string}|null
     */
    private function settlement(User $seller, CarbonImmutable $asOf): ?array
    {
        $orders = Order::query()
            ->where('seller_id', $seller->id)
            ->where('created_at', '<=', $asOf)
            ->count();

        if ($orders === 0) {
            return null;
        }

        $disputes = Dispute::query()
            ->whereHas('order', fn ($query) => $query->where('seller_id', $seller->id))
            ->where('created_at', '<=', $asOf)
            ->count();

        return $this->factor(
            'settlement', 'Betaal- en afhandelbetrouwbaarheid', 15,
            (int) round(max(0, 1 - $disputes / $orders) * 100),
            'Hoe vaak een verkoop zonder geschil is afgehandeld.',
        );
    }

    /**
     * @return array{key: string, label: string, weight: int, score: int, measures: string}|null
     */
    private function delivery(User $seller, CarbonImmutable $asOf): ?array
    {
        $shipped = Order::query()
            ->where('seller_id', $seller->id)
            ->where('status', OrderStatus::Completed)
            ->where('created_at', '<=', $asOf)
            ->whereNotNull('paid_at')
            ->whereNotNull('shipped_at')
            ->get(['paid_at', 'shipped_at']);

        if ($shipped->isEmpty()) {
            return null;
        }

        $onTime = $shipped
            ->filter(fn (Order $order) => $order->shipped_at->lessThanOrEqualTo($order->paid_at->addDays(7)))
            ->count();

        return $this->factor(
            'delivery', 'Leverprestatie', 12,
            (int) round($onTime / $shipped->count() * 100),
            'Of ophalen en levering gaan zoals afgesproken, binnen zeven dagen na betaling.',
        );
    }

    /**
     * @return array{key: string, label: string, weight: int, score: int, measures: string}
     */
    private function factor(string $key, string $label, int $weight, int $score, string $measures): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'weight' => $weight,
            'score' => max(0, min(100, $score)),
            'measures' => $measures,
        ];
    }
}
