<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\Actions\TransitionOrder;
use App\Orders\OrderTimeline;
use Illuminate\Support\Facades\Queue;

/*
| De tijdlijn van een bestelling (briefing sectie 3): hamerslag, elke
| statusovergang, de betaling en de uitbetaling, op volgorde en op de
| bestelpagina.
*/

beforeEach(fn () => Queue::fake());

it('bouwt de tijdlijn uit het auditlog, de betalingen en de uitbetaling', function () {
    $order = Order::factory()->create(['status' => OrderStatus::AwaitingPayment]);
    $transition = app(TransitionOrder::class);

    // Elke stap een minuut later, anders vallen de tijdstempels samen.
    $this->travelTo(now()->addMinute());
    $transition->handle($order, OrderStatus::Paid);
    $this->travelTo(now()->addMinute());
    Payment::create(['order_id' => $order->id, 'provider' => 'fake', 'status' => 'paid', 'method' => 'ideal', 'amount_cents' => $order->total_cents])
        ->forceFill(['paid_at' => now()])->save();
    $this->travelTo(now()->addMinute());
    $transition->handle($order->fresh(), OrderStatus::Shipped, $order->seller, ['method' => 'shipped']);
    $this->travelTo(now()->addMinute());
    $transition->handle($order->fresh(), OrderStatus::Delivered, $order->buyer);

    $entries = app(OrderTimeline::class)->for($order->fresh());

    expect($entries->pluck('kind')->all())->toBe(['created', 'paid', 'payment', 'shipped', 'delivered'])
        ->and($entries->firstWhere('kind', 'shipped')['actor'])->toBe($order->seller->name)
        ->and($entries->firstWhere('kind', 'payment')['body'])->toContain('ideal');
});

it('staat op de bestelpagina voor koper en verkoper', function () {
    $order = Order::factory()->create(['status' => OrderStatus::AwaitingPayment]);
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    $this->actingAs($order->buyer)->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Tijdlijn')
        ->assertSee(__('orders.events.created'))
        ->assertSee(__('orders.events.paid'));

    $this->actingAs($order->seller)->get(route('orders.show', $order))->assertOk()->assertSee('Tijdlijn');
});
