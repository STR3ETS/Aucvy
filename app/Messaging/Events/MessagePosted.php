<?php

declare(strict_types=1);

namespace App\Messaging\Events;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Realtime naar het gesprek via Reverb, kanaal private-conversation.{id}.
 * Alleen een seintje met het id: de tekst haalt de client op met zijn
 * eigen rechten, zodat een gemaskeerd of vastgehouden bericht nooit via
 * de websocket lekt.
 */
final class MessagePosted implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public readonly Conversation $conversation, public readonly ConversationMessage $message) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.'.$this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    /**
     * @return array{id: int, conversation_id: int, sender_type: string}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->message->id, 'conversation_id' => $this->conversation->id, 'sender_type' => $this->message->sender_type];
    }
}
