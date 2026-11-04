<?php

declare(strict_types=1);

namespace App\Lots\Management;

use App\Enums\LotStatus;
use App\Jobs\WithdrawLotFromChannels;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Een kavel pauzeren (briefing sectie 7: published naar paused, door de
 * verkoper of moderatie, met reden). Alleen zolang er nog niet geboden is:
 * een bieder mag niet onder zijn bod vandaan een pauze krijgen. De veiling
 * staat stil; de resterende tijd gaat bij hervatten weer lopen.
 */
final class PauseLot
{
    public function handle(Lot $lot, User $actor, ?string $reason = null): Lot
    {
        return DB::transaction(function () use ($lot, $actor, $reason): Lot {
            $locked = Lot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();
            // Ook de veiling op slot: een bod dat net binnenkomt wacht hierop en
            // ziet daarna een gepauzeerde kavel.
            $auction = $locked->auction === null ? null : Auction::query()->whereKey($locked->auction->id)->lockForUpdate()->first();

            if (! in_array($locked->status, [LotStatus::Scheduled, LotStatus::Live], true) || $auction === null || $auction->isClosed()) {
                throw ValidationException::withMessages(['lot' => __('lots.manage.cannot_pause')]);
            }

            if ($auction->bid_count > 0) {
                throw ValidationException::withMessages(['lot' => __('lots.manage.cannot_pause_bids')]);
            }

            $auction->paused_at = now();
            $auction->save();

            $before = $locked->status;
            $locked->status = LotStatus::Paused;
            $locked->save();

            Audit::record('lot.paused', $locked, $actor, ['status' => $before->value], [
                'status' => LotStatus::Paused->value,
                'reason' => $reason,
                'ends_at' => $auction->ends_at->toIso8601String(),
            ]);

            // Gepauzeerd is niet live: advertenties offline, planning stopt.
            WithdrawLotFromChannels::dispatch($locked->id)->afterCommit();

            return $locked;
        });
    }
}
