<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * De inzage-export staat klaar. De downloadlink is tijdelijk getekend en
 * werkt alleen ingelogd als de gebruiker zelf (hfst. 15).
 */
final class DataExportReady extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject(__('notifications.data_export.subject'))
            ->line(__('notifications.data_export.line'))
            ->action(__('notifications.data_export.action'), URL::temporarySignedRoute(
                'account.export.download',
                now()->addHours(24),
                ['user' => $notifiable],
            ))
            ->line(__('notifications.data_export.expires'))
            ->salutation('Aucvy');
    }
}
