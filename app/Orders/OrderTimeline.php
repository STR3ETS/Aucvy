<?php

declare(strict_types=1);

namespace App\Orders;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * De tijdlijn van een bestelling (briefing sectie 3: /app/orders/{id}):
 * hamerslag, elke statusovergang uit het auditlog, betalingen, geschil en
 * uitbetaling, op volgorde. Alleen wat er echt gebeurd is; de volgende stap
 * staat apart in het statusblok.
 *
 * @phpstan-type Entry array{at: CarbonInterface, kind: string, title: string, body: string|null, actor: string|null}
 */
final class OrderTimeline
{
    /**
     * @return Collection<int, Entry>
     */
    public function for(Order $order): Collection
    {
        $order->loadMissing(['lot', 'payments', 'disputes', 'payout', 'auction']);

        $entries = collect([[
            'at' => $order->created_at,
            'kind' => 'created',
            'title' => __('orders.events.created'),
            'body' => __('orders.events.created_body', ['amount' => Money::format($order->hammer_cents)]),
            'actor' => null,
        ]]);

        AuditLog::query()
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->where('action', 'order.transition')
            ->with('actor')
            ->orderBy('id')
            ->get()
            ->each(function (AuditLog $entry) use ($entries, $order): void {
                $status = OrderStatus::tryFrom((string) ($entry->after['status'] ?? ''));

                if ($status === null) {
                    return;
                }

                $entries->push([
                    'at' => $entry->created_at,
                    'kind' => $status->value,
                    'title' => __('orders.events.'.$status->value),
                    'body' => $this->transitionBody($status, $entry->after ?? [], $order),
                    'actor' => $entry->actor?->name,
                ]);
            });

        $order->payments
            ->filter(fn (Payment $payment): bool => $payment->paid_at !== null)
            ->each(fn (Payment $payment) => $entries->push([
                'at' => $payment->paid_at,
                'kind' => 'payment',
                'title' => __('orders.events.payment'),
                'body' => __('orders.events.payment_body', ['amount' => Money::format($payment->amount_cents), 'method' => $payment->method ?? $payment->provider]),
                'actor' => null,
            ]));

        $order->disputes->each(function (Dispute $dispute) use ($entries): void {
            if ($dispute->seller_responded_at !== null) {
                $entries->push([
                    'at' => $dispute->seller_responded_at,
                    'kind' => 'dispute_response',
                    'title' => __('orders.events.dispute_response'),
                    'body' => $dispute->seller_response,
                    'actor' => null,
                ]);
            }

            if ($dispute->resolved_at !== null) {
                $entries->push([
                    'at' => $dispute->resolved_at,
                    'kind' => 'dispute_resolved',
                    'title' => __('orders.events.dispute_resolved'),
                    'body' => trim(str_replace('_', ' ', (string) $dispute->resolution).'. '.(string) $dispute->resolution_notes),
                    'actor' => null,
                ]);
            }
        });

        if ($order->payout?->released_at !== null) {
            $entries->push([
                'at' => $order->payout->released_at,
                'kind' => 'payout',
                'title' => __('orders.events.payout'),
                'body' => __('orders.events.payout_body', ['amount' => Money::format($order->payout->amount_cents)]),
                'actor' => null,
            ]);
        }

        return $entries->sortBy(fn (array $entry): int => $entry['at']->getTimestamp())->values();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function transitionBody(OrderStatus $status, array $context, Order $order): ?string
    {
        return match ($status) {
            OrderStatus::ReadyForPickup, OrderStatus::Shipped => isset($context['method']) ? __('orders.events.method_'.$context['method']) : null,
            OrderStatus::Delivered => isset($context['auto']) && $context['auto'] ? __('orders.events.delivered_auto') : null,
            OrderStatus::Disputed => isset($context['reason']) ? __('orders.events.disputed_body', ['reason' => str_replace('_', ' ', (string) $context['reason'])]) : null,
            OrderStatus::Cancelled => isset($context['reason']) ? (string) $context['reason'] : null,
            OrderStatus::Completed => $order->payout?->hold_until !== null
                ? __('orders.events.completed_body', ['until' => $order->payout->hold_until->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm')])
                : null,
            default => null,
        };
    }
}
