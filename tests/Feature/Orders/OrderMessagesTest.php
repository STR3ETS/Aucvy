<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderMessageReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| Berichten bij een bestelling (briefing sectie 7): koper, verkoper en
| support in één draad; de ander krijgt een melding en een mail.
*/

beforeEach(fn () => Queue::fake());

it('laat koper en verkoper elkaar schrijven en meldt het de ander', function () {
    Notification::fake();

    $order = Order::factory()->create(['status' => OrderStatus::Paid]);
    $buyer = $order->buyer;
    $seller = $order->seller;

    $this->actingAs($buyer)
        ->post(route('orders.messages.store', $order), ['body' => "Kan ik morgen om 10 uur ophalen?\nIk kom met een bus."])
        ->assertRedirect(route('orders.show', $order).'#berichten');

    Notification::assertSentTo($seller, OrderMessageReceived::class);
    Notification::assertNotSentTo($buyer, OrderMessageReceived::class);

    $this->actingAs($seller)->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Kan ik morgen om 10 uur ophalen?')
        ->assertSee('koper ·');

    $this->actingAs($seller)->post(route('orders.messages.store', $order), ['body' => 'Prima, tot morgen.']);

    $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk()->assertSee('Prima, tot morgen.')->assertSee('Jij');

    expect($order->messages()->count())->toBe(2)
        ->and($order->messages()->first()->from_staff)->toBeFalse();
});

it('houdt buitenstaanders weg en weigert een leeg bericht', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Paid]);

    $this->actingAs(User::factory()->create())
        ->post(route('orders.messages.store', $order), ['body' => 'Hallo'])
        ->assertNotFound();

    $this->actingAs($order->buyer)
        ->from(route('orders.show', $order))
        ->post(route('orders.messages.store', $order), ['body' => ''])
        ->assertSessionHasErrors('body');
});

it('laat support meeschrijven vanuit de backoffice naar beide partijen', function () {
    Notification::fake();

    $order = Order::factory()->create(['status' => OrderStatus::Paid]);
    $staff = User::factory()->create(['is_staff' => true]);

    $this->actingAs($staff)
        ->post(route('admin.orders.messages.store', $order), ['body' => 'We kijken mee; de verkoper bevestigt het ophaalmoment.'])
        ->assertRedirect(route('admin.orders.show', $order).'#berichten');

    Notification::assertSentTo($order->buyer, OrderMessageReceived::class);
    Notification::assertSentTo($order->seller, OrderMessageReceived::class);

    $message = $order->messages()->sole();
    expect($message->from_staff)->toBeTrue()->and($message->roleFor($order))->toBe('support');

    $this->actingAs($order->buyer)->get(route('orders.show', $order))->assertOk()->assertSee('Aucvy support');
    $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertOk()->assertSee('We kijken mee');
});
