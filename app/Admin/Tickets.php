<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Lots\Dossier\Dossier;
use App\Models\Dispute;
use App\Models\LotActivity;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketReply;
use App\Orders\Actions\TransitionOrder;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Wat er met een ticket gebeurt: binnenkomen (formulier, mail), koppelen
 * aan klant, kavel en bestelling, beantwoorden in dezelfde draad, oplossen,
 * en met één handeling doorzetten naar een geschil.
 */
final class Tickets
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * Een nieuw ticket, gekoppeld waar het kan. Een ticket over een kavel
     * komt ook in het dossier.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function open(array $attributes): SupportTicket
    {
        $email = mb_strtolower((string) ($attributes['email'] ?? ''));
        $user = $attributes['user_id'] ?? null ? User::query()->find($attributes['user_id']) : User::query()->where('email', $email)->first();
        $order = isset($attributes['order_number']) && $attributes['order_number'] !== null
            ? Order::query()->where('order_number', $attributes['order_number'])->first()
            : null;

        $ticket = SupportTicket::create([
            ...$attributes,
            'user_id' => $user?->id,
            'order_id' => $order?->id,
            'lot_id' => $attributes['lot_id'] ?? $order?->lot_id,
            'type' => $attributes['type'] ?? $this->guessType((string) ($attributes['subject'] ?? ''), (string) ($attributes['message'] ?? '')),
        ]);

        $ticket->messages()->create([
            'sender_type' => TicketMessage::FROM_CUSTOMER,
            'sender_id' => $user?->id,
            'body' => (string) $attributes['message'],
            'created_at' => now(),
        ]);

        if ($ticket->lot_id !== null) {
            Dossier::record($ticket->lot_id, LotActivity::SOURCE_MESSAGE, 'ticket', LotActivity::STAFF, ['snippet' => $ticket->subject, 'ticket_id' => $ticket->id], $user, $ticket->order_id);
        }

        return $ticket;
    }

    /**
     * Een vervolgbericht van de klant in dezelfde draad (inkomende mail).
     */
    public function append(SupportTicket $ticket, string $body, ?User $user = null): TicketMessage
    {
        $message = $ticket->messages()->create([
            'sender_type' => TicketMessage::FROM_CUSTOMER,
            'sender_id' => $user?->id ?? $ticket->user_id,
            'body' => trim($body),
            'created_at' => now(),
        ]);

        if ($ticket->status === SupportTicket::CLOSED) {
            $ticket->forceFill(['status' => SupportTicket::OPEN, 'closed_at' => null, 'resolved_at' => null])->save();
        }

        return $message;
    }

    /**
     * Een antwoord van een medewerker: naar de klant per mail, in dezelfde
     * draad, en het eerste antwoord telt voor de reactietijd.
     */
    public function reply(SupportTicket $ticket, User $staff, string $body): TicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $staff, $body): TicketMessage {
            $message = $ticket->messages()->create([
                'sender_type' => TicketMessage::FROM_STAFF,
                'sender_id' => $staff->id,
                'body' => trim($body),
                'created_at' => now(),
            ]);

            // Het concept van Ivy is gebruikt of overbodig.
            $ticket->messages()->where('draft', true)->delete();

            $ticket->forceFill([
                'first_response_at' => $ticket->first_response_at ?? now(),
                'assigned_to' => $ticket->assigned_to ?? $staff->id,
            ])->save();

            return $message;
        });

        Notification::route('mail', [$ticket->email => $ticket->name])->notify(new TicketReply($ticket, $message));

        Audit::record('support.replied', $ticket, $staff, after: ['message_id' => $message->id]);

        return $message;
    }

    public function resolve(SupportTicket $ticket, User $staff): void
    {
        $ticket->forceFill(['status' => SupportTicket::CLOSED, 'closed_at' => now(), 'closed_by' => $staff->id, 'resolved_at' => now()])->save();

        Audit::record('support.closed', $ticket, $staff);
    }

    public function toDispute(SupportTicket $ticket, User $staff, string $reason): Dispute
    {
        $order = $ticket->order;

        if ($order === null) {
            throw ValidationException::withMessages(['dispute' => 'Dit ticket hoort niet bij een bestelling; koppel eerst een bestelling.']);
        }

        return DB::transaction(function () use ($ticket, $order, $staff, $reason): Dispute {
            $dispute = Dispute::create([
                'order_id' => $order->id,
                'reported_by' => $ticket->user_id ?? $staff->id,
                'reason' => 'ticket',
                'description' => trim($reason),
                'evidence' => ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->number],
                'status' => DisputeStatus::UnderReview,
                'sla_due_at' => now()->addHours((int) config('aucvy.orders.seller_response_hours')),
            ]);

            if (in_array(OrderStatus::Disputed, $order->status->allowedTransitions(), true)) {
                $this->transition->handle($order, OrderStatus::Disputed, $staff, ['dispute_id' => $dispute->id, 'reason' => 'ticket']);
            }

            $ticket->forceFill(['type' => SupportTicket::TYPE_COMPLAINT])->save();
            $ticket->messages()->create(['sender_type' => TicketMessage::FROM_STAFF, 'sender_id' => $staff->id, 'body' => 'Doorgezet naar geschil #'.$dispute->id.'.', 'internal' => true, 'created_at' => now()]);

            Audit::record('support.to_dispute', $ticket, $staff, after: ['dispute_id' => $dispute->id]);

            return $dispute;
        });
    }

    private function guessType(string $subject, string $message): string
    {
        $text = mb_strtolower($subject.' '.$message);

        foreach (['klacht', 'ontevreden', 'niet ontvangen', 'kapot', 'defect', 'geld terug', 'terugbetal', 'oplichting', 'bedrog'] as $word) {
            if (str_contains($text, $word)) {
                return SupportTicket::TYPE_COMPLAINT;
            }
        }

        return SupportTicket::TYPE_QUESTION;
    }
}
