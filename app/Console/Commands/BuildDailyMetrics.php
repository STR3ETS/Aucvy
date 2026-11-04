<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Admin\Ledger;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\Bid;
use App\Models\CostEntry;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\MetricSnapshot;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Vult elke nacht metric_snapshots met de cijfers van gisteren, volgens de
 * definities uit het control center. Het dashboard leest die plus de
 * cijfers van vandaag live. Opnieuw draaien overschrijft dezelfde dag.
 */
final class BuildDailyMetrics extends Command
{
    protected $signature = 'aucvy:build-metrics {--date= : De dag (Y-m-d), standaard gisteren} {--days=1 : Zoveel dagen terug}';

    protected $description = 'Vult de dagcijfers van het control center';

    public function handle(Ledger $ledger): int
    {
        $end = $this->option('date') ? CarbonImmutable::parse((string) $this->option('date')) : CarbonImmutable::yesterday();
        $days = max(1, (int) $this->option('days'));

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = $end->subDays($offset);
            $this->build($ledger, $day);
        }

        $this->info("Cijfers bijgewerkt tot en met {$end->toDateString()}.");

        return self::SUCCESS;
    }

    private function build(Ledger $ledger, CarbonImmutable $day): void
    {
        $from = $day->startOfDay();
        $to = $day->endOfDay();

        $paid = Order::query()->whereBetween('paid_at', [$from, $to]);

        MetricSnapshot::put('transactions_count', $day, (int) (clone $paid)->count());
        MetricSnapshot::put('transactions_volume_cents', $day, (int) (clone $paid)->sum('hammer_cents'));
        MetricSnapshot::put('platform_revenue_cents', $day, $ledger->platformRevenueCents($from, $to));
        MetricSnapshot::put('payment_cost_cents', $day, $ledger->paymentCostCents($from, $to));
        MetricSnapshot::put('bids_count', $day, (int) Bid::query()->whereBetween('placed_at', [$from, $to])->count());
        MetricSnapshot::put('lots_published', $day, (int) Lot::query()->whereBetween('published_at', [$from, $to])->count());
        MetricSnapshot::put('users_new', $day, (int) User::query()->whereBetween('created_at', [$from, $to])->whereNotNull('email_verified_at')->count());
        MetricSnapshot::put('tickets_new', $day, (int) SupportTicket::query()->whereBetween('created_at', [$from, $to])->count());
        MetricSnapshot::put('disputes_new', $day, (int) Dispute::query()->whereBetween('created_at', [$from, $to])->count());

        // Standen aan het eind van de dag.
        MetricSnapshot::put('lots_live', $day, (int) Lot::query()->whereIn('status', [LotStatus::Live, LotStatus::Closing])->count());
        MetricSnapshot::put('users_total', $day, (int) User::query()->whereNotNull('email_verified_at')->count());
        MetricSnapshot::put('tickets_open', $day, (int) SupportTicket::query()->open()->count());
        MetricSnapshot::put('disputes_open', $day, (int) Dispute::query()->whereNull('resolved_at')->count());
        MetricSnapshot::put('orders_open', $day, (int) Order::query()->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->count());

        // Kosten uit de boekhouding tellen per maand; per dag een twaalfde van de maand is misleidend, dus per maand apart.
        $monthCosts = (int) CostEntry::query()->where('period', $day->startOfMonth()->toDateString())->sum('amount_cents');
        MetricSnapshot::put('costs_month_cents', $day->startOfMonth(), $monthCosts);
    }
}
