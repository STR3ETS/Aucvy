<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Review;
use App\Models\User;

/*
| Reviews zijn niet cosmetisch (hfst. 13.6): alleen `completed` geeft recht,
| kritische reviews blijven staan (er bestaat geen delete-route), en de
| verkoper krijgt precies één reactie.
*/

it('laat de koper een afgeronde order beoordelen', function () {
    $order = Order::factory()->completed()->create();

    $this->actingAs($order->buyer)
        ->post(route('orders.review', $order), [
            'rating' => 2,
            'body' => 'Ophalen liep een week uit door miscommunicatie.',
        ])
        ->assertRedirect(route('orders.show', $order));

    $review = Review::sole();

    expect($review->rating)->toBe(2)
        ->and($review->published_at)->not->toBeNull()
        ->and($review->seller_id)->toBe($order->seller_id);
});

it('weigert een review op een order die nog loopt', function () {
    $order = Order::factory()->paid()->create();

    $this->actingAs($order->buyer)
        ->post(route('orders.review', $order), ['rating' => 5])
        ->assertSessionHasErrors('review');
});

it('weigert een tweede review op dezelfde order', function () {
    $order = Order::factory()->completed()->create();

    $this->actingAs($order->buyer)->post(route('orders.review', $order), ['rating' => 4]);
    $this->actingAs($order->buyer)
        ->post(route('orders.review', $order), ['rating' => 5])
        ->assertSessionHasErrors('review');

    expect(Review::count())->toBe(1);
});

it('verbergt de reviewroute voor wie niet de koper is', function () {
    $order = Order::factory()->completed()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('orders.review', $order), ['rating' => 5])
        ->assertNotFound();
});

it('toont ook een kritische review op het profiel, met de verkopersreactie', function () {
    $order = Order::factory()->completed()->create();

    $this->actingAs($order->buyer)->post(route('orders.review', $order), [
        'rating' => 1,
        'body' => 'Machine bleek defect bij aankomst.',
    ]);

    $review = Review::sole();

    $this->actingAs($order->seller)
        ->post(route('reviews.reply', $review), [
            'reply' => 'Terecht punt - we hebben de controle vooraf aangescherpt.',
        ])
        ->assertRedirect();

    // Publiek profiel: de kritische review stáát er, mét reactie en de
    // verdeling over álle sterren.
    $this->get(route('sellers.show', $order->seller))
        ->assertOk()
        ->assertSee('Machine bleek defect')
        ->assertSee('Terecht punt')
        ->assertSee('1★')
        ->assertSee('5★')
        ->assertSee('De verkoper kan reageren, maar niets verwijderen.');
});

it('laat de verkoper maar één keer reageren', function () {
    $order = Order::factory()->completed()->create();
    $this->actingAs($order->buyer)->post(route('orders.review', $order), ['rating' => 3]);

    $review = Review::sole();

    $this->actingAs($order->seller)->post(route('reviews.reply', $review), ['reply' => 'Eerste reactie.']);
    $this->actingAs($order->seller)
        ->post(route('reviews.reply', $review), ['reply' => 'Tweede reactie.'])
        ->assertSessionHasErrors('reply');
});
