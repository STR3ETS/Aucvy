<?php

declare(strict_types=1);

namespace App\Lots\Management;

use App\Auctions\Jobs\CloseAuctionJob;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Een gepauzeerde kavel hervatten: de tijd die stilstond komt achter de
 * eindtijd, zodat kopers evenveel tijd houden als voor de pauze. De sluiting
 * wordt opnieuw ingepland.
 */
final class ResumeLot
{
    public function handle(Lot $lot, User $actor): Lot
    {
        $resumed = DB::transaction(function () use ($lot, $actor): Lot {
            $locked = Lot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
            $auction = $locked->auction;

            if ($locked->status !== LotStatus::Paused || $auction === null || $auction->paused_at === null) {
                throw ValidationException::withMessages(['lot' => __('lots.manage.not_paused')]);
            }

            $pausedSeconds = (int) $auction->paused_at->diffInSeconds(now(), absolute: true);

            $auction->ends_at = $auction->ends_at->addSeconds($pausedSeconds);
            $auction->original_ends_at = $auction->original_ends_at->addSeconds($pausedSeconds);

            // Bij een afslag loopt de prijs op de klok sinds starts_at: die schuift mee,
            // anders staat de prijs na de pauze ineens op de bodem.
            if ($locked->sale_type === SaleType::Dutch) {
                $auction->starts_at = $auction->starts_at->addSeconds($pausedSeconds);
            }

            $auction->paused_at = null;
            $auction->save();

            $locked->status = $auction->starts_at->isFuture() ? LotStatus::Scheduled : LotStatus::Live;
            $locked->save();

            Audit::record('lot.resumed', $locked, $actor, ['status' => LotStatus::Paused->value], [
                'status' => $locked->status->value,
                'paused_seconds' => $pausedSeconds,
                'ends_at' => $auction->ends_at->toIso8601String(),
            ]);

            return $locked;
        });

        CloseAuctionJob::dispatch($resumed->auction->id)->delay($resumed->auction->ends_at);

        return $resumed;
    }
}
