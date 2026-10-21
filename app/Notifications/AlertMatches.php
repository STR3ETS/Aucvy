<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lot;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * De alert-treffer (briefing sectie 8): nieuwe kavels die aan een bewaarde
 * zoekopdracht voldoen. In-app altijd; per mail als de ontvanger dat wil.
 */
final class AlertMatches extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    /**
     * @param  Collection<int, Lot>  $lots
     */
    public function __construct(
        public readonly SavedSearch $search,
        public readonly Collection $lots,
    ) {
        $this->afterCommit = true;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->prefers('alerts_email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->lots->count();

        $mail = (new MailMessage)
            ->subject(trans_choice('notifications.alert_matches.subject', $count, ['count' => $count, 'name' => $this->search->label()]))
            ->line(trans_choice('notifications.alert_matches.line', $count, ['count' => $count, 'name' => Markdown::escape($this->search->label())]));

        foreach ($this->lots->take(5) as $lot) {
            $mail->line(sprintf('- %s (%s)', Markdown::escape($lot->title), Money::format($lot->currentPriceCents())));
        }

        return $mail
            ->action(__('notifications.alert_matches.action'), $this->url())
            ->line(__('notifications.alert_matches.manage', ['url' => route('dashboard.alerts')]))
            ->salutation('Aucvy');
    }

    protected function inApp(object $notifiable): array
    {
        $count = $this->lots->count();

        return [
            'kind' => 'alert',
            'title' => trans_choice('notifications.alert_matches.subject', $count, ['count' => $count, 'name' => $this->search->label()]),
            'body' => $this->lots->take(3)->pluck('title')->join(', '),
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return route('lots.index', $this->search->query ?? []);
    }
}
