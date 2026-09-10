<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De tweede stap bij het inloggen voor accounts met een gekoppelde
 * uitbetaling (hfst. 15). Tien minuten geldig.
 */
final class TwoFactorCode extends Notification implements ShouldQueue
{
    use Queueable;

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
