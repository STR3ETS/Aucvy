<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Auction;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De veiling is gesloten en een ander heeft gewonnen. Eerlijk en kort: de
 * eindprijs, en de weg naar vergelijkbare kavels.
 */
final class AuctionLost extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly Auction $auction)
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
        $lot = $this->auction->lot;

        return [
            'kind' => 'auction_lost',
            'title' => __('notifications.auction_lost.subject', ['lot' => $lot->title]),
            'body' => __('notifications.auction_lost.line', ['lot' => $lot->title, 'price' => Money::format($this->auction->current_price_cents)]),
            'url' => route('lots.show', $lot),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lot = $this->auction->lot;

        return (new MailMessage)
            ->subject(__('notifications.auction_lost.subject', ['lot' => $lot->title]))
            ->line(__('notifications.auction_lost.line', ['lot' => Markdown::escape($lot->title), 'price' => Money::format($this->auction->current_price_cents)]))
            ->line(__('notifications.auction_lost.next'))
            ->action(__('notifications.auction_lost.action'), $lot->category
                ? route('lots.category', $lot->category)
                : route('lots.index'))
            ->salutation('Aucvy');
    }
}
