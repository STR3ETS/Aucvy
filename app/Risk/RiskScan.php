<?php

declare(strict_types=1);

namespace App\Risk;

use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\Order;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Risicosignalen voor beheer (briefing sectie 7: biedmanipulatie, verdachte
 * patronen, escalatie). Alleen deterministische regels op eigen data; geen
 * score, geen AI. Elk signaal zegt wat er is gezien en waar je het nakijkt.
 * Beheer beslist, met de knoppen die er al zijn (blokkeren, afwijzen).
 *
 * @phpstan-type Signal array{key: string, severity: 'hoog'|'midden', title: string, body: string, url: string}
 */
final class RiskScan
{
    /**
     * @return Collection<int, Signal>
     */
    public function signals(int $days = 30): Collection
    {
        $since = now()->subDays($days);

        return collect()
            ->merge($this->selfBidding($since))
            ->merge($this->sharedAddress($since))
            ->merge($this->repeatNonPayers())
            ->merge($this->repeatRejectedSellers())
            ->sortBy(fn (array $signal): int => $signal['severity'] === 'hoog' ? 0 : 1)
            ->values();
    }

    /**
     * Het aantal voor de overzichtstegel, een minuut gecached: het
     * overzicht ververst elke paar seconden.
     */
    public function count(): int
    {
        return Cache::remember('risk.signals.count', 60, fn (): int => $this->signals()->count());
    }

    /**
     * Bieden op je eigen kavel, of op een kavel van je eigen bedrijf: het
     * klassieke opdrijven.
     *
     * @return list<Signal>
     */
    private function selfBidding(DateTimeInterface $since): array
    {
        return Bid::query()
            ->join('auctions', 'auctions.id', '=', 'bids.auction_id')
            ->join('lots', 'lots.id', '=', 'auctions.lot_id')
            ->join('users', 'users.id', '=', 'bids.user_id')
            ->where('bids.placed_at', '>=', $since)
            ->where(fn ($query) => $query
                ->whereColumn('bids.user_id', 'lots.seller_id')
                ->orWhere(fn ($own) => $own
                    ->whereNotNull('lots.company_id')
                    ->whereColumn('users.company_id', 'lots.company_id')))
            ->selectRaw('lots.id as lot_id, lots.title, bids.user_id, users.name, count(*) as bids')
            ->groupBy('lots.id', 'lots.title', 'bids.user_id', 'users.name')
            ->get()
            ->map(fn (object $row): array => [
                'key' => 'self_bid:'.$row->lot_id.':'.$row->user_id,
                'severity' => 'hoog',
                'title' => __('risk.self_bid', ['name' => $row->name, 'lot' => $row->title]),
                'body' => trans_choice('risk.self_bid_body', (int) $row->bids, ['count' => (int) $row->bids]),
                'url' => route('admin.users.show', $row->user_id),
            ])
            ->all();
    }

    /**
     * Een bieder vanaf hetzelfde IP-adres als de verkoper van die kavel.
     * Niet sluitend (kantoornetwerk), wel het nakijken waard.
     *
     * @return list<Signal>
     */
    private function sharedAddress(DateTimeInterface $since): array
    {
        /*
         | In SQL: de biedregel uit het auditlog (actie, periode en ip zijn
         | geïndexeerd) via het veiling-id in de payload naar de kavel, en
         | dan de auditregels van de verkoper op hetzelfde adres. Zo komt er
         | geen maand aan biedregels naar PHP, hoe druk het ook wordt.
         */
        $rows = DB::table('audit_logs as bidlog')
            ->join('auctions', fn ($join) => $join->on('auctions.id', '=', DB::raw("cast(json_unquote(json_extract(bidlog.after, '$.auction_id')) as unsigned)")))
            ->join('lots', 'lots.id', '=', 'auctions.lot_id')
            ->join('audit_logs as sellerlog', function ($join): void {
                $join->on('sellerlog.actor_id', '=', 'lots.seller_id')
                    ->on('sellerlog.ip', '=', 'bidlog.ip');
            })
            ->join('users as bidders', 'bidders.id', '=', 'bidlog.actor_id')
            ->where('bidlog.action', 'bid.placed')
            ->whereNotNull('bidlog.ip')
            ->where('bidlog.created_at', '>=', $since)
            ->where('sellerlog.created_at', '>=', now()->subDays(90))
            ->whereColumn('bidlog.actor_id', '!=', 'lots.seller_id')
            ->selectRaw('distinct lots.id as lot_id, lots.title, bidlog.actor_id as user_id, bidders.name, bidlog.ip')
            ->limit(500)
            ->get();

        $signals = [];

        foreach ($rows as $row) {
            $key = 'shared_ip:'.$row->lot_id.':'.$row->user_id;

            $signals[$key] ??= [
                'key' => $key,
                'severity' => 'midden',
                'title' => __('risk.shared_ip', ['name' => $row->name ?? '#'.$row->user_id, 'lot' => $row->title]),
                'body' => __('risk.shared_ip_body', ['ip' => $row->ip]),
                'url' => route('admin.lots.show', $row->lot_id),
            ];
        }

        return array_values($signals);
    }

    /**
     * Wie twee keer of vaker won zonder te betalen, blokkeert kavels voor
     * eerlijke bieders.
     *
     * @return list<Signal>
     */
    private function repeatNonPayers(): array
    {
        $orderIds = AuditLog::query()
            ->where('action', 'order.transition')
            ->where('after->status', OrderStatus::Cancelled->value)
            ->where('after->reason', 'payment_deadline_expired')
            ->where('created_at', '>=', now()->subDays(90))
            ->pluck('subject_id');

        if ($orderIds->isEmpty()) {
            return [];
        }

        return Order::query()
            ->whereIn('orders.id', $orderIds)
            ->join('users', 'users.id', '=', 'orders.buyer_id')
            ->selectRaw('orders.buyer_id, users.name, count(*) as unpaid')
            ->groupBy('orders.buyer_id', 'users.name')
            ->havingRaw('count(*) >= 2')
            ->get()
            ->map(fn (object $row): array => [
                'key' => 'non_payer:'.$row->buyer_id,
                'severity' => 'hoog',
                'title' => __('risk.non_payer', ['name' => $row->name]),
                'body' => __('risk.non_payer_body', ['count' => (int) $row->unpaid]),
                'url' => route('admin.users.show', $row->buyer_id),
            ])
            ->all();
    }

    /**
     * Een verkoper van wie moderatie twee keer of vaker een kavel offline
     * haalde.
     *
     * @return list<Signal>
     */
    private function repeatRejectedSellers(): array
    {
        return Lot::query()
            ->where('status', LotStatus::Cancelled)
            ->whereNotNull('moderation_note')
            ->whereNotNull('seller_id')
            ->where('reviewed_at', '>=', now()->subDays(90))
            ->join('users', 'users.id', '=', 'lots.seller_id')
            ->selectRaw('lots.seller_id, users.name, count(*) as rejected')
            ->groupBy('lots.seller_id', 'users.name')
            ->havingRaw('count(*) >= 2')
            ->get()
            ->map(fn (object $row): array => [
                'key' => 'rejected_seller:'.$row->seller_id,
                'severity' => 'midden',
                'title' => __('risk.rejected_seller', ['name' => $row->name]),
                'body' => __('risk.rejected_seller_body', ['count' => (int) $row->rejected]),
                'url' => route('admin.users.show', $row->seller_id),
            ])
            ->all();
    }
}
