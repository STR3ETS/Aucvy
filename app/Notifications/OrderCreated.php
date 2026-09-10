<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De hamerslag: koper en verkoper horen allebei meteen waar ze aan toe zijn
 * (hfst. 9). De betaaldeadline staat erin - dezelfde die orders:advance
 * afdwingt.
 */
final class OrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly string $role,
    ) {
        // Pas versturen als de transactie rond is - geen mail over een rollback.
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
            'lot' => $this->order->lot->title,
            'deadline' => $this->order->payment_deadline_at
                ?->timezone(config('aucvy.display_timezone'))
                ->isoFormat('D MMMM, HH:mm') ?? '-',
        ];

        return (new MailMessage)
            ->subject(__("notifications.created.{$this->role}.subject", $replace))
            ->line(__("notifications.created.{$this->role}.line", $replace))
            ->action(__("notifications.created.{$this->role}.action"), route('orders.show', $this->order))
            ->salutation('Aucvy');
    }
}
