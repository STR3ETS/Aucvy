<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DisputeResolved;
use App\Notifications\OrderCreated;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PayoutReleased;
use App\Orders\Actions\ReportProblem;
use App\Orders\Actions\ResolveDispute;
use App\Orders\Actions\TransitionOrder;
use Illuminate\Support\Facades\Notification;

/*
| "Elke overgang stuurt beide partijen een bericht" (hfst. 9). Deze tests
| pinnen vast wíe wat krijgt - en net zo belangrijk: dat de teksten bestaan,
| want een ontbrekende taalregel is een mail met een kale sleutel erin.
*/

beforeEach(function () {
    Notification::fake();
});

it('meldt de hamerslag aan koper én verkoper, met de betaaldeadline', function () {
    $order = Order::factory()->create();

    Notification::assertSentTo($order->buyer, OrderCreated::class, function (OrderCreated $mail) use ($order): bool {
        $rendered = $mail->toMail($order->buyer);

        return $mail->role === 'buyer'
            && str_contains($rendered->subject, $order->lot->title)
            && str_contains(implode(' ', $rendered->introLines), 'vervalt de koop');
    });

    Notification::assertSentTo($order->seller, OrderCreated::class, fn (OrderCreated $mail): bool => $mail->role === 'seller');
});

it('stuurt bij elke overgang de juiste rollen een bericht', function () {
    $order = Order::factory()->create();
    Notification::fake();

    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    Notification::assertSentTo($order->buyer, OrderStatusChanged::class, fn ($mail): bool => $mail->role === 'buyer' && $mail->status === OrderStatus::Paid);
    Notification::assertSentTo($order->seller, OrderStatusChanged::class, fn ($mail): bool => $mail->role === 'seller' && $mail->status === OrderStatus::Paid);

    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    // Verzonden: alleen de koper hoeft iets te doen.
    Notification::assertSentTo($order->buyer, OrderStatusChanged::class, fn ($mail): bool => $mail->status === OrderStatus::Shipped);
    Notification::assertNotSentTo($order->seller, OrderStatusChanged::class, fn ($mail): bool => $mail->status === OrderStatus::Shipped);
});

it('rendert elke status-roltekst zonder kale taalsleutels', function () {
    $order = Order::factory()->delivered()->create();

    $combinations = [
        [OrderStatus::Paid, ['buyer', 'seller']],
        [OrderStatus::ReadyForPickup, ['buyer']],
        [OrderStatus::Shipped, ['buyer']],
        [OrderStatus::Delivered, ['buyer', 'seller']],
        [OrderStatus::Completed, ['buyer', 'seller']],
        [OrderStatus::Cancelled, ['buyer', 'seller']],
    ];

    foreach ($combinations as [$status, $roles]) {
        foreach ($roles as $role) {
            $rendered = (new OrderStatusChanged($order, $status, $role))->toMail($order->buyer);

            // Een onvertaalde sleutel begint met "notifications." - dat mag
            // nooit in een onderwerpsregel terechtkomen.
            expect($rendered->subject)->not->toStartWith('notifications.')
                ->and($rendered->subject)->toContain($order->lot->title);
        }
    }
});

it('bezorgt de geschiluitkomst mét motivering bij beide partijen', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    $dispute = app(ReportProblem::class)->handle($order->fresh(), $order->buyer, 'damaged', 'Kras.');

    Notification::fake();

    app(ResolveDispute::class)->handle(
        $dispute,
        User::factory()->create(),
        upheld: true,
        resolution: 'prijscorrectie',
        notes: 'Beide partijen akkoord met een correctie.',
    );

    foreach ([$order->buyer, $order->seller] as $party) {
        Notification::assertSentTo($party, DisputeResolved::class, function (DisputeResolved $mail) use ($party): bool {
            $rendered = $mail->toMail($party);

            return str_contains(implode(' ', $rendered->introLines), 'Beide partijen akkoord');
        });
    }
});

it('meldt de verkoper dat zijn uitbetaling is vrijgegeven', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    $this->travel(49)->hours();

    Notification::fake();
    $this->artisan('orders:advance');

    Notification::assertSentTo($order->seller, PayoutReleased::class);
    Notification::assertNotSentTo($order->buyer, PayoutReleased::class);
});
