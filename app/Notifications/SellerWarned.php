<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Lot;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Een waarschuwing van een medewerker aan de verkoper, naar aanleiding van
 * een gesprek. Een sanctie is het niet; die neemt beheer apart.
 */
final class SellerWarned extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public readonly Lot $lot;

    public function __construct(public readonly Conversation $conversation, public readonly string $note)
    {
        $this->lot = $conversation->lot;
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
            'kind' => 'message',
            'title' => __('notifications.seller_warned.subject', ['lot' => $this->lot->title]),
            'body' => $this->note,
            'url' => route('messages.show', $this->conversation),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.seller_warned.subject', ['lot' => $this->lot->title]))
            ->line(__('notifications.seller_warned.line', ['lot' => Markdown::escape((string) $this->lot->title)]))
            ->line(Markdown::escape($this->note))
            ->line(__('notifications.seller_warned.rule'))
            ->action(__('notifications.seller_warned.action'), route('messages.show', $this->conversation))
            ->salutation('Aucvy');
    }
}
