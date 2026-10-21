<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Auctions\Events\Outbid;
use App\Models\User;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Je bent overboden" per mail, voor wie geen apparaat heeft ingeschreven
 * voor push. Later dan push, maar beter dan niets (briefing sectie 8:
 * e-mailtemplate "overboden").
 */
final class OutbidMail extends Notification implements ShouldQueue
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
        // Per mail alleen als de ontvanger dat wil; in-app altijd.
        return $notifiable instanceof User && ! $notifiable->prefers('outbid_email') ? ['database'] : ['database', 'mail'];
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

    public function toMail(object $notifiable): MailMessage
    {
        $lot = $this->event->auction->lot;
        $price = Money::format($this->event->newPriceCents);

        return (new MailMessage)
            ->subject(__('notifications.outbid.subject', ['lot' => $lot->title]))
            ->line(__('notifications.outbid.line', ['lot' => Markdown::escape($lot->title), 'price' => $price]))
            ->line($this->event->byAutomaticBid ? __('notifications.outbid.automatic') : __('notifications.outbid.manual'))
            ->action(__('notifications.outbid.action'), route('lots.show', $lot))
            ->salutation('Aucvy');
    }
}
