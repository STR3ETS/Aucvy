<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Viewing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De verkoper kan niet op het voorgestelde moment. Geen adres, wel een
 * uitnodiging om een nieuw moment voor te stellen.
 */
final class ViewingCancelled extends Notification implements ShouldQueue
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
            'moment' => $this->viewing->slot_at
                ->timezone(config('aucvy.display_timezone'))
                ->isoFormat('dddd D MMMM, HH:mm'),
        ];

        return (new MailMessage)
            ->subject(__('notifications.viewing_cancelled.buyer.subject', $replace))
            ->line(__('notifications.viewing_cancelled.buyer.line', $replace))
            ->action(__('notifications.viewing_cancelled.buyer.action'), route('lots.show', $this->viewing->lot))
            ->salutation('Aucvy');
    }
}
