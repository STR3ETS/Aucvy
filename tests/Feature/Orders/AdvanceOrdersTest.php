<?php

declare(strict_types=1);

use App\Enums\DisputeStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Orders\Actions\TransitionOrder;

/*
| De klokken van hfst. 9. De zekerheidspagina belooft termijnen; deze tests
| bewijzen dat `orders:advance` ze afdwingt - en vooral dat de vrijgave van
| geld stilstaat zolang er een geschil loopt.
*/

function advance(): void
{
    test()->artisan('orders:advance')->assertSuccessful();
}

it('annuleert een order waarvan de betaaltermijn verstreek', function () {
    $lot = Lot::factory()->create(['status' => LotStatus::Sold]);
    $order = Order::factory()->create([
        'lot_id' => $lot->id,
        'payment_deadline_at' => now()->subHour(),
    ]);

    advance();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($lot->fresh()->status)->toBe(LotStatus::Unsold);
});

it('blijft af van een order binnen de termijn', function () {
    $order = Order::factory()->create(['payment_deadline_at' => now()->addHour()]);

    advance();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

it('levert automatisch na zeven dagen stilte', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);

    $this->travel(8)->days();
    advance();

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered)
        ->and($order->fresh()->dispute_window_ends_at)->not->toBeNull();
});

it('rondt af zodra de meldtermijn zonder melding verstrijkt en geeft daarna de payout vrij', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    // Meldtermijn (48u) én houdtermijn (48u) voorbij.
    $this->travel(49)->hours();
    advance();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->payout->status)->toBe(PayoutStatus::Released)
        ->and($order->payout->released_at)->not->toBeNull();
});

it('houdt de payout vast zolang er een geschil loopt', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    Dispute::create([
        'order_id' => $order->id,
        'reported_by' => $order->buyer_id,
        'reason' => 'damaged',
        'status' => DisputeStatus::Reported,
        'sla_due_at' => now()->addHours(72),
    ]);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Disputed);

    $this->travel(10)->days();
    advance();

    // Geen afronding en géén geld: de order is disputed, de payout blijft
    // in de houdstand, hoe lang het ook duurt.
    expect($order->fresh()->status)->toBe(OrderStatus::Disputed)
        ->and($order->fresh()->payout->status)->toBe(PayoutStatus::Held);
});
