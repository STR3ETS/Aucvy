<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Distribution\Publishers\PublisherFactory;
use App\Distribution\Publishers\PublishResult;
use App\Distribution\ShareVisual;
use App\Lots\Dossier\Dossier;
use App\Models\LotActivity;
use App\Models\LotDistribution;
use App\Notifications\DistributionUpdate;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Eén bericht op één kanaal plaatsen: tekst invullen met de stand van nu,
 * beeld maken, plaatsen via de koppeling of klaarzetten voor de verkoper.
 * Idempotent op de client_key; niets wordt geplaatst als de kavel niet
 * meer live is.
 */
final class DistributeLotToChannel implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $distributionId) {}

    public function uniqueId(): string
    {
        return (string) $this->distributionId;
    }

    public function handle(PublisherFactory $publishers, ShareVisual $visuals): void
    {
        $distribution = LotDistribution::query()->with(['lot.auction', 'lot.seller', 'lot.media', 'lot.category', 'lot.location'])->find($this->distributionId);

        if ($distribution === null || in_array($distribution->status, [LotDistribution::POSTED, LotDistribution::OFFLINE, LotDistribution::READY], true)) {
            return;
        }

        $lot = $distribution->lot;

        if ($lot === null || ! $lot->status->isPublic() || $lot->auction === null || $lot->auction->isClosed() || $lot->auction->isPaused()) {
            $distribution->forceFill(['status' => LotDistribution::OFFLINE, 'withdrawn_at' => now(), 'last_error' => 'De kavel is niet (meer) live; er is niets geplaatst.'])->save();

            return;
        }

        $body = $distribution->renderedBody();
        $visualPath = $visuals->render($lot, $distribution->channel()->format(), $distribution->moment);
        $visualUrl = $visuals->url($lot, $distribution->channel()->format(), $distribution->moment);

        try {
            $result = $publishers->for($distribution)->publish($distribution, $body, $visualUrl);
        } catch (Throwable $exception) {
            $result = PublishResult::failed($exception->getMessage());
        }

        $distribution->forceFill([
            'status' => $result->status,
            'visual_path' => $visualPath,
            'external_id' => $result->externalId ?? $distribution->external_id,
            'url' => $result->url ?? $distribution->url,
            'posted_at' => $result->status === LotDistribution::POSTED ? now() : $distribution->posted_at,
            'last_error' => $result->error,
        ])->save();

        if ($result->status === LotDistribution::POSTED || $result->status === LotDistribution::FAILED) {
            Dossier::record(
                lot: $lot->id,
                source: LotActivity::SOURCE_DISTRIBUTION,
                type: $result->status === LotDistribution::POSTED ? 'posted' : 'failed',
                visibility: LotActivity::SELLER,
                payload: ['channel' => $distribution->channelLabel(), 'moment' => $distribution->moment, 'error' => $result->error],
                ref: "distribution:{$distribution->id}:{$result->status}:".now()->timestamp,
                actorType: 'system',
            );
        }

        $lot->seller?->notify(new DistributionUpdate($distribution->fresh(['lot'])));
    }
}
