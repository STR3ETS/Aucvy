<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Het reviewverzoek na afronding (masterbriefing 4.1 en 4.3): de koper van
 * een afgeronde bestelling wordt één keer uitgenodigd om de verkoper te
 * beoordelen. Alleen de koper, alleen na afronding, en nooit als er al een
 * beoordeling staat.
 */
final class ReviewRequested extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly Order $order)
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
            'kind' => 'order',
            'title' => __('notifications.review_requested.subject', ['lot' => $this->order->lot->title]),
            'body' => __('notifications.review_requested.body'),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.review_requested.subject', ['lot' => $this->order->lot->title]))
            ->line(__('notifications.review_requested.line', ['lot' => Markdown::escape($this->order->lot->title)]))
            ->line(__('notifications.review_requested.body'))
            ->action(__('notifications.review_requested.action'), $this->url())
            ->salutation('Aucvy');
    }

    private function url(): string
    {
        return route('orders.show', $this->order).'#beoordeling';
    }
}
