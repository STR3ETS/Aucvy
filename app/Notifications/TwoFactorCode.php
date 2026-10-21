<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De tweede stap bij het inloggen voor accounts met een gekoppelde
 * uitbetaling (hfst. 15). Tien minuten geldig.
 *
 * Bewust niet via de wachtrij: een stilstaande worker zou anders elke
 * medewerker en elk bedrijf met een uitbetaalrekening buiten de deur houden.
 */
final class TwoFactorCode extends Notification
{
    public function __construct(public readonly string $code) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.two_factor.subject', ['code' => $this->code]))
            ->line(__('notifications.two_factor.line', ['code' => $this->code]))
            ->line(__('notifications.two_factor.warning'))
            ->salutation('Aucvy');
    }
}
