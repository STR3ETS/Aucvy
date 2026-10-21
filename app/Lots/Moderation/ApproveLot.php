<?php

declare(strict_types=1);

namespace App\Lots\Moderation;

use App\Enums\LotStatus;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\LotModerated;
use App\Support\Audit\Audit;
use Illuminate\Validation\ValidationException;

/**
 * Een gepubliceerde kavel is nagekeken en in orde. De kavel stond al live;
 * dit legt vast wie dat wanneer heeft beoordeeld, zodat de wachtrij leeg
 * raakt en het spoor klopt.
 */
final class ApproveLot
{
    /** @var list<LotStatus> Alleen een kavel die nu online staat is moderatiewerk. */
    public const REVIEWABLE = [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing, LotStatus::Paused];

    public function handle(Lot $lot, User $staff, ?string $note = null): Lot
    {
        if (! in_array($lot->status, self::REVIEWABLE, true)) {
            throw ValidationException::withMessages(['lot' => __('moderation.errors.not_reviewable')]);
        }

        if ($lot->reviewed_at !== null) {
            throw ValidationException::withMessages(['lot' => __('moderation.errors.already_reviewed')]);
        }

        $lot->reviewed_at = now();
        $lot->reviewed_by = $staff->id;
        $lot->moderation_note = $note;
        $lot->save();

        Audit::record('lot.approved', $lot, $staff, after: ['note' => $note]);

        $lot->seller?->notify(new LotModerated($lot, approved: true));

        return $lot;
    }
}
