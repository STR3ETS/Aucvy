<?php

declare(strict_types=1);

namespace App\Compliance;

/**
 * Waarom een handeling niet mag. Elke reden heeft een vaste code voor de
 * API en een tekst voor de gebruiker; de UI kiest er de juiste route bij.
 */
enum DenialReason: string
{
    /** Het account is door beheer geblokkeerd. */
    case Blocked = 'blocked';

    /** Het e-mailadres is nog niet bevestigd. */
    case EmailUnverified = 'email_unverified';

    /** De identiteitscontrole is nog niet aangevraagd. */
    case KycRequired = 'kyc_required';

    /** De identiteitscontrole is aangevraagd en wacht op een besluit. */
    case KycPending = 'kyc_pending';

    /** De identiteitscontrole is afgewezen; opnieuw aanvragen kan. */
    case KycRejected = 'kyc_rejected';

    /** Zakelijk account zonder goedgekeurde bedrijfscontrole (KvK). */
    case KybRequired = 'kyb_required';

    /** Geen rekeningnummer om naar uit te betalen. */
    case IbanRequired = 'iban_required';

    public function message(): string
    {
        return __('permissions.'.$this->value);
    }

    /**
     * Waar de gebruiker het zelf kan oplossen; null als alleen beheer kan.
     */
    public function resolutionRoute(): ?string
    {
        return match ($this) {
            self::EmailUnverified, self::KycRequired, self::KycPending, self::KycRejected, self::KybRequired, self::IbanRequired => 'verification.overview',
            self::Blocked => null,
        };
    }
}
