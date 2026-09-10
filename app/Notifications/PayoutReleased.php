<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payout;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De vrijgave - het bericht waar de verkoper op wacht (hfst. 9). Verstuurd
 * door orders:advance, op het moment dat de houdtermijn echt om is.
 */
final class PayoutReleased extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payout $payout) {}

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
            ->subject(__('notifications.payout_released.subject', [
                'amount' => Money::format($this->payout->amount_cents),
            ]))
            ->line(__('notifications.payout_released.line', [
                'lot' => $this->payout->order->lot->title,
            ]))
            ->action(__('notifications.payout_released.action'), route('orders.index'))
            ->salutation('Aucvy');
    }
}
