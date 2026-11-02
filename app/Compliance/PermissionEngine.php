<?php

declare(strict_types=1);

namespace App\Compliance;

use App\Enums\KycStatus;
use App\Enums\UserType;
use App\Models\User;

/**
 * De ene plek die beslist of iemand mag bieden, publiceren of uitbetaald
 * worden (briefing sectie 7, flow E, en sectie 11).
 *
 * De regels lezen de accountstatus, de KYC-status en, voor zakelijke
 * accounts, de bedrijfscontrole. Ze staan hier en nergens anders: de
 * biedmotor, de publicatie en de uitbetaling vragen het hier na, en de UI
 * gebruikt dezelfde uitkomst om knoppen te verbergen of uit te leggen.
 *
 * De volgorde van de controles is de volgorde waarin de gebruiker ze kan
 * oplossen: eerst de blokkade (kan hij niet zelf), dan e-mail, identiteit,
 * bedrijf en rekening.
 */
final class PermissionEngine
{
    public function decide(User $user, Permission $permission): Decision
    {
        if ($user->isBlocked()) {
            return Decision::deny(DenialReason::Blocked);
        }

        if (! $user->hasVerifiedEmail()) {
            return Decision::deny(DenialReason::EmailUnverified);
        }

        if ($user->kyc_status === KycStatus::Rejected) {
            return Decision::deny(DenialReason::KycRejected);
        }

        if ($user->kyc_status === KycStatus::Pending) {
            return Decision::deny(DenialReason::KycPending);
        }

        if (! $user->kyc_status->allowsBidding()) {
            return Decision::deny(DenialReason::KycRequired);
        }

        return match ($permission) {
            Permission::Bid => Decision::allow(),
            Permission::Publish => $this->business($user),
            Permission::Payout => $this->payout($user),
        };
    }

    public function allows(User $user, Permission $permission): bool
    {
        return $this->decide($user, $permission)->allowed;
    }

    /**
     * Publiceren: alleen zakelijk, met een goedgekeurde KvK-controle (KYB).
     * Zakelijke verkopers, particuliere kopers (besluit Raph, 2 november
     * 2026); een particulier account mag wel bieden en kopen.
     */
    private function business(User $user): Decision
    {
        if ($user->type !== UserType::Business || $user->company === null) {
            return Decision::deny(DenialReason::BusinessAccountRequired);
        }

        if (! $user->company->isVerified()) {
            return Decision::deny(DenialReason::KybRequired);
        }

        return Decision::allow();
    }

    /**
     * Uitbetalen: dezelfde eisen als publiceren, plus een rekeningnummer
     * op het bedrijf.
     */
    private function payout(User $user): Decision
    {
        $business = $this->business($user);

        if ($business->denied()) {
            return $business;
        }

        if ($user->company?->payout_iban_last4 === null) {
            return Decision::deny(DenialReason::IbanRequired);
        }

        return Decision::allow();
    }
}
