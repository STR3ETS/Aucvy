<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lot;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De uitkomst van de moderatie: goedgekeurd (de kavel blijft staan) of
 * afgewezen met reden (de kavel is offline). Briefing sectie 8: de mails
 * "kavel goedgekeurd of afgewezen".
 */
final class LotModerated extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(
        public readonly Lot $lot,
        public readonly bool $approved,
        public readonly ?string $reason = null,
    ) {
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
            'kind' => 'moderation',
            'title' => __($this->approved ? 'notifications.lot_moderated.approved_subject' : 'notifications.lot_moderated.rejected_subject', ['lot' => $this->lot->title]),
            'body' => $this->approved
                ? __('notifications.lot_moderated.approved_line', ['lot' => $this->lot->title])
                : __('notifications.lot_moderated.reason', ['reason' => (string) $this->reason]),
            'url' => $this->approved ? route('lots.show', $this->lot) : route('dashboard.lots'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject(__('notifications.lot_moderated.approved_subject', ['lot' => $this->lot->title]))
                ->line(__('notifications.lot_moderated.approved_line', ['lot' => Markdown::escape($this->lot->title)]))
                ->action(__('notifications.lot_moderated.approved_action'), route('lots.show', $this->lot))
                ->salutation('Aucvy');
        }

        return (new MailMessage)
            ->subject(__('notifications.lot_moderated.rejected_subject', ['lot' => $this->lot->title]))
            ->line(__('notifications.lot_moderated.rejected_line', ['lot' => Markdown::escape($this->lot->title)]))
            ->line(__('notifications.lot_moderated.reason', ['reason' => (string) $this->reason]))
            ->line(__('notifications.lot_moderated.rejected_next'))
            ->action(__('notifications.lot_moderated.rejected_action'), route('dashboard.lots'))
            ->salutation('Aucvy');
    }
}
