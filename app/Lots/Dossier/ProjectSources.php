<?php

declare(strict_types=1);

namespace App\Lots\Dossier;

use App\Enums\AuctionEventType;
use App\Models\AuctionEvent;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotDocument;
use App\Models\MailDelivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Vertaalt de bronlogs naar regels in het kaveldossier. Wordt aangeroepen
 * door de observers (live) en door aucvy:dossier-backfill (achteraf); de
 * source_ref voorkomt dat allebei dezelfde regel maken.
 */
final class ProjectSources
{
    /**
     * Auditregels over een kavel, order, betaling, uitbetaling, review of
     * borg. Alleen wat de briefing noemt als tijdlijnbron.
     *
     * @var array<string, array{0: string, 1: string}> action => [type, visibility]
     */
    private const AUDIT = [
        'lot.imported' => ['imported', LotActivity::SELLER],
        'lot.submitted' => ['submitted', LotActivity::SELLER],
        'lot.published' => ['published', LotActivity::PUBLIC],
        'lot.returned' => ['returned', LotActivity::SELLER],
        'lot.escalated' => ['escalated', LotActivity::STAFF],
        'lot.approved' => ['approved', LotActivity::STAFF],
        'lot.rejected' => ['rejected', LotActivity::SELLER],
        'lot.updated' => ['updated', LotActivity::SELLER],
        'lot.media_changed' => ['media_changed', LotActivity::SELLER],
        'lot.paused' => ['paused', LotActivity::PUBLIC],
        'lot.resumed' => ['resumed', LotActivity::PUBLIC],
        'payment.paid' => ['paid', LotActivity::PARTIES],
        'payment.needs_attention' => ['payment_attention', LotActivity::STAFF],
        'payout.released' => ['payout_released', LotActivity::SELLER],
        'payout.corrected' => ['payout_corrected', LotActivity::STAFF],
        'deposit.capture_pending' => ['deposit_pending', LotActivity::STAFF],
        'review.held' => ['review_held', LotActivity::STAFF],
        'review.reported' => ['review_reported', LotActivity::STAFF],
        'review.removed' => ['review_removed', LotActivity::STAFF],
    ];

    public function fromAudit(AuditLog $entry): ?LotActivity
    {
        $mapping = self::AUDIT[$entry->action] ?? null;

        if ($mapping === null) {
            return null;
        }

        $subject = $entry->subject;
        $lotId = $this->lotIdOf($subject);

        if ($lotId === null) {
            return null;
        }

        [$type, $visibility] = $mapping;
        $source = str_starts_with($entry->action, 'lot.') ? LotActivity::SOURCE_LOT : LotActivity::SOURCE_ORDER;
        $after = (array) ($entry->after ?? []);

        $payload = array_filter([
            'reason' => $after['reason'] ?? null,
            'note' => $after['note'] ?? null,
            'issues' => isset($after['issues']) ? array_values(array_map(fn ($issue) => (string) ($issue['message'] ?? $issue), (array) $after['issues'])) : null,
            'by' => $after['by'] ?? $after['approved_by'] ?? null,
            'amount_cents' => $after['amount_cents'] ?? null,
        ], fn ($value) => $value !== null && $value !== []);

        return Dossier::record(
            lot: $lotId,
            source: $source,
            type: $type,
            visibility: $visibility,
            payload: $payload,
            actor: $entry->actor_id === null ? null : User::withTrashed()->find($entry->actor_id),
            order: $this->orderIdOf($subject),
            ref: "audit:{$entry->id}",
            at: $entry->created_at,
            actorType: ($after['by'] ?? $after['approved_by'] ?? null) === 'ivy' ? 'ivy' : null,
        );
    }

