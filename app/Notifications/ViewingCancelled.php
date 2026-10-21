<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Viewing;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
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
    use Queueable, ShowsInApp;

    public function __construct(public readonly Viewing $viewing)
    {
        $this->afterCommit = true;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    protected function inApp(object $notifiable): array
    {
        return [
            'kind' => 'viewing',
            'title' => __('notifications.viewing_cancelled.buyer.subject', ['lot' => $this->viewing->lot->title]),
            'body' => __('notifications.viewing_cancelled.buyer.line', ['moment' => $this->viewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('dddd D MMMM, HH:mm')]),
            'url' => route('lots.show', $this->viewing->lot),
        ];
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
            ->line(__('notifications.viewing_cancelled.buyer.line', Markdown::escapeValues($replace)))
            ->action(__('notifications.viewing_cancelled.buyer.action'), route('lots.show', $this->viewing->lot))
            ->salutation('Aucvy');
    }
}
