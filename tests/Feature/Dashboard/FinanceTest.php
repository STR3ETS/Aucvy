<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\TransitionOrder;
use Illuminate\Support\Facades\Queue;

/*
| Financiën voor de verkoper (briefing sectie 4): per verkoop hamerbedrag,
| fee met btw en uitbetaling, totalen per maand en een csv.
*/

beforeEach(fn () => Queue::fake());

it('toont de afrekening per verkoop met totalen en per maand', function () {
    $seller = User::factory()->verified()->create();
    $order = Order::factory()->create(['seller_id' => $seller->id, 'status' => OrderStatus::AwaitingPayment, 'hammer_cents' => 100_000]);
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    Order::factory()->create(['seller_id' => $seller->id, 'status' => OrderStatus::AwaitingPayment, 'hammer_cents' => 999_999]);

    $this->actingAs($seller)->get(route('dashboard.finance'))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('€ 1.000,00')
        ->assertSee('Per maand')
        ->assertDontSee('€ 9.999,99');
});

it('levert een csv met puntkomma\'s en Nederlandse bedragen', function () {
    $seller = User::factory()->verified()->create();
    $order = Order::factory()->create(['seller_id' => $seller->id, 'status' => OrderStatus::AwaitingPayment, 'hammer_cents' => 123_456]);
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    $response = $this->actingAs($seller)->get(route('dashboard.finance.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->getContent())->toContain('"Bestelnummer";"Datum";"Kavel";"Hamerbedrag"')
        ->and($response->getContent())->toContain($order->order_number)
        ->and($response->getContent())->toContain('"1234,56"');
});

it('is alleen van de verkoper zelf', function () {
    $seller = User::factory()->verified()->create();
    $order = Order::factory()->create(['seller_id' => $seller->id, 'status' => OrderStatus::AwaitingPayment]);
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    $this->actingAs(User::factory()->create())->get(route('dashboard.finance'))
        ->assertOk()
        ->assertDontSee($order->order_number);
});
