<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Distribution\ShareVisual;
use App\Models\Lot;
use App\Models\LotDistribution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * De drie formaten van het deelbeeld vooraf maken, zodat de deelkit en
 * de kavelpagina ze meteen kunnen tonen.
 */
final class RenderShareVisual implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $lotId, public readonly string $moment = LotDistribution::KIT) {}

    public function handle(ShareVisual $visuals): void
    {
        $lot = Lot::query()->with(['auction', 'media', 'category'])->find($this->lotId);

        if ($lot === null || ! $lot->status->isPublic()) {
            return;
        }

        foreach (ShareVisual::FORMATS as $format) {
            $visuals->render($lot, $format, $this->moment);
        }
    }
}
