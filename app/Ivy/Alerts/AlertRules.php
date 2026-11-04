<?php

declare(strict_types=1);

namespace App\Ivy\Alerts;

use App\Auctions\Events\AuctionClosed;
use App\Auctions\Events\BidPlaced;
use App\Auctions\Events\Outbid;
use App\Enums\OrderStatus;
use App\Models\IvyAlert;
use App\Models\IvyConversation;
use App\Models\IvyLotMode;
use App\Models\Order;
use App\Support\Money\Money;
use App\Support\PlatformFacts;
use Illuminate\Support\Facades\Cache;

/**
 * De regels op domeinevents (Ivy-briefing hfst. 8). Elke alert heeft een
 * vaste tekst; Ivy mag later een zin advies toevoegen, maar de alert wacht
 * daar niet op. Luistert op Outbid, BidPlaced en AuctionClosed; de
 * orderstatus komt via de observer.
 */
final class AlertRules
{
    public function __construct(private readonly Alerts $alerts) {}

    public function handleOutbid(Outbid $event): void
    {
        $lot = $event->auction->lot;
        $user = $event->user;
        $ends = $event->auction->ends_at->timezone(config('aucvy.display_timezone'));
        $mode = IvyLotMode::for($user, $lot);

        if ($mode === IvyLotMode::AUTO) {
            // Het autobod werkt zelf; alleen bij de limiet volgt een alert (zie handleLimitReached).
            return;
        }

        $this->alerts->raise(
            user: $user,
            key: 'outbid',
            role: IvyConversation::BUYER,
            payload: [
                'title' => 'Je bent overboden op '.$lot->title,
                'body' => 'Het bod staat op '.Money::format($event->newPriceCents).'. Sluit '.$ends->isoFormat('D MMM, HH:mm').'.',
                'url' => route('lots.show', $lot),
            ],
            choices: [
                ['key' => 'raise', 'label' => 'Verhoog', 'primary' => true],
                ['key' => 'auto', 'label' => 'Automatisch tot limiet'],
                ['key' => 'let_go', 'label' => 'Laat gaan'],
            ],
            channels: ['inapp'],
            lot: $lot,
            expiresAt: $event->auction->ends_at,
            urgent: $mode === IvyLotMode::ASK,
        );
    }

    public function handleBidPlaced(BidPlaced $event): void
    {
        $lot = $event->auction->lot;
        $seller = $lot->seller;

        if ($seller === null) {
            return;
        }

        // Gebundeld: maximaal één push per 15 minuten; de alert zelf werkt bij.
        $this->alerts->raise(
            user: $seller,
            key: 'new_bid',
            role: IvyConversation::SELLER,
            payload: [
                'title' => 'Nieuw bod op '.$lot->title,
                'body' => $event->bid->user?->publicName().' biedt '.Money::format($event->bid->amount_cents).'. Het '.$event->auction->bid_count.'e bod.',
                'url' => route('dashboard.lots.show', $lot),
            ],
            choices: [['key' => 'view', 'label' => 'Bekijk verloop', 'primary' => true, 'url' => route('dashboard.lots.show', $lot)]],
            channels: Cache::add("ivy-bid-bundle:{$seller->id}:{$lot->id}", 1, (int) config('aucvy.ivy.bundle_minutes', 15) * 60) ? ['inapp', 'push'] : ['inapp'],
            lot: $lot,
            level: IvyAlert::INFO,
            expiresAt: $event->auction->ends_at,
        );
    }

