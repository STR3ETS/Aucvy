<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Eén bericht per overgang, per rol (hfst. 9). Wie wat krijgt bepaalt
 * TransitionOrder; de teksten staan per status en rol in
 * lang/nl/notifications.php - geen tekst betekent bewust geen mail.
 */
final class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly OrderStatus $status,
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
        $key = "notifications.{$this->status->value}.{$this->role}";

        $replace = [
            'lot' => $this->order->lot->title,
            'window' => (int) config('aucvy.orders.dispute_window_hours'),
            'deadline' => $this->deadline(),
        ];

        return (new MailMessage)
            ->subject(__("{$key}.subject", $replace))
            ->line(__("{$key}.line", $replace))
            ->action(__("{$key}.action"), route('orders.show', $this->order))
            ->salutation('Aucvy');
    }

    /**
     * De deadline die bij deze overgang hoort: de meldtermijn na levering, of
     * de reactietermijn van de verkoper bij een geschil.
     */
    private function deadline(): string
    {
        $moment = match ($this->status) {
            OrderStatus::Disputed => $this->order->disputes()->latest('id')->first()?->sla_due_at,
            default => $this->order->dispute_window_ends_at,
        };

        return $moment?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm') ?? '-';
    }
}
