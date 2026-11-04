<?php

declare(strict_types=1);

namespace App\Observers;

use App\Lots\Dossier\ProjectSources;
use App\Models\AuctionEvent;
use App\Models\AuditLog;
use App\Models\LotDocument;
use App\Models\MailDelivery;
use Throwable;

/**
 * Vult het kaveldossier vanuit de bronlogs zodra die geschreven worden. Een
 * fout in de projectie mag de bron nooit laten mislukken: de tijdlijn is
 * afgeleid, en aucvy:dossier-backfill haalt hem altijd weer in.
 */
final class DossierObserver
{
    public function __construct(private readonly ProjectSources $project) {}

    public function created(AuditLog|AuctionEvent|LotDocument|MailDelivery $model): void
    {
        $this->safely(fn () => match (true) {
            $model instanceof AuditLog => $this->project->fromAudit($model),
            $model instanceof AuctionEvent => $this->project->fromAuctionEvent($model),
            $model instanceof LotDocument => $this->project->document($model, 'added'),
            $model instanceof MailDelivery => $this->project->mail($model, 'sent'),
        });
    }

    public function updated(LotDocument|MailDelivery $model): void
    {
        $this->safely(fn () => match (true) {
            $model instanceof LotDocument => $model->wasChanged('ai_checked_at') && $model->ai_checked_at !== null
                ? $this->markChecked($model)
                : null,
            $model instanceof MailDelivery => $model->wasChanged('status')
                ? $this->project->mail($model, $model->status)
                : null,
        });
    }

    private function markChecked(LotDocument $document): void
    {
        if (($document->getAttributes()['checked_by'] ?? null) === null) {
            $document->newQuery()->whereKey($document->id)->update(['checked_by' => 'lex']);
        }

        $this->project->document($document, 'checked');
    }

    private function safely(callable $projection): void
    {
        try {
            $projection();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
