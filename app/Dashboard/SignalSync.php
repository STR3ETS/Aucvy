<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\AiAgent;
use App\Enums\BidStatus;
use App\Enums\DisputeStatus;
use App\Enums\InsightSeverity;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\AiInsight;
use App\Models\Auction;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;
use App\Support\Money\Money;

/**
 * De gebeurtenisgedreven signalen op het dashboard (hfst. 13.5).
 *
 * Nadrukkelijk deterministisch PHP en geen AI: "transport ontbreekt" is een
 * query, geen mening. De uitkomsten landen in `ai_insights` zodat de pagina
 * alleen hoeft te lezen; elke kaart draagt de specialist die hem opmerkte en
 * heeft precies één actie.
 *
 * De sync is idempotent: bestaande kaarten blijven staan (mét hun
 * dismissed_at), opgeloste verdwijnen, nieuwe komen erbij. Sleutel is
 * type + action_params.
 */
final class SignalSync
{
    public function sync(User $user): void
    {
        $desired = collect([
            ...$this->outbidAuctions($user),
            ...$this->missingTransport($user),
            ...$this->missingOwnership($user),
            ...$this->unfinishedDrafts($user),
            ...$this->payoutReady($user),
            ...$this->ordersToShip($user),
            ...$this->disputesAwaitingResponse($user),
            ...$this->ordersToPay($user),
            ...$this->deliveriesToConfirm($user),
        ])->keyBy(fn (array $signal): string => $signal['type'].'|'.json_encode($signal['action_params']));

        $existing = AiInsight::query()
            ->where('user_id', $user->id)
            ->where('type', 'like', 'signal.%')
            ->get()
            ->keyBy(fn (AiInsight $insight): string => $insight->type.'|'.json_encode($insight->action_params));

        // Opgelost: weg. Niet "dismissed" - het signaal klópt gewoon niet meer.
        $existing->diffKeys($desired)->each->delete();

        $desired->diffKeys($existing)->each(
            fn (array $signal) => AiInsight::create([...$signal, 'user_id' => $user->id]),
        );
    }

