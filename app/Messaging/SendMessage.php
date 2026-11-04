<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Ivy\StartTask;
use App\Ivy\Tasks\ModerateMessage;
use App\Lots\Dossier\Dossier;
use App\Messaging\Events\MessagePosted;
use App\Messaging\Jobs\SendUnreadDigest;
use App\Messaging\Rules\MessageRules;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LotActivity;
use App\Models\MessageModeration;
use App\Models\User;
use App\Notifications\MessageHeld;
use App\Support\Audit\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Een bericht plaatsen: synchrone regelcheck vóór het opslaan, daarna
 * Ivy op de wachtrij, realtime naar het gesprek, een regel in het dossier
 * en na vijf minuten ongelezen een push.
 *
 * Een chatbericht wijzigt nooit een prijs, status of uitbetaling; alles
 * met gevolgen voor geld loopt via de order.
 */
final class SendMessage
{
    public function __construct(private readonly MessageRules $rules) {}

    public function handle(
        Conversation $conversation,
        User $sender,
        string $body,
        ?UploadedFile $attachment = null,
        ?string $ip = null,
        ?string $lotPart = null,
        ?string $clientKey = null,
    ): ConversationMessage {
        if ($conversation->isBlocked() || $conversation->status !== Conversation::STATUS_OPEN) {
            throw ValidationException::withMessages(['body' => __('messaging.errors.blocked')]);
        }

        if ($clientKey !== null && ($existing = ConversationMessage::query()->where('client_key', $clientKey)->first()) !== null) {
            return $existing;
        }

        $body = trim($body);
        $outcome = $this->rules->evaluate($body, $conversation);
        $senderType = $conversation->isBuyer($sender) ? ConversationMessage::FROM_BUYER : ConversationMessage::FROM_SELLER;

        $message = DB::transaction(function () use ($conversation, $sender, $senderType, $body, $attachment, $outcome, $clientKey): ConversationMessage {
            $message = $conversation->messages()->create([
                'sender_type' => $senderType,
                'sender_id' => $sender->id,
                'body' => $body,
                'body_masked' => $outcome->maskedBody,
                'state' => match ($outcome->action) {
                    MessageModeration::MASK => ConversationMessage::MASKED,
                    MessageModeration::HOLD => ConversationMessage::HELD,
                    MessageModeration::REJECT => ConversationMessage::REJECTED,
                    default => ConversationMessage::VISIBLE,
                },
                'hint' => $outcome->hint,
                'client_key' => $clientKey,
                'created_at' => now(),
                ...$this->storeAttachment($attachment),
            ]);

            if ($outcome->action !== MessageModeration::ALLOW) {
                $message->moderations()->create([
                    'rule_version' => (string) config('messaging.rule_version'),
                    'signal' => $outcome->signal,
                    'confidence' => $outcome->confidence,
                    'action' => $outcome->action,
                    'explanation' => $outcome->explanation,
                    'created_at' => now(),
                ]);
            }

            $conversation->forceFill([
                'last_message_at' => $outcome->blocksDelivery() ? $conversation->last_message_at : now(),
                $senderType === ConversationMessage::FROM_BUYER ? 'buyer_read_at' : 'seller_read_at' => now(),
                'flag' => $outcome->action === MessageModeration::HOLD ? $outcome->signal : ($outcome->action === MessageModeration::FLAG && $conversation->flag === null ? $outcome->signal : $conversation->flag),
                'flagged_at' => in_array($outcome->action, [MessageModeration::HOLD, MessageModeration::FLAG], true) ? now() : $conversation->flagged_at,
            ])->save();

            return $message;
        });

        $this->recordInDossier($conversation, $message, $outcome->action, $sender, $lotPart);

        if ($outcome->blocksDelivery()) {
            $sender->notify(new MessageHeld($message));
            Audit::record('message.'.$outcome->action, $conversation, $sender, after: ['message_id' => $message->id, 'signal' => $outcome->signal], ip: $ip);

            return $message;
        }

        MessagePosted::dispatch($conversation, $message);

        // Ivy leest mee voor wat de regels niet zien (biedafspraak in andere
        // woorden, twijfel); een storing laat het bericht gewoon staan.
        if (config('aucvy.ai.enabled')) {
            StartTask::run(new ModerateMessage($message), $sender);
        }

        $recipient = $senderType === ConversationMessage::FROM_BUYER ? $conversation->seller : $conversation->buyer;

        if ($recipient !== null) {
            SendUnreadDigest::dispatch($conversation->id, $recipient->id, $message->id, 'push')
                ->delay(now()->addMinutes((int) config('messaging.unread_push_minutes')));
        }

        return $message;
    }

    /**
     * @return array<string, string|null>
     */
    private function storeAttachment(?UploadedFile $attachment): array
    {
        if ($attachment === null) {
            return [];
        }

        $path = $attachment->store('messages', 'local');

        return [
            'attachment_path' => $path === false ? null : $path,
            'attachment_name' => $attachment->getClientOriginalName(),
            'attachment_mime' => $attachment->getClientMimeType(),
        ];
    }

    private function recordInDossier(Conversation $conversation, ConversationMessage $message, string $action, User $sender, ?string $lotPart): void
    {
        $type = match ($action) {
            MessageModeration::HOLD, MessageModeration::REJECT => 'held',
            MessageModeration::MASK => 'masked',
            default => $conversation->kind === Conversation::KIND_QUESTION
                ? ($message->sender_type === ConversationMessage::FROM_BUYER ? 'question' : 'answer')
                : 'message',
        };

        Dossier::record(
            lot: $conversation->lot_id,
            source: LotActivity::SOURCE_MESSAGE,
            type: $type,
            visibility: LotActivity::CONVERSATION,
            payload: array_filter(['snippet' => mb_strimwidth($message->bodyFor(null) ?? '', 0, 120, '…'), 'part' => $lotPart ?? $conversation->lot_part, 'conversation_id' => $conversation->id]),
            actor: $sender,
            order: $conversation->order_id,
            userId: $conversation->buyer_id,
        );
    }
}
