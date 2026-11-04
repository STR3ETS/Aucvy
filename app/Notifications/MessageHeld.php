<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ConversationMessage;
use App\Notifications\Concerns\ShowsInApp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * De bot hield een bericht vast of weigerde het: de afzender krijgt
 * meteen uitleg, in de app. De bot blokkeert alleen in duidelijke gevallen
 * en legt elke keer uit waarom.
 */
final class MessageHeld extends Notification
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly ConversationMessage $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    protected function inApp(object $notifiable): array
    {
        $moderation = $this->message->moderations()->latest('id')->first();

        return [
            'kind' => 'message',
            'title' => $this->message->state === ConversationMessage::REJECTED ? __('messaging.status.rejected') : __('messaging.status.held'),
            'body' => (string) ($moderation?->explanation ?? ''),
            'url' => route('messages.show', $this->message->conversation_id),
        ];
    }
}
