<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Auctions\Support\BidGuards;
use App\Enums\BidStatus;
use App\Enums\OrderStatus;
use App\Lots\Interest\Interest;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Het koperoverzicht uit het ontwerp van november: de lopende biedingen
 * met de waarde-indicatie van Atlas, wat je volgt, wat je kocht en wat er
 * nog te betalen is. Geen AI-calls; de waarde-indicatie staat al op de
 * kavel.
 *
 * @phpstan-type OpenBid array{bid: Bid, current_cents: int, minimum_cents: int, winning: bool, interest: array{viewers: int, saved: int, shared: int, bidders: int, now: int, hot: bool}}
 */
final class BuyerOverview
{
    public function __construct(private BidGuards $guards) {}

    /**
     * @return list<OpenBid>
     */
    public function openBids(User $user, int $limit = 4): array
    {
        return Bid::query()
            ->where('user_id', $user->id)
            ->whereIn('id', fn (Builder $query) => $query
                ->selectRaw('max(id)')
                ->from('bids')
                ->where('user_id', $user->id)
                ->groupBy('auction_id'))
            ->with(['auction.lot.media'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->filter(fn (Bid $bid): bool => $bid->auction !== null && $bid->auction->lot !== null && ! $bid->auction->isClosed())
            // Waar je overboden bent staat bovenaan; daarbinnen wat het eerst sluit.
            ->sortBy([
                fn (Bid $a, Bid $b): int => ($a->status === BidStatus::Winning) <=> ($b->status === BidStatus::Winning),
                fn (Bid $a, Bid $b): int => $a->auction->ends_at->getTimestamp() <=> $b->auction->ends_at->getTimestamp(),
            ])
            ->take($limit)
            ->map(fn (Bid $bid): array => [
                'bid' => $bid,
                'current_cents' => $bid->auction->lot->currentPriceCents(),
                'minimum_cents' => $this->guards->minimumBid($bid->auction),
                'winning' => $bid->status === BidStatus::Winning,
                'interest' => Interest::forBuyer($bid->auction->lot),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Order>
     */
    public function purchases(User $user, int $limit = 5): Collection
    {
        return Order::query()
            ->where('buyer_id', $user->id)
            ->whereNot('status', OrderStatus::Cancelled)
            ->with(['lot.media', 'invoices', 'disputes'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{total_cents: int, count: int, deadline: CarbonInterface|null, order: Order|null}
     */
    public function toPay(User $user): array
    {
        $orders = Order::query()
            ->where('buyer_id', $user->id)
            ->where('status', OrderStatus::AwaitingPayment)
            ->orderBy('payment_deadline_at')
            ->get();

        return [
            'total_cents' => (int) $orders->sum('total_cents'),
            'count' => $orders->count(),
            'deadline' => $orders->first()?->payment_deadline_at,
            'order' => $orders->first(),
        ];
    }
}
