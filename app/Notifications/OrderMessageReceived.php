<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OrderMessage;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Nieuw bericht in de draad van een bestelling: in-app en per mail, met
 * de eerste regels van het bericht en een knop naar de bestelling.
 */
final class OrderMessageReceived extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly OrderMessage $message)
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
            'title' => __('notifications.order_message.subject', $this->replace()),
            'body' => mb_strimwidth($this->message->body, 0, 140, '…'),
            'url' => route('orders.show', $this->message->order).'#berichten',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replace();

        return (new MailMessage)
            ->subject(__('notifications.order_message.subject', $replace))
            ->line(__('notifications.order_message.line', Markdown::escapeValues($replace)))
            ->line('"'.Markdown::escape(mb_strimwidth($this->message->body, 0, 400, '…')).'"')
            ->action(__('notifications.order_message.action'), route('orders.show', $this->message->order).'#berichten')
            ->line(__('notifications.order_message.footer'))
            ->salutation('Aucvy');
    }

    /**
     * @return array<string, string>
     */
    private function replace(): array
    {
        $order = $this->message->order;

        return [
            'lot' => $order->lot->title,
            'order' => $order->order_number,
            'from' => __('notifications.order_message.from.'.$this->message->roleFor($order)),
        ];
    }
}
