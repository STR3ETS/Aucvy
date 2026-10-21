<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Auctions\Events\Outbid;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * "Je bent overboden" als web push (hfst. 14): e-mail is hiervoor te laat.
 * Dit bericht moet binnen twee seconden op het scherm staan - de
 * realtime-queue, niet de gewone.
 */
final class OutbidPush extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly Outbid $event)
    {
        $this->afterCommit = true;
        $this->onQueue('realtime');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    protected function inApp(object $notifiable): array
    {
        $lot = $this->event->auction->lot;

        return [
            'kind' => 'outbid',
            'title' => __('notifications.outbid.subject', ['lot' => $lot->title]),
            'body' => __('notifications.outbid.line', ['lot' => $lot->title, 'price' => Money::format($this->event->newPriceCents)]),
            'url' => route('lots.show', $lot),
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        $lot = $this->event->auction->lot;

        return (new WebPushMessage)
            ->title('Je bent overboden')
            ->body(sprintf(
                '%s staat nu op %s%s',
                $lot->title,
                Money::format($this->event->newPriceCents),
                $this->event->byAutomaticBid ? ' (automatisch bod)' : '',
            ))
            ->tag('outbid-'.$this->event->auction->id)
            ->data(['url' => route('lots.show', $lot)])
            ->options(['TTL' => 300]);
    }
}
