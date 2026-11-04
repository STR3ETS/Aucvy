<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Een ongelezen bericht: eerst een push (als er een apparaat is), daarna
 * een mail met de kavel erbij. De tekst van het bericht gaat niet mee in
 * de push; wel wie er schreef en over welke kavel.
 */
final class ConversationUnread extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public readonly Lot $lot;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ConversationMessage $message,
        public readonly string $stage,
    ) {
        $this->lot = $conversation->lot;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->stage === 'push' ? [WebPushChannel::class, 'database'] : ['mail'];
    }

    protected function inApp(object $notifiable): array
    {
        return [
            'kind' => 'message',
            'title' => __('notifications.conversation_unread.subject', ['from' => $this->fromLabel($notifiable), 'lot' => $this->lot->title]),
            'body' => mb_strimwidth($this->message->bodyFor($notifiable) ?? '', 0, 120, '…'),
            'url' => route('messages.show', $this->conversation),
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('notifications.conversation_unread.subject', ['from' => $this->fromLabel($notifiable), 'lot' => $this->lot->title]))
            ->body(__('notifications.conversation_unread.push'))
            ->action(__('notifications.conversation_unread.action'), 'open')
            ->data(['url' => route('messages.show', $this->conversation)]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.conversation_unread.subject', ['from' => $this->fromLabel($notifiable), 'lot' => $this->lot->title]))
            ->line(__('notifications.conversation_unread.line', ['from' => Markdown::escape($this->fromLabel($notifiable)), 'lot' => Markdown::escape((string) $this->lot->title)]))
            ->line('"'.Markdown::escape(mb_strimwidth($this->message->bodyFor($notifiable) ?? '', 0, 200, '…')).'"')
            ->action(__('notifications.conversation_unread.action'), route('messages.show', $this->conversation))
            ->salutation('Aucvy');
    }

    private function fromLabel(object $notifiable): string
    {
        return $notifiable instanceof User ? $this->conversation->counterpartLabel($notifiable) : 'Aucvy';
    }
}
