<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De welkomstmail na registratie: wat je nu al kunt en wat de eerste stap
 * is (verificatie vóór je eerste bod of kavel). Los van de
 * e-mailbevestiging, die Laravel zelf stuurt.
 */
final class Welcome extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit = true;
    }

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
            ->subject(__('notifications.welcome.subject'))
            ->greeting(__('notifications.welcome.greeting', ['name' => Markdown::escape($notifiable->name)]))
            ->line(__('notifications.welcome.line'))
            ->line(__('notifications.welcome.next'))
            ->action(__('notifications.welcome.action'), route('verification.overview'))
            ->line(__('notifications.welcome.browse'))
            ->salutation('Aucvy');
    }
}
