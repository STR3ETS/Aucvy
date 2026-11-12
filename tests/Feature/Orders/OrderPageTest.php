<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\TransitionOrder;
use App\Support\Money\Money;

/*
| De orderpagina: beide partijen zien dezelfde route met hun eigen
| vervolgstap, een derde ziet een 404, en de kostenopbouw komt uit dezelfde
| Money-klasse als overal (hfst. 9).
*/

it('toont de koper de kostenopbouw en de betaalknop', function () {
    $order = Order::factory()->create();

    $this->actingAs($order->buyer)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Betalen')
        ->assertSee(Money::format($order->total_cents))
        ->assertSee($order->lot->title);
});

it('toont de verkoper de uitbetaling met fee', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    $this->actingAs($order->seller)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Jouw uitbetaling')
        ->assertSee(Money::format($order->fresh()->payout->amount_cents));
});

it('geeft een derde een 404, geen 403', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', $order))
        ->assertNotFound();
});

it('laat de verkoper verzenden en de koper de levering bevestigen', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    $this->actingAs($order->seller)
        ->post(route('orders.ship', $order), ['method' => 'shipped'])
        ->assertRedirect(route('orders.show', $order));

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);

    $this->actingAs($order->buyer)
        ->post(route('orders.deliver', $order))
        ->assertRedirect(route('orders.show', $order));

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('laat de koper niet verzenden en de verkoper niet leveren', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    $this->actingAs($order->buyer)
        ->post(route('orders.ship', $order), ['method' => 'shipped'])
        ->assertSessionHasErrors();

    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);

    $this->actingAs($order->seller)
        ->post(route('orders.deliver', $order))
        ->assertSessionHasErrors();
});

it('meldt een probleem via het formulier', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);

    $this->actingAs($order->buyer)
        ->post(route('orders.dispute', $order), [
            'reason' => 'not_received',
            'description' => 'Na twee weken nog niets ontvangen.',
        ])
        ->assertRedirect(route('orders.show', $order));

    expect($order->fresh()->status)->toBe(OrderStatus::Disputed)
        ->and($order->fresh()->disputes()->count())->toBe(1);
});

it('zet bestellingen per stand in een eigen tabel en filtert via de tegels', function () {
    $user = User::factory()->verified()->create();
    $bought = Order::factory()->create(['buyer_id' => $user->id]);
    $sold = Order::factory()->create(['seller_id' => $user->id]);

    // Zonder filter: wat je kocht en wat je verkocht elk in een eigen tabel, met de koppen één keer.
    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee($bought->order_number)
        ->assertSee($sold->order_number)
        ->assertSee('class="d-lottable-head"', false)
        ->assertSee('Waarde volgens Atlas')
        ->assertSee('data-tip="Bekijk bestelling"', false)
        ->assertSee(route('orders.index', ['toon' => 'verkocht']), escape: false);

    $this->actingAs($user)
        ->get(route('orders.index', ['toon' => 'verkocht']))
        ->assertOk()
        ->assertSee($sold->order_number)
        ->assertSee('Hamerprijs')
        ->assertDontSee($bought->order_number);

    $this->actingAs($user)
        ->get(route('orders.index', ['toon' => 'gekocht']))
        ->assertOk()
        ->assertSee($bought->order_number)
        ->assertSee('Je bod')
        ->assertDontSee($sold->order_number);

    // Lopend toont beide kanten, elk in de eigen tabel.
    $this->actingAs($user)
        ->get(route('orders.index', ['toon' => 'lopend']))
        ->assertOk()
        ->assertSee($bought->order_number)
        ->assertSee($sold->order_number);
});

it('opent op verkocht voor wie alleen verkocht heeft', function () {
    $user = User::factory()->verified()->create();
    $sold = Order::factory()->create(['seller_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee($sold->order_number);
});

it('zet lange lijsten achter Meer tonen', function () {
    $user = User::factory()->verified()->create();
    Order::factory()->count(12)->create(['buyer_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('Meer tonen')
        ->assertSee('(2)');
});

it('toont een bestelling als één kop met de stappen, hoogstens één actie en drie tabs', function () {
    $order = Order::factory()->create(['status' => OrderStatus::AwaitingPayment]);

    // De koper moet betalen: het bedrag met de termijn, de stap Betalen is aan de beurt, één blauwe knop.
    $this->actingAs($order->buyer)->get(route('orders.show', $order))
        ->assertOk()
        ->assertSeeInOrder(['d-orderhead', $order->lot->title, 'Betalen vóór', 'class="d-hsteps"', 'Kavel gewonnen', 'class="is-now"', 'Betalen', 'Betaalmethode', 'Nu betalen', 'id="order-tab-berichten"', 'id="order-tab-tijdlijn"', 'id="order-tab-dossier"', 'Kostenopbouw', 'Jij betaalt'], false)
        // De losse blokken van vroeger zijn weg.
        ->assertDontSee('Besteloverzicht')
        ->assertDontSee('Wat gebeurt er nu?')
        ->assertDontSee('Totaalbedrag');

    // De verkoper ziet de hamerprijs en leest niet dat hij zelf betaalt.
    $this->actingAs($order->seller)->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Kavel verkocht')
        ->assertSee('Hamerprijs')
        ->assertSee('De koper betaalt')
        ->assertDontSee('Jij betaalt')
        ->assertDontSee('Nu betalen');

    // Na betaling regelt de verkoper de levering; de koper kan nog niets doen.
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    $this->actingAs($order->seller)->get(route('orders.show', $order))
        ->assertOk()
        ->assertSeeInOrder(['Regel de levering', 'Verzonden', 'Klaar om op te halen'])
        ->assertSee('Nog te regelen');
    $this->actingAs($order->buyer)->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('De verkoper maakt de kavel klaar')
        ->assertDontSee('Regel de levering');

    // Onderweg: de koper bevestigt de levering en kan via de vlag een probleem melden.
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);

    $this->actingAs($order->buyer)->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Levering bevestigen')
        ->assertSee('data-tip="Probleem melden"', false);
});
