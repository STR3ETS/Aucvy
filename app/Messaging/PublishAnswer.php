<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Lots\Dossier\Dossier;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\LotActivity;
use App\Models\LotPublicAnswer;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Een antwoord over staat of inhoud geldt voor alle bieders: de verkoper
 * zet vraag en antwoord bij de kavel, zonder de naam van de vrager.
 */
final class PublishAnswer
{
    public function handle(Conversation $conversation, ConversationMessage $answer, User $seller): LotPublicAnswer
    {
        if (! $conversation->lot->isManagedBy($seller) || $answer->conversation_id !== $conversation->id || $answer->sender_type !== ConversationMessage::FROM_SELLER) {
            throw ValidationException::withMessages(['answer' => __('messaging.errors.nothing_to_publish')]);
        }

        $question = $conversation->messages()
            ->where('sender_type', ConversationMessage::FROM_BUYER)
            ->where('id', '<', $answer->id)
            ->whereIn('state', [ConversationMessage::VISIBLE, ConversationMessage::MASKED])
            ->latest('id')
            ->first();

        if ($question === null) {
            throw ValidationException::withMessages(['answer' => __('messaging.errors.nothing_to_publish')]);
        }

        $published = LotPublicAnswer::query()->updateOrCreate(
            ['conversation_id' => $conversation->id, 'question' => $question->bodyFor(null) ?? $question->body],
            [
                'lot_id' => $conversation->lot_id,
                'lot_part' => $conversation->lot_part,
                'answer' => $answer->bodyFor(null) ?? $answer->body,
                'published_by' => $seller->id,
            ],
        );

        Dossier::record(
            lot: $conversation->lot_id,
            source: LotActivity::SOURCE_MESSAGE,
            type: 'published',
            visibility: LotActivity::PUBLIC,
            payload: ['snippet' => mb_strimwidth($published->question, 0, 120, '…')],
            actor: $seller,
            ref: "answer:{$published->id}",
        );

        return $published;
    }
}
