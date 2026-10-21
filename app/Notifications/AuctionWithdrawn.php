<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Auction;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Moderatie haalde een kavel offline terwijl er geboden was: elke bieder
 * hoort dat de veiling is beëindigd zonder winnaar en dat zijn borg terug is.
 */
final class AuctionWithdrawn extends Notification implements ShouldQueue
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
            'kind' => 'auction',
            'title' => __('notifications.auction_withdrawn.subject', ['lot' => $lot->title]),
            'body' => __('notifications.auction_withdrawn.line', ['lot' => $lot->title]),
            'url' => route('lots.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lot = $this->auction->lot;

        return (new MailMessage)
            ->subject(__('notifications.auction_withdrawn.subject', ['lot' => $lot->title]))
            ->line(__('notifications.auction_withdrawn.line', ['lot' => Markdown::escape($lot->title)]))
            ->line(__('notifications.auction_withdrawn.deposit'))
            ->action(__('notifications.auction_withdrawn.action'), route('lots.index'))
            ->salutation('Aucvy');
    }
}
