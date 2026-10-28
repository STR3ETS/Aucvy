<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Review;
use App\Notifications\ReviewRequested;
use App\Orders\Actions\TransitionOrder;
use Illuminate\Support\Facades\Notification;

/*
| Het reviewverzoek na afronding (masterbriefing 4.1 en 4.3): één keer, aan
| de koper, met een link naar het beoordelingsformulier.
*/

function completeOrder(Order $order): Order
{
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Completed);

    return $order->fresh();
}

it('nodigt de koper uit zodra de bestelling is afgerond', function () {
    Notification::fake();
    $order = completeOrder(Order::factory()->create());

    Notification::assertSentTo($order->buyer, ReviewRequested::class, function (ReviewRequested $notification) use ($order): bool {
        $mail = $notification->toMail($order->buyer);

        return str_contains($mail->actionUrl, '#beoordeling') && str_contains($mail->subject, $order->lot->title);
    });
    Notification::assertNotSentTo($order->seller, ReviewRequested::class);
});

it('nodigt niet uit als er al een beoordeling staat', function () {
    Notification::fake();
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    Review::forceCreate([
        'order_id' => $order->id, 'reviewer_id' => $order->buyer_id, 'seller_id' => $order->seller_id,
        'company_id' => null, 'rating' => 4, 'body' => 'Prima', 'published_at' => now(),
    ]);

    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Completed);

    Notification::assertNotSentTo($order->buyer, ReviewRequested::class);
});

it('nodigt niet uit bij een annulering', function () {
    Notification::fake();
    $order = Order::factory()->create();

    app(TransitionOrder::class)->handle($order, OrderStatus::Cancelled);

    Notification::assertNotSentTo($order->buyer, ReviewRequested::class);
});
