<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Distribution\Publishers\PublisherFactory;
use App\Lots\Dossier\Dossier;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotDistribution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Bij sluiten, verkoop of intrekken gaan alle advertenties offline en
 * vervalt wat nog gepland stond. Een kanaal dat niet meewerkt houdt de
 * fout vast, maar de status wordt altijd offline.
 */
final class WithdrawLotFromChannels implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $lotId) {}

    public function handle(PublisherFactory $publishers): void
    {
        $lot = Lot::query()->find($this->lotId);

        if ($lot === null) {
            return;
        }

        $rows = LotDistribution::query()->with('lot')->where('lot_id', $lot->id)
            ->whereIn('status', [LotDistribution::SCHEDULED, LotDistribution::POSTING, LotDistribution::READY, LotDistribution::POSTED])
            ->get();

        foreach ($rows as $distribution) {
            $error = null;

            if ($distribution->status === LotDistribution::POSTED) {
                try {
                    $publishers->for($distribution)->withdraw($distribution);
                } catch (Throwable $exception) {
                    $error = $exception->getMessage();
                }

                Dossier::record(
                    lot: $lot->id,
                    source: LotActivity::SOURCE_DISTRIBUTION,
                    type: 'withdrawn',
                    visibility: LotActivity::SELLER,
                    payload: ['channel' => $distribution->channelLabel()],
                    ref: "distribution:{$distribution->id}:withdrawn",
                    actorType: 'system',
                );
            }

            $distribution->forceFill(['status' => LotDistribution::OFFLINE, 'withdrawn_at' => now(), 'last_error' => $error])->save();
        }
    }
}
