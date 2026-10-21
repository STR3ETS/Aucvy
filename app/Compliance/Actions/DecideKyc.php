<?php

declare(strict_types=1);

namespace App\Compliance\Actions;

use App\Enums\KycStatus;
use App\Models\AutoBid;
use App\Models\User;
use App\Notifications\VerificationDecided;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Het besluit over een identiteitscontrole (KYC) door compliance. Zolang
 * er geen identiteitsdienst gekoppeld is, is dit de handmatige controle
 * uit de briefing (flow E, status in_review). Goedkeuren zet de biedvlag;
 * afwijzen legt de reden vast en mailt die.
 */
final class DecideKyc
{
    public function handle(User $user, User $staff, bool $approved, ?string $note = null): User
    {
        return DB::transaction(function () use ($user, $staff, $approved, $note): User {
            $before = $user->kyc_status;

            $user->kyc_status = $approved ? KycStatus::Verified : KycStatus::Rejected;
            $user->kyc_verified_at = $approved ? now() : null;
            $user->kyc_note = $approved ? null : $note;
            $user->save();
            $user->refreshBidPermission();

            // Afgewezen: ook de lopende automatische biedingen stoppen.
            $autoBids = $approved ? 0 : AutoBid::query()->where('user_id', $user->id)->where('active', true)->update(['active' => false]);

            Audit::record('user.kyc_changed', $user, $staff, ['status' => $before->value], [
                'status' => $user->kyc_status->value,
                'via' => 'compliance',
                'note' => $note,
                'auto_bids_deactivated' => $autoBids,
            ]);

            $user->notify(new VerificationDecided($user->kyc_status, $note));

            return $user;
        });
    }
}
