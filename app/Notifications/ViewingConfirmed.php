<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Viewing;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De bevestiging naar de koper - dit is de énige plek waar het adres van de
 * kavel gedeeld wordt (hfst. 7): per mail, na bevestiging van de verkoper.
 */
final class ViewingConfirmed extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly Viewing $viewing)
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
        return [
            'kind' => 'viewing',
            'title' => __('notifications.viewing_confirmed.buyer.subject', ['lot' => $this->viewing->lot->title]),
            'body' => __('notifications.viewing_confirmed.buyer.line', ['moment' => $this->viewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('dddd D MMMM, HH:mm')]),
            'url' => route('lots.show', $this->viewing->lot),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $location = $this->viewing->lot->location;

        $replace = [
            'lot' => $this->viewing->lot->title,
            'moment' => $this->viewing->slot_at
                ->timezone(config('aucvy.display_timezone'))
                ->isoFormat('dddd D MMMM, HH:mm'),
            'address' => $location !== null
                ? trim("{$location->street} {$location->house_number}, {$location->postal_code} {$location->city}")
                : 'volgt van de verkoper',
        ];

        return (new MailMessage)
            ->subject(__('notifications.viewing_confirmed.buyer.subject', $replace))
            ->line(__('notifications.viewing_confirmed.buyer.line', Markdown::escapeValues($replace)))
            ->line(__('notifications.viewing_confirmed.buyer.address', Markdown::escapeValues($replace)))
            ->action(__('notifications.viewing_confirmed.buyer.action'), route('lots.show', $this->viewing->lot))
            ->salutation('Aucvy');
    }
}
