<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\BidStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Database\Query\Builder;

/**
 * De vier cijfertegels en de samenvattende regel bovenaan het dashboard,
 * per rol. Alleen tellingen en sommen uit de eigen tabellen: geen
 * schattingen, geen AI.
 *
 * @phpstan-type Tile array{tone: string, icon: string, label: string, value: string, sub: string, href: string}
 */
final class HeadlineStats
{
    /**
     * @return array{tiles: list<Tile>, summary: string}
     */
    public function forSeller(User $user, ?int $trend): array
    {
        $revenue = (int) Order::query()
            ->whereIn('seller_id', $user->financeSellerIds())
            ->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', now()->subDays(30))
            ->sum('hammer_cents');

        $liveLots = Lot::query()
            ->whereIn('seller_id', $user->lotSellerIds())
            ->whereIn('status', [LotStatus::Live, LotStatus::Closing]);
        $liveCount = (clone $liveLots)->count();
        // De tellers staan op de veiling; sommeren in de database in plaats
        // van elke live kavel met zijn veiling te laden.
        $bidders = (int) Auction::query()
            ->whereIn('lot_id', (clone $liveLots)->select('id'))
            ->sum('bidder_count');

        $drafts = Lot::query()
            ->whereIn('seller_id', $user->lotSellerIds())
            ->where('status', LotStatus::Draft)
            ->count();

        $pending = Payout::query()
            ->whereIn('seller_id', $user->financeSellerIds())
            ->whereIn('status', [PayoutStatus::Scheduled, PayoutStatus::Held, PayoutStatus::Released]);
        $pendingSum = (int) (clone $pending)->sum('amount_cents');
        $pendingCount = $pending->count();

        $sold = Order::query()
            ->whereIn('seller_id', $user->financeSellerIds())
            ->whereNotIn('status', [OrderStatus::AwaitingPayment, OrderStatus::Cancelled])
            ->count();

        // Een redacteur zonder financiële rol krijgt op /dashboard/uitbetalingen
        // een 403; zijn tegel wijst dan naar de bestellingen.
        $financeUrl = $user->canSeeCompanyFinance() ? route('dashboard.payouts') : route('orders.index');

        $trendText = $trend === null
            ? 'afgeronde verkopen'
            : abs($trend).'% '.($trend >= 0 ? 'hoger' : 'lager').' dan de 30 dagen ervoor';

        return [
            'tiles' => [
                $this->tile('green', 'banknote', 'Opbrengst 30 dagen', Money::format($revenue), $trendText, $financeUrl),
                $this->tile('blue', 'gavel', 'Live kavels', (string) $liveCount, $this->count($bidders, 'bieder', 'bieders').($drafts > 0 ? ' · '.$this->count($drafts, 'concept', 'concepten') : ''), route('dashboard.lots')),
                $this->tile('orange', 'clock', 'Te ontvangen', Money::format($pendingSum), $this->count($pendingCount, 'uitbetaling onderweg', 'uitbetalingen onderweg'), $financeUrl),
                $this->tile('purple', 'badge-check', 'Verkocht', (string) $sold, 'kavels met een koper', route('orders.index')),
            ],
            'summary' => sprintf(
                '%s, %s en %s te ontvangen.',
                $this->count($liveCount, 'live kavel', 'live kavels'),
                $this->count($bidders, 'bieder', 'bieders'),
                Money::format($pendingSum),
            ),
        ];
    }

    /**
     * @return array{tiles: list<Tile>, summary: string}
     */
    public function forBuyer(User $user): array
    {
        // Eén regel per veiling: je laatste eigen bod telt.
        $latest = Bid::query()
            ->where('user_id', $user->id)
            ->whereIn('id', fn (Builder $query) => $query
                ->selectRaw('max(id)')
                ->from('bids')
                ->where('user_id', $user->id)
                ->groupBy('auction_id'))
            ->with('auction')
            ->get();
        $open = $latest->filter(fn (Bid $bid): bool => $bid->auction !== null && ! $bid->auction->isClosed());
        $winning = $open->where('status', BidStatus::Winning)->count();
        $outbid = $open->where('status', BidStatus::Outbid)->count();

        $toPay = Order::query()
            ->where('buyer_id', $user->id)
            ->where('status', OrderStatus::AwaitingPayment);
        $toPaySum = (int) (clone $toPay)->sum('total_cents');
        $toPayCount = $toPay->count();

        $won = Order::query()
            ->where('buyer_id', $user->id)
            ->whereNot('status', OrderStatus::Cancelled)
            ->count();

        return [
            'tiles' => [
                $this->tile('green', 'trending-up', 'Hoogste bieder', (string) $winning, 'in lopende veilingen', route('dashboard.bids')),
                $this->tile('blue', 'gavel', 'Lopende biedingen', (string) $open->count(), $this->count($outbid, 'keer overboden', 'keer overboden'), route('dashboard.bids')),
                $this->tile('orange', 'clock', 'Te betalen', Money::format($toPaySum), $this->count($toPayCount, 'bestelling wacht op betaling', 'bestellingen wachten op betaling'), route('orders.index')),
                $this->tile('purple', 'shopping-bag', 'Gekocht', (string) $won, 'kavels gewonnen of direct gekocht', route('orders.index')),
            ],
            'summary' => sprintf(
                '%s, %s en %s te betalen.',
                $this->count($winning, 'keer hoogste bieder', 'keer hoogste bieder'),
                $this->count($outbid, 'keer overboden', 'keer overboden'),
                Money::format($toPaySum),
            ),
        ];
    }

    /**
     * @return Tile
     */
    private function tile(string $tone, string $icon, string $label, string $value, string $sub, string $href): array
    {
        return compact('tone', 'icon', 'label', 'value', 'sub', 'href');
    }

    private function count(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
