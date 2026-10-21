<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lot;
use App\Models\User;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * "Een kavel op je volglijst sluit binnenkort" (briefing sectie 3, de
 * volglijst met meldingen). Push als er een apparaat is ingeschreven,
 * anders mail; in-app altijd.
 */
final class WatchlistEnding extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly Lot $lot)
    {
        $this->afterCommit = true;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (! $notifiable instanceof User) {
            return $channels;
        }

        if ($notifiable->prefers('watchlist_push') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        } elseif ($notifiable->prefers('watchlist_email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.watchlist_ending.subject', ['lot' => $this->lot->title]))
            ->line(__('notifications.watchlist_ending.line', Markdown::escapeValues($this->replace())))
            ->action(__('notifications.watchlist_ending.action'), route('lots.show', $this->lot))
            ->salutation('Aucvy');
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('notifications.watchlist_ending.subject', ['lot' => $this->lot->title]))
            ->body(__('notifications.watchlist_ending.line', $this->replace()))
            ->tag('watchlist-'.$this->lot->id)
            ->data(['url' => route('lots.show', $this->lot)])
            ->options(['TTL' => 3600]);
    }

    protected function inApp(object $notifiable): array
    {
        return [
            'kind' => 'watchlist',
            'title' => __('notifications.watchlist_ending.subject', ['lot' => $this->lot->title]),
            'body' => __('notifications.watchlist_ending.line', $this->replace()),
            'url' => route('lots.show', $this->lot),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function replace(): array
    {
        $endsAt = $this->lot->auction?->ends_at;

        return [
            'lot' => $this->lot->title,
            'price' => Money::format($this->lot->currentPriceCents()),
            'ends' => $endsAt?->timezone(config('aucvy.display_timezone'))->isoFormat('HH:mm') ?? '-',
        ];
    }
}
