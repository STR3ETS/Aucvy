<?php

declare(strict_types=1);

namespace App\Lots\Moderation;

use App\Enums\LotStatus;
use App\Lots\GoLive;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\ListingScreened;
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
    /** @var list<LotStatus> Een kavel in beoordeling of online is moderatiewerk. */
    public const REVIEWABLE = [LotStatus::PendingReview, LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing, LotStatus::Paused];

    public function handle(Lot $lot, User $staff, ?string $note = null): Lot
    {
        if (! in_array($lot->status, self::REVIEWABLE, true)) {
            throw ValidationException::withMessages(['lot' => __('moderation.errors.not_reviewable')]);
        }

        if ($lot->reviewed_at !== null) {
            throw ValidationException::withMessages(['lot' => __('moderation.errors.already_reviewed')]);
        }

        // In beoordeling (vooraf modereren): goedkeuren is live zetten.
        $wasPending = $lot->status === LotStatus::PendingReview;

        if ($wasPending) {
            $lot = app(GoLive::class)->handle($lot, $staff, approvedBy: 'staff');
        }

        $lot->reviewed_at = now();
        $lot->reviewed_by = $staff->id;
        $lot->moderation_note = $note;
        $lot->save();

        Audit::record('lot.approved', $lot, $staff, after: ['note' => $note]);

        $lot->seller?->notify($wasPending
            ? new ListingScreened($lot, ListingScreened::LIVE)
            : new LotModerated($lot, approved: true));

        return $lot;
    }
}