    public function fromAuctionEvent(AuctionEvent $event): ?LotActivity
    {
        $auction = $event->auction;

        if ($auction === null) {
            return null;
        }

        $payload = (array) ($event->payload ?? []);

        [$type, $visibility, $data] = match ($event->type) {
            AuctionEventType::BidPlaced => $this->bidPlaced($event, $payload),
            AuctionEventType::Extended => ['extended', LotActivity::PUBLIC, ['ends_at' => $payload['ends_at'] ?? null]],
            AuctionEventType::Closed => [
                ($payload['sold'] ?? false) ? 'sold' : (($payload['withdrawn'] ?? false) ? 'withdrawn' : 'unsold'),
                LotActivity::PUBLIC,
                ['amount_cents' => $payload['final_price_cents'] ?? null, 'count' => $payload['bid_count'] ?? null, 'order_number' => $payload['order_number'] ?? null],
            ],
            AuctionEventType::Cancelled => ['cancelled', LotActivity::PUBLIC, []],
            default => [null, null, []],
        };

        if ($type === null) {
            return null;
        }

        return Dossier::record(
            lot: $auction->lot_id,
            source: LotActivity::SOURCE_AUCTION,
            type: $type,
            visibility: $visibility,
            payload: array_filter($data, fn ($value) => $value !== null),
            ref: "event:{$event->id}",
            at: $event->created_at,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function bidPlaced(AuctionEvent $event, array $payload): array
    {
        $first = Bid::query()->where('auction_id', $event->auction_id)->count() <= 1;

        return [
            $first ? 'first_bid' : 'bid',
            $first ? LotActivity::PUBLIC : LotActivity::STAFF,
            ['amount_cents' => $payload['amount_cents'] ?? null, 'bidder_label' => $payload['bidder_label'] ?? null, 'is_automatic' => $payload['is_automatic'] ?? false],
        ];
    }

    public function orderCreated(Order $order): ?LotActivity
    {
        return Dossier::record(
            lot: $order->lot_id,
            source: LotActivity::SOURCE_ORDER,
            type: 'created',
            visibility: LotActivity::PARTIES,
            payload: ['order_number' => $order->order_number, 'amount_cents' => $order->total_cents],
            order: $order,
            ref: "order:{$order->id}:created",
            at: $order->created_at,
        );
    }

    public function orderStatus(Order $order): ?LotActivity
    {
        $status = $order->status->value;

        return Dossier::record(
            lot: $order->lot_id,
            source: LotActivity::SOURCE_ORDER,
            type: $status,
            visibility: LotActivity::PARTIES,
            payload: ['order_number' => $order->order_number, 'status' => $order->status->label()],
            order: $order,
            ref: "order:{$order->id}:{$status}:".now()->format('YmdHis'),
        );
    }

    public function mail(MailDelivery $delivery, string $type): ?LotActivity
    {
        if ($delivery->lot_id === null) {
            return null;
        }

        return Dossier::record(
            lot: $delivery->lot_id,
            source: LotActivity::SOURCE_MAIL,
            type: $type,
            visibility: $delivery->user_id === null ? LotActivity::STAFF : LotActivity::USER,
            payload: array_filter(['subject' => $delivery->subject, 'kind' => $delivery->kind, 'to' => $delivery->to_email, 'reason' => $delivery->failure_reason]),
            order: $delivery->order_id,
            userId: $delivery->user_id,
            ref: "mail:{$delivery->id}:{$type}",
            at: $delivery->last_event_at ?? $delivery->created_at,
        );
    }

    public function document(LotDocument $document, string $type): ?LotActivity
    {
        return Dossier::record(
            lot: $document->lot_id,
            source: LotActivity::SOURCE_DOCUMENT,
            type: $type,
            visibility: $type === 'checked' ? LotActivity::PARTIES : LotActivity::SELLER,
            payload: ['kind' => $document->kind->label(), 'document_id' => $document->id],
            ref: "document:{$document->id}:{$type}",
            at: $type === 'checked' ? ($document->ai_checked_at ?? now()) : $document->created_at,
            actorType: $type === 'checked' ? 'ivy' : 'user',
        );
    }

    private function lotIdOf(?Model $subject): ?int
    {
        return match (true) {
            $subject instanceof Lot => $subject->id,
            $subject instanceof Order => $subject->lot_id,
            $subject instanceof Payment, $subject instanceof Payout, $subject instanceof Review => $subject->order?->lot_id,
            $subject instanceof Deposit => $subject->auction?->lot_id,
            default => null,
        };
    }

    private function orderIdOf(?Model $subject): ?int
    {
        return match (true) {
            $subject instanceof Order => $subject->id,
            $subject instanceof Payment, $subject instanceof Payout, $subject instanceof Review => $subject->order_id,
            default => null,
        };
    }
}
