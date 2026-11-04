<?php

declare(strict_types=1);

namespace App\Messaging\Jobs;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ConversationUnread;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Ongelezen na vijf minuten: web push, daarna een mail met de kavel erbij.
 * Nooit tijdens een uitzending van de kavel zelf; dan zit de ontvanger er
 * al bovenop.
 */
final class SendUnreadDigest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $conversationId,
        public readonly int $recipientId,
        public readonly int $messageId,
        public readonly string $stage,
    ) {}

    public function handle(): void
    {
        $conversation = Conversation::query()->with(['lot', 'buyer', 'seller', 'company'])->find($this->conversationId);
        $recipient = User::query()->find($this->recipientId);

        if ($conversation === null || $recipient === null || $conversation->isBlocked()) {
            return;
        }

        $readAt = $conversation->isBuyer($recipient) ? $conversation->buyer_read_at : $conversation->seller_read_at;
        $message = $conversation->messages()->find($this->messageId);

        if ($message === null || ($readAt !== null && $readAt->greaterThanOrEqualTo($message->created_at))) {
            return;
        }

        if ($conversation->lot->liveSessionIsRunning ?? false) {
            return;
        }

        if ($this->stage === 'push') {
            if ($recipient->pushSubscriptions()->exists()) {
                $recipient->notify(new ConversationUnread($conversation, $message, 'push'));
            }

            self::dispatch($this->conversationId, $this->recipientId, $this->messageId, 'mail')
                ->delay(now()->addMinutes(max(1, (int) config('messaging.unread_mail_minutes') - (int) config('messaging.unread_push_minutes'))));

            return;
        }

        $recipient->notify(new ConversationUnread($conversation, $message, 'mail'));
    }
}
