<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Viewing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * De verkoper krijgt het voorstel met twee getekende links: bevestigen of
 * afwijzen. Pas bij bevestiging gaat het adres naar de koper (hfst. 7).
 */
final class ViewingRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Viewing $viewing)
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
        $replace = [
            'lot' => $this->viewing->lot->title,
            'name' => $this->viewing->user->name,
            'moment' => $this->moment(),
        ];

        $mail = (new MailMessage)
            ->subject(__('notifications.viewing_requested.seller.subject', $replace))
            ->line(__('notifications.viewing_requested.seller.line', $replace));

        if ($this->viewing->note !== null) {
            $mail->line(__('notifications.viewing_requested.seller.note', ['note' => $this->viewing->note]));
        }

        return $mail
            ->action(
                __('notifications.viewing_requested.seller.action'),
                URL::signedRoute('viewings.confirm', ['viewing' => $this->viewing]),
            )
            ->line(__('notifications.viewing_requested.seller.decline', [
                'url' => URL::signedRoute('viewings.decline', ['viewing' => $this->viewing]),
            ]))
            ->salutation('Aucvy');
    }

    private function moment(): string
    {
        return $this->viewing->slot_at
            ->timezone(config('aucvy.display_timezone'))
            ->isoFormat('dddd D MMMM, HH:mm');
    }
}