    /**
     * Overboden op een lopende veiling: hét koperssignaal. Verdwijnt vanzelf
     * zodra je weer bovenaan staat of de veiling sluit.
     *
     * @return list<array<string, mixed>>
     */
    private function outbidAuctions(User $user): array
    {
        return Auction::query()
            ->whereHas('bids', fn ($query) => $query->where('user_id', $user->id))
            ->whereDoesntHave('bids', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', BidStatus::Winning))
            ->whereHas('lot', fn ($query) => $query->whereIn('status', [LotStatus::Live, LotStatus::Closing]))
            ->with('lot')
            ->get()
            ->map(fn (Auction $auction): array => [
                'agent' => AiAgent::Atlas,
                'type' => 'signal.outbid',
                'title' => __('dashboard.signals.outbid', ['lot' => $auction->lot->title]),
                'body' => __('dashboard.signals.outbid_body'),
                'action_route' => 'lots.show',
                'action_params' => ['lot' => $auction->lot->slug],
                'action_label' => __('dashboard.actions.view'),
                'severity' => InsightSeverity::Attention,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missingTransport(User $user): array
    {
        return Lot::query()
            ->where('seller_id', $user->id)
            ->where('status', LotStatus::Live)
            ->where('pickup_only', false)
            ->whereNull('transport_from_cents')
            ->get()
            ->map(fn (Lot $lot): array => [
                'agent' => AiAgent::Flow,
                'type' => 'signal.missing_transport',
                'title' => __('dashboard.signals.missing_transport', ['lot' => $lot->title]),
                'body' => __('dashboard.signals.missing_transport_body'),
                'action_route' => 'lots.show',
                'action_params' => ['lot' => $lot->slug],
                'action_label' => __('dashboard.actions.view'),
                'severity' => InsightSeverity::Attention,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function missingOwnership(User $user): array
    {
        return Lot::query()
            ->where('seller_id', $user->id)
            ->where('status', LotStatus::Live)
            ->whereDoesntHave('documents', fn ($query) => $query->where('kind', 'ownership'))
            ->get()
            ->map(fn (Lot $lot): array => [
                'agent' => AiAgent::Lex,
                'type' => 'signal.missing_ownership',
                'title' => __('dashboard.signals.missing_ownership', ['lot' => $lot->title]),
                'body' => __('dashboard.signals.missing_ownership_body'),
                'action_route' => 'lots.show',
                'action_params' => ['lot' => $lot->slug],
                'action_label' => __('dashboard.actions.view'),
                'severity' => InsightSeverity::Attention,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unfinishedDrafts(User $user): array
    {
        $count = Lot::query()
            ->where('seller_id', $user->id)
            ->where('status', LotStatus::Draft)
            ->count();

        if ($count === 0) {
            return [];
        }

        return [[
            'agent' => AiAgent::Ivy,
            'type' => 'signal.unfinished_draft',
            'title' => trans_choice('dashboard.signals.unfinished_draft', $count, ['count' => $count]),
            'body' => __('dashboard.signals.unfinished_draft_body'),
            'action_route' => 'sell',
            'action_params' => [],
            'action_label' => __('dashboard.actions.continue_draft'),
            'severity' => InsightSeverity::Info,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payoutReady(User $user): array
    {
        $totalCents = (int) Payout::query()
            ->where('seller_id', $user->id)
            ->where('status', PayoutStatus::Released)
            ->sum('amount_cents');

        if ($totalCents === 0) {
            return [];
        }

        return [[
            'agent' => AiAgent::Finn,
            'type' => 'signal.payout_ready',
            'title' => __('dashboard.signals.payout_ready', ['amount' => Money::format($totalCents)]),
            'body' => __('dashboard.signals.payout_ready_body'),
            'action_route' => 'orders.index',
            'action_params' => [],
            'action_label' => __('dashboard.actions.view'),
            'severity' => InsightSeverity::Info,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ordersToShip(User $user): array
    {
        return Order::query()
            ->where('seller_id', $user->id)
            ->where('status', OrderStatus::Paid)
            ->with('lot')
            ->get()
            ->map(fn (Order $order): array => [
                'agent' => AiAgent::Flow,
                'type' => 'signal.order_to_ship',
                'title' => __('dashboard.signals.order_to_ship', ['lot' => $order->lot->title]),
                'body' => __('dashboard.signals.order_to_ship_body'),
                'action_route' => 'orders.show',
                'action_params' => ['order' => $order->order_number],
                'action_label' => __('dashboard.actions.arrange'),
                'severity' => InsightSeverity::Urgent,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function disputesAwaitingResponse(User $user): array
    {
        return Dispute::query()
            ->where('status', DisputeStatus::Reported)
            ->whereHas('order', fn ($query) => $query->where('seller_id', $user->id))
            ->with('order')
            ->get()
            ->map(fn (Dispute $dispute): array => [
                'agent' => AiAgent::Lex,
                'type' => 'signal.dispute_response',
                'title' => __('dashboard.signals.dispute_response', ['order' => $dispute->order->order_number]),
                'body' => __('dashboard.signals.dispute_response_body', [
                    'due' => $dispute->sla_due_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm') ?? '-',
                ]),
                'action_route' => 'orders.show',
                'action_params' => ['order' => $dispute->order->order_number],
                'action_label' => __('dashboard.actions.respond'),
                'severity' => InsightSeverity::Urgent,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ordersToPay(User $user): array
    {
        return Order::query()
            ->where('buyer_id', $user->id)
            ->where('status', OrderStatus::AwaitingPayment)
            ->with('lot')
            ->get()
            ->map(fn (Order $order): array => [
                'agent' => AiAgent::Finn,
                'type' => 'signal.order_to_pay',
                'title' => __('dashboard.signals.order_to_pay', ['lot' => $order->lot->title]),
                'body' => __('dashboard.signals.order_to_pay_body', [
                    'deadline' => $order->payment_deadline_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm') ?? '-',
                ]),
                'action_route' => 'orders.show',
                'action_params' => ['order' => $order->order_number],
                'action_label' => __('dashboard.actions.pay'),
                'severity' => InsightSeverity::Urgent,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deliveriesToConfirm(User $user): array
    {
        return Order::query()
            ->where('buyer_id', $user->id)
            ->whereIn('status', [OrderStatus::ReadyForPickup, OrderStatus::Shipped])
            ->with('lot')
            ->get()
            ->map(fn (Order $order): array => [
                'agent' => AiAgent::Flow,
                'type' => 'signal.delivery_confirm',
                'title' => __('dashboard.signals.delivery_confirm', ['lot' => $order->lot->title]),
                'body' => __('dashboard.signals.delivery_confirm_body'),
                'action_route' => 'orders.show',
                'action_params' => ['order' => $order->order_number],
                'action_label' => __('dashboard.actions.confirm'),
                'severity' => InsightSeverity::Attention,
            ])
            ->all();
    }
}
