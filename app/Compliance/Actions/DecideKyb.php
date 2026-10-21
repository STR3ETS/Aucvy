<?php

declare(strict_types=1);

namespace App\Compliance\Actions;

use App\Enums\KycStatus;
use App\Models\Company;
use App\Models\User;
use App\Notifications\VerificationDecided;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Het besluit over een bedrijfscontrole (KYB): de KvK-gegevens zijn
 * nagekeken en kloppen, of niet. Goedkeuren maakt publiceren en uitbetalen
 * mogelijk voor alle accounts van het bedrijf; afwijzen zet de aanvraag
 * terug met een reden, zodat het bedrijf opnieuw kan aanvragen.
 */
final class DecideKyb
{
    public function handle(Company $company, User $staff, bool $approved, ?string $note = null): Company
    {
        return DB::transaction(function () use ($company, $staff, $approved, $note): Company {
            $company->verified_at = $approved ? now() : null;
            $company->ubo_verified_at = $approved && $company->ubo_declared_at !== null ? now() : null;
            $company->kyb_requested_at = $approved ? $company->kyb_requested_at : null;
            $company->kyb_note = $approved ? null : $note;
            $company->save();

            Audit::record($approved ? 'company.kyb_verified' : 'company.kyb_rejected', $company, $staff, after: [
                'kvk_number' => $company->kvk_number,
                'via' => 'compliance',
                'note' => $note,
            ]);

            $company->users()->get()->each(fn (User $user) => $user->notify(
                new VerificationDecided($approved ? KycStatus::Verified : KycStatus::Rejected, $note, 'kyb'),
            ));

            return $company;
        });
    }
}