    public function handleAuctionClosed(AuctionClosed $event): void
    {
        $auction = $event->auction;
        $lot = $auction->lot;
        $order = $event->order;

        if ($order !== null && $order->buyer !== null) {
            $this->alerts->raise(
                user: $order->buyer,
                key: 'won',
                role: IvyConversation::BUYER,
                payload: ['title' => 'Gewonnen: '.$lot->title, 'body' => 'Totaal '.Money::format($order->total_cents).' excl. btw, graag betalen vóór '.$order->payment_deadline_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm').'.', 'url' => route('orders.show', $order)],
                choices: [['key' => 'pay', 'label' => 'Betalen', 'primary' => true, 'url' => route('orders.show', $order)], ['key' => 'costs', 'label' => 'Kosten bekijken', 'url' => route('orders.show', $order)]],
                channels: ['inapp', 'push'],
                lot: $lot,
                order: $order,
                urgent: true,
            );
        }

        // Verliezers: de alert komt de dag erna per mail (reeks 6); nu alleen in de inbox, zonder haast.
        $losers = $auction->bids()->with('user')->get()->pluck('user')->filter()->unique('id')->reject(fn ($user) => $order !== null && $user->id === $order->buyer_id);

        foreach ($losers as $user) {
            $this->alerts->raise(
                user: $user,
                key: 'lost',
                role: IvyConversation::BUYER,
                payload: ['title' => 'Niet gewonnen: '.$lot->title, 'body' => $order !== null ? 'Deze ging voor '.Money::format($auction->current_price_cents).'. Ik zoek verder naar vergelijkbare kavels.' : 'De kavel is niet verkocht.', 'url' => route('lots.index')],
                choices: [['key' => 'view', 'label' => 'Bekijk', 'url' => route('lots.show', $lot)], ['key' => 'keep_searching', 'label' => 'Blijf zoeken', 'primary' => true, 'url' => route('lots.index')], ['key' => 'stop', 'label' => 'Stop']],
                channels: ['inapp'],
                lot: $lot,
                level: IvyAlert::INFO,
            );
        }

        if ($lot->seller !== null) {
            $this->alerts->raise(
                user: $lot->seller,
                key: $order !== null ? 'sold' : 'unsold',
                role: IvyConversation::SELLER,
                payload: [
                    'title' => $order !== null ? 'Verkocht: '.$lot->title : 'Niet verkocht: '.$lot->title,
                    'body' => $order !== null ? 'Verkocht voor '.Money::format($order->hammer_cents).'. De koper heeft nu '.PlatformFacts::paymentDeadlineHours().' uur om te betalen.' : 'Er is geen koper gevonden. Opnieuw plaatsen kost niets.',
                    'url' => $order !== null ? route('orders.show', $order) : route('dashboard.lots.show', $lot),
                ],
                choices: $order !== null
                    ? [['key' => 'view_order', 'label' => 'Bekijk order', 'primary' => true, 'url' => route('orders.show', $order)]]
                    : [['key' => 'relist', 'label' => 'Opnieuw plaatsen', 'primary' => true, 'url' => route('dashboard.lots.edit', $lot)], ['key' => 'later', 'label' => 'Later']],
                channels: ['inapp', 'push'],
                lot: $lot,
                order: $order,
                level: $order !== null ? IvyAlert::INFO : IvyAlert::TASK,
            );
        }
    }

    /**
     * Voortgang van een order voor de koper, betaling en uitbetaling voor de verkoper.
     */
    public function handleOrderStatus(Order $order): void
    {
        $lot = $order->lot;

        if ($lot === null) {
            return;
        }

        match ($order->status) {
            OrderStatus::Paid => $this->paid($order),
            OrderStatus::ReadyForPickup, OrderStatus::Shipped => $this->progress($order, 'Betaling ontvangen. Kies nog een ophaalmoment of bevestig de ontvangst zodra de kavel binnen is.'),
            OrderStatus::Delivered => $this->progress($order, 'Geleverd. Bevestig de ontvangst; daarna heb je '.PlatformFacts::disputeWindowHours().' uur om een probleem te melden.'),
            OrderStatus::Completed => $this->payout($order),
            default => null,
        };
    }

    private function paid(Order $order): void
    {
        if ($order->seller !== null) {
            $this->alerts->raise(
                user: $order->seller,
                key: 'payment_received',
                role: IvyConversation::SELLER,
                payload: ['title' => 'Betaling binnen voor '.$order->lot->title, 'body' => 'De koper heeft betaald. Het geld blijft bij de betaalpartij tot de koper heeft ontvangen.', 'url' => route('orders.show', $order)],
                choices: [['key' => 'plan', 'label' => 'Afgifte plannen', 'primary' => true, 'url' => route('orders.show', $order)], ['key' => 'buyer', 'label' => 'Bekijk koper', 'url' => route('orders.show', $order)]],
                channels: ['inapp', 'push'],
                lot: $order->lot,
                order: $order,
            );
        }

        $this->progress($order, 'Betaling ontvangen. Kies nog een ophaalmoment of transport.');
    }

    private function progress(Order $order, string $body): void
    {
        if ($order->buyer === null) {
            return;
        }

        $this->alerts->raise(
            user: $order->buyer,
            key: 'progress',
            role: IvyConversation::BUYER,
            payload: ['title' => 'Voortgang: '.$order->lot->title, 'body' => $body, 'url' => route('orders.show', $order)],
            choices: [['key' => 'moment', 'label' => 'Kies moment', 'primary' => true, 'url' => route('orders.show', $order)], ['key' => 'ask', 'label' => 'Vraag stellen', 'url' => route('orders.show', $order)]],
            channels: ['inapp'],
            lot: $order->lot,
            order: $order,
            level: IvyAlert::INFO,
            dedupe: $order->buyer_id.':progress:'.$order->id.':'.$order->status->value,
        );
    }

    private function payout(Order $order): void
    {
        if ($order->seller === null) {
            return;
        }

        $payout = $order->payout()->first();

        $this->alerts->raise(
            user: $order->seller,
            key: 'payout',
            role: IvyConversation::SELLER,
            payload: ['title' => 'Uitbetaling voor '.$order->lot->title, 'body' => 'Ontvangst bevestigd. '.($payout !== null ? Money::format($payout->amount_cents).' gaat' : 'Je opbrengst gaat').' na de meldtermijn naar je rekening.', 'url' => route('dashboard.payouts')],
            choices: [['key' => 'overview', 'label' => 'Overzicht', 'primary' => true, 'url' => route('dashboard.payouts')], ['key' => 'invoice', 'label' => 'Factuur', 'url' => route('dashboard.finance')]],
            channels: ['inapp'],
            lot: $order->lot,
            order: $order,
            level: IvyAlert::INFO,
        );
    }
}
