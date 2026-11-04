<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Lots\Dossier\ProjectSources;
use App\Models\AuctionEvent;
use App\Models\AuditLog;
use App\Models\LotDocument;
use App\Models\MailDelivery;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Bouwt het kaveldossier op uit de bronlogs die er al waren vóór de
 * tijdlijn bestond. Idempotent: elke bronregel heeft een source_ref, dus
 * nog een keer draaien voegt niets dubbel toe.
 */
final class BackfillDossier extends Command
{
    protected $signature = 'aucvy:dossier-backfill';

    protected $description = 'Vult het kaveldossier vanuit auditlog, veilingevents, orders, mail en documenten';

    public function handle(ProjectSources $project): int
    {
        $added = ['audit' => 0, 'events' => 0, 'orders' => 0, 'mail' => 0, 'documents' => 0];

        AuditLog::query()->orderBy('id')->chunkById(500, function ($entries) use ($project, &$added): void {
            foreach ($entries as $entry) {
                $added['audit'] += $project->fromAudit($entry) === null ? 0 : 1;
            }
        });

        AuctionEvent::query()->with('auction')->orderBy('id')->chunkById(500, function ($events) use ($project, &$added): void {
            foreach ($events as $event) {
                $added['events'] += $project->fromAuctionEvent($event) === null ? 0 : 1;
            }
        });

        Order::query()->orderBy('id')->chunkById(500, function ($orders) use ($project, &$added): void {
            foreach ($orders as $order) {
                $added['orders'] += $project->orderCreated($order) === null ? 0 : 1;
            }
        });

        MailDelivery::query()->whereNotNull('lot_id')->orderBy('id')->chunkById(500, function ($deliveries) use ($project, &$added): void {
            foreach ($deliveries as $delivery) {
                $added['mail'] += $project->mail($delivery, 'sent') === null ? 0 : 1;

                if ($delivery->status !== MailDelivery::STATUS_SENT) {
                    $added['mail'] += $project->mail($delivery, $delivery->status) === null ? 0 : 1;
                }
            }
        });

        LotDocument::query()->orderBy('id')->chunkById(500, function ($documents) use ($project, &$added): void {
            foreach ($documents as $document) {
                $added['documents'] += $project->document($document, 'added') === null ? 0 : 1;

                if ($document->ai_checked_at !== null) {
                    $added['documents'] += $project->document($document, 'checked') === null ? 0 : 1;
                }
            }
        });

        $this->info(sprintf(
            'Toegevoegd: %d uit het auditlog, %d veilingevents, %d orders, %d mails, %d documenten.',
            $added['audit'], $added['events'], $added['orders'], $added['mail'], $added['documents'],
        ));

        return self::SUCCESS;
    }
}
