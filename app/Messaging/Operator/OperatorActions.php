<?php

declare(strict_types=1);

namespace App\Messaging\Operator;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Lots\Dossier\Dossier;
use App\Messaging\Events\MessagePosted;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Dispute;
use App\Models\LotActivity;
use App\Models\User;
use App\Notifications\SellerWarned;
use App\Orders\Actions\TransitionOrder;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wat een medewerker in een gesprek kan (briefing nieuwe functies):
 * meelezen met reden, een tegengehouden bericht vrijgeven of bevestigen,
 * reageren als Aucvy-medewerker (zichtbaar voor beide partijen) of een
 * interne notitie maken, en de zaak omzetten naar een geschil met het
 * gesprek als bewijs. Elke inzage komt in het auditlog.
 */
final class OperatorActions
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * Openen: zonder vlag, melding of geschil alleen met een reden.
     */
    public function open(Conversation $conversation, User $staff, ?string $reason, ?string $ip = null): void
    {
        $hasCause = $conversation->flag !== null || $conversation->reported_at !== null || $conversation->dispute_id !== null
            || ($conversation->order !== null && $conversation->order->status === OrderStatus::Disputed);

        if (! $hasCause && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages(['reason' => __('messaging.errors.reason_required')]);
        }

        $conversation->accessLogs()->create([
            'staff_id' => $staff->id,
            'reason' => $hasCause ? null : trim((string) $reason),
            'opened_at' => now(),
        ]);

        Audit::record('conversation.opened', $conversation, $staff, after: ['reason' => $hasCause ? 'flag' : trim((string) $reason)], ip: $ip);
    }

    public function release(Conversation $conversation, ConversationMessage $message, User $staff, ?string $ip = null): void
    {
        DB::transaction(function () use ($conversation, $message, $staff): void {
            $message->forceFill(['state' => ConversationMessage::VISIBLE])->save();
            $message->moderations()->whereNull('reviewed_at')->update(['reviewed_by' => $staff->id, 'reviewed_at' => now(), 'review_action' => 'released']);
            $conversation->forceFill(['last_message_at' => now(), 'flag' => null, 'flagged_at' => null])->save();
        });

        MessagePosted::dispatch($conversation, $message);
        Audit::record('message.released', $conversation, $staff, after: ['message_id' => $message->id], ip: $ip);
        $this->record($conversation, 'released', $staff);
    }

    public function confirm(Conversation $conversation, ConversationMessage $message, User $staff, ?string $ip = null): void
    {
        DB::transaction(function () use ($conversation, $message, $staff): void {
            $message->forceFill(['state' => ConversationMessage::REJECTED])->save();
            $message->moderations()->whereNull('reviewed_at')->update(['reviewed_by' => $staff->id, 'reviewed_at' => now(), 'review_action' => 'confirmed']);
            $conversation->forceFill(['flag' => null, 'flagged_at' => null])->save();
        });

        Audit::record('message.confirmed', $conversation, $staff, after: ['message_id' => $message->id], ip: $ip);
    }

    /**
     * Reageren als medewerker: beide partijen zien het, en zien vanaf nu
     * dat er iemand meeleest.
     */
    public function reply(Conversation $conversation, User $staff, string $body, ?string $ip = null): ConversationMessage
    {
        $message = DB::transaction(function () use ($conversation, $staff, $body): ConversationMessage {
            if (! $conversation->hasOperator()) {
                $conversation->forceFill(['operator_id' => $staff->id, 'operator_joined_at' => now()])->save();

                $conversation->messages()->create([
                    'sender_type' => ConversationMessage::FROM_SYSTEM,
                    'body' => __('messaging.notices.operator_joined'),
                    'state' => ConversationMessage::VISIBLE,
                    'created_at' => now(),
                ]);
            }

            $message = $conversation->messages()->create([
                'sender_type' => ConversationMessage::FROM_OPERATOR,
                'sender_id' => $staff->id,
                'body' => trim($body),
                'state' => ConversationMessage::VISIBLE,
                'created_at' => now(),
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            return $message;
        });

        MessagePosted::dispatch($conversation, $message);
        Audit::record('conversation.replied', $conversation, $staff, after: ['message_id' => $message->id], ip: $ip);
        $this->record($conversation, 'operator_reply', $staff);

        return $message;
    }

    public function note(Conversation $conversation, User $staff, string $body): ConversationMessage
    {
        return $conversation->messages()->create([
            'sender_type' => ConversationMessage::FROM_OPERATOR,
            'sender_id' => $staff->id,
            'body' => trim($body),
            'state' => ConversationMessage::VISIBLE,
            'internal' => true,
            'created_at' => now(),
        ]);
    }

    /**
     * Omzetten naar een geschil: de bestaande geschilstroom, met het
     * gesprek als bewijs. Alleen bij een verkochte kavel met order.
     */
    public function toDispute(Conversation $conversation, User $staff, string $reason, ?string $ip = null): Dispute
    {
        $order = $conversation->order;

        if ($order === null || $conversation->dispute_id !== null) {
            throw ValidationException::withMessages(['dispute' => __('orders.errors.dispute_window_closed')]);
        }

        $dispute = DB::transaction(function () use ($conversation, $order, $staff, $reason): Dispute {
            $dispute = Dispute::create([
                'order_id' => $order->id,
                'reported_by' => $staff->id,
                'reason' => 'conversation',
                'description' => trim($reason),
                'evidence' => ['conversation_id' => $conversation->id],
                'status' => DisputeStatus::UnderReview,
                'sla_due_at' => now()->addHours((int) config('aucvy.orders.seller_response_hours')),
            ]);

            // De order alleen op "geschil" als de statusmachine dat toestaat;
            // het geschil zelf bestaat hoe dan ook, met het gesprek als bewijs.
            if (in_array(OrderStatus::Disputed, $order->status->allowedTransitions(), true)) {
                $this->transition->handle($order, OrderStatus::Disputed, $staff, ['dispute_id' => $dispute->id, 'reason' => 'conversation']);
            }

            $conversation->forceFill(['dispute_id' => $dispute->id, 'flag' => null])->save();

            return $dispute;
        });

        Audit::record('conversation.dispute', $conversation, $staff, after: ['dispute_id' => $dispute->id], ip: $ip);

        return $dispute;
    }

    public function warnSeller(Conversation $conversation, User $staff, string $note, ?string $ip = null): void
    {
        $conversation->seller?->notify(new SellerWarned($conversation, $note));

        Audit::record('conversation.seller_warned', $conversation, $staff, after: ['note' => $note], ip: $ip);
    }

    public function report(Conversation $conversation, User $reporter, ?string $note): void
    {
        $conversation->forceFill([
            'reported_by' => $reporter->id,
            'reported_at' => now(),
            'report_note' => $note,
            'flag' => $conversation->flag ?? 'reported',
            'flagged_at' => $conversation->flagged_at ?? now(),
        ])->save();

        Audit::record('conversation.reported', $conversation, $reporter, after: ['note' => $note]);
    }

    public function block(Conversation $conversation, User $by): void
    {
        $conversation->forceFill(['blocked_by' => $by->id, 'blocked_at' => now(), 'status' => Conversation::STATUS_CLOSED])->save();

        Audit::record('conversation.blocked', $conversation, $by);
    }

    private function record(Conversation $conversation, string $type, User $staff): void
    {
        Dossier::record(
            lot: $conversation->lot_id,
            source: LotActivity::SOURCE_MESSAGE,
            type: $type,
            visibility: LotActivity::CONVERSATION,
            payload: ['conversation_id' => $conversation->id],
            actor: $staff,
            order: $conversation->order_id,
            userId: $conversation->buyer_id,
        );
    }
}
