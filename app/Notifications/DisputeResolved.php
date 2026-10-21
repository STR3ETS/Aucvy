<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Dispute;
use App\Notifications\Concerns\ShowsInApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De uitkomst met motivering, naar beide partijen (hfst. 9). De motivering
 * is verplicht bij het beoordelen; hier wordt hij ook echt bezorgd.
 */
final class DisputeResolved extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly Dispute $dispute)
    {
        // Pas versturen als de transactie rond is - geen mail over een rollback.
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
        $order = $this->dispute->order;

        return [
            'kind' => 'dispute',
            'title' => __('notifications.dispute_resolved.subject', ['lot' => $order->lot->title]),
            'body' => __('notifications.dispute_resolved.intro', ['resolution' => str_replace('_', ' ', (string) $this->dispute->resolution)]),
            'url' => route('orders.show', $order),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->dispute->order;

        return (new MailMessage)
            ->subject(__('notifications.dispute_resolved.subject', ['lot' => $order->lot->title]))
            ->line(__('notifications.dispute_resolved.intro', [
                'resolution' => str_replace('_', ' ', (string) $this->dispute->resolution),
            ]))
            ->line(__('notifications.dispute_resolved.notes', ['notes' => (string) $this->dispute->resolution_notes]))
            ->action(__('notifications.dispute_resolved.action'), route('orders.show', $order))
            ->salutation('Aucvy');
    }
}
