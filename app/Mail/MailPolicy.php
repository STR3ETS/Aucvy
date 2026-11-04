<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailConsent;
use App\Models\MailDelivery;
use App\Models\MailSuppression;
use App\Models\MailTemplate;
use App\Models\User;

/**
 * Vlak voor elke mail: suppressielijst, toestemming, weekmaximum. Service
 * gaat altijd, behalve naar een adres dat hard terugkomt. Activatie mag
 * tot iemand zich afmeldt; campagne alleen met een uitdrukkelijke ja. De
 * indeling toetst Don; de keuze staat in de instellingen.
 */
final class MailPolicy
{
    public const string SUPPRESSED = 'suppressed';

    public const string NO_CONSENT = 'no_consent';

    public const string UNSUBSCRIBED = 'unsubscribed';

    public const string WEEKLY_LIMIT = 'weekly_limit';

    /**
     * Null als de mail mag; anders de reden waarom niet.
     */
    public function reasonNotToSend(User $user, string $stream): ?string
    {
        if ($stream === MailTemplate::SERVICE) {
            return MailSuppression::has($user->email) && (bool) config('aucvy.mail.suppress_service_too', false) ? self::SUPPRESSED : null;
        }

        if (MailSuppression::has($user->email)) {
            return self::SUPPRESSED;
        }

        $consent = MailConsent::statusFor($user, $stream);

        if ($consent === MailConsent::REVOKED) {
            return self::UNSUBSCRIBED;
        }

        if ($stream === MailTemplate::CAMPAIGN && $consent !== MailConsent::GRANTED && (bool) config('aucvy.mail.campaign_requires_consent', true)) {
            return self::NO_CONSENT;
        }

        if ($stream === MailTemplate::ACTIVATION && $consent !== MailConsent::GRANTED && (bool) config('aucvy.mail.activation_requires_consent', false)) {
            return self::NO_CONSENT;
        }

        $limit = (int) config('aucvy.mail.weekly_marketing_limit', 3);
        $sent = MailDelivery::query()->where('user_id', $user->id)->whereIn('stream', MailTemplate::MARKETING)->where('created_at', '>=', now()->subWeek())->count();

        return $sent >= $limit ? self::WEEKLY_LIMIT : null;
    }

    public function canSend(User $user, string $stream): bool
    {
        return $this->reasonNotToSend($user, $stream) === null;
    }
}
