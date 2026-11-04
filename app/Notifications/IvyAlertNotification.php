<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IvyAlert;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Een alert buiten het platform: push (zonder bedragen op een vergrendeld
 * scherm als de klant dat uitzette) of mail als terugval. Een tik opent
 * altijd de keuze; bieden gebeurt nooit vanuit de melding zelf.
 */
final class IvyAlertNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly IvyAlert $alert, public readonly string $channel) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->channel === 'push' ? [WebPushChannel::class] : ['mail'];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->alert->title())
            ->body($notifiable->prefers('push_amounts') ? $this->alert->body() : 'Open de melding voor je keuze.')
            ->action('Bekijk je keuze', 'open')
            ->data(['url' => route('ivy.inbox', ['alert' => $this->alert->id])]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->alert->title())
            ->line(Markdown::escape($this->alert->body()));

        if ($this->alert->advice() !== null) {
            $mail->line('Wat Ivy ervan vindt: '.Markdown::escape($this->alert->advice()));
        }

        foreach ((array) $this->alert->choices as $choice) {
            if (($choice['primary'] ?? false) === true) {
                $mail->action($choice['label'], route('ivy.alerts.choose', [$this->alert, $choice['key']]));
            }
        }

        return $mail->line('Je krijgt eerst een bevestigingsscherm met het actuele bedrag en alle kosten; vanuit deze mail wordt niets geboden.')->salutation('Ivy, AI-assistent van Aucvy');
    }
}
