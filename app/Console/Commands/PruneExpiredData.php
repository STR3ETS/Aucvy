<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\AiRun;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * De bewaartermijnen uit hfst. 15, afgedwongen in code in plaats van
 * beloofd in een document. Draait dagelijks; de vensters staan in
 * config/aucvy.php onder `retention`.
 */
final class PruneExpiredData extends Command
{
    protected $signature = 'aucvy:prune-expired';

    protected $description = 'Verwijdert gegevens waarvan de bewaartermijn verstreken is';

    public function handle(): int
    {
        $aiCutoff = now()->subMonths((int) config('aucvy.retention.ai_runs_months'));
        $aiRuns = AiRun::query()->where('created_at', '<', $aiCutoff)->delete();

        $eventCutoff = now()->subMonths((int) config('aucvy.retention.auction_events_months'));
        $events = AuctionEvent::query()->where('created_at', '<', $eventCutoff)->delete();

        $financialCutoff = now()->subYears((int) config('aucvy.retention.financial_years'));

        /*
         | Biedingen: pas na de fiscale termijn, en alleen van veilingen die
         | toen ook al voorbij waren - een bod op een lopende veiling wordt
         | nooit geraakt, hoe oud de rij ook is.
         */
        $bids = Bid::query()
            ->where('created_at', '<', $financialCutoff)
            ->whereHas('auction', fn ($query) => $query->where('ends_at', '<', $financialCutoff))
            ->delete();

        /*
         | Orders: alleen afgeronde of geannuleerde, met alles wat eraan
         | hangt in één transactie per order - nooit een halve verwijdering.
         */
        $orders = 0;

        Order::query()
            ->whereIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])
            ->where('updated_at', '<', $financialCutoff)
            ->orderBy('id')
            ->chunkById(100, function ($chunk) use (&$orders): void {
                foreach ($chunk as $order) {
                    DB::transaction(function () use ($order): void {
                        Review::query()->where('order_id', $order->id)->delete();
                        Dispute::query()->where('order_id', $order->id)->delete();
                        Payment::query()->where('order_id', $order->id)->delete();
                        Payout::query()->where('order_id', $order->id)->delete();
                        $order->delete();
                    });

                    $orders++;
                }
            });

        $this->info("Opgeruimd: {$aiRuns} AI-runs, {$events} veilingevents, {$bids} biedingen, {$orders} orders.");

        return self::SUCCESS;
    }
}
