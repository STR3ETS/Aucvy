<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LiveSession;
use App\Notifications\Concerns\ShowsInApp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Het beeld is weggevallen: de veiling loopt door en bieden blijft
 * werken (beslissing 6); de verkoper hoort het direct.
 */
final class LiveSessionNotice extends Notification
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly LiveSession $session, public readonly string $kind) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class, 'database'];
    }

    protected function inApp(object $notifiable): array
    {
        return [
            'kind' => 'live',
            'title' => 'Je uitzending is weggevallen',
            'body' => 'De veiling loopt gewoon door en bieden blijft werken. Start de uitzending opnieuw vanuit de studio.',
            'url' => route('dashboard.lots.live', $this->session->lot),
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Je uitzending is weggevallen')
            ->body('De veiling loopt door. Start opnieuw vanuit de studio.')
            ->action('Naar de studio', 'open')
            ->data(['url' => route('dashboard.lots.live', $this->session->lot)]);
    }
}
