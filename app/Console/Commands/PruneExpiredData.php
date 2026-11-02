<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\AiRun;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Models\Dispute;
use App\Models\MailDelivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Review;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $aiRuns = $this->deleteInChunks(AiRun::query()->where('created_at', '<', $aiCutoff)->toBase());

        $eventCutoff = now()->subMonths((int) config('aucvy.retention.auction_events_months'));
        $events = $this->deleteInChunks(AuctionEvent::query()->where('created_at', '<', $eventCutoff)->toBase());

        /*
         | Auditregels zijn bewijs: het model weigert elke verwijdering, dus
         | dit gaat bewust langs de querybuilder, alleen hier en alleen na de
         | fiscale termijn.
         */
        $auditCutoff = now()->subYears((int) config('aucvy.retention.audit_log_years'));
        $auditRows = $this->deleteInChunks(DB::table('audit_logs')->where('created_at', '<', $auditCutoff));

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

        $exports = $this->pruneExports();

        $mailCutoff = now()->subDays((int) config('aucvy.retention.mail_deliveries_days'));
        $mails = $this->deleteInChunks(MailDelivery::query()->where('created_at', '<', $mailCutoff)->toBase());

        $this->info("Opgeruimd: {$aiRuns} AI-runs, {$events} veilingevents, {$auditRows} auditregels, {$bids} biedingen, {$orders} orders, {$exports} exports, {$mails} mailregels.");

        return self::SUCCESS;
    }

    /**
     * Verwijderen in stukken van vijfduizend: één onbegrensde DELETE houdt
     * bij miljoenen rijen de tabel minutenlang op slot.
     */
    private function deleteInChunks(Builder $query, int $chunk = 5000): int
    {
        $total = 0;

        do {
            $deleted = (clone $query)->limit($chunk)->delete();
            $total += $deleted;
        } while ($deleted === $chunk);

        return $total;
    }

    /**
     * De inzage-exports: één JSON per gebruiker met alles wat we over hem
     * hebben. De link verloopt na een dag; het bestand na de termijn.
     */
    private function pruneExports(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays((int) config('aucvy.retention.exports_days'))->getTimestamp();
        $pruned = 0;

        foreach ($disk->files('exports') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $pruned++;
            }
        }

        return $pruned;
    }
}
