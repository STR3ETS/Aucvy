<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Orders\Actions\StartPayment;
use App\Orders\Contracts\PaymentProvider;
use App\Orders\Providers\FakePaymentProvider;
use Illuminate\Validation\ValidationException;

/*
| Het betaalpad (hfst. 9 en 14): starten geeft een checkout-URL, de webhook
| is de enige die een betaling "betaald" maakt, en die verwerking is
| idempotent - Mollie stuurt hem gerust drie keer.
*/

function startPaymentFor(Order $order): Payment
{
    app(StartPayment::class)->handle($order, $order->buyer);

    // De jongste: een order mag meerdere pogingen hebben.
    return $order->payments()->latest('id')->firstOrFail();
}

function fakeProvider(): FakePaymentProvider
{
    return app(PaymentProvider::class);
}

describe('betaling starten', function () {
    it('maakt een betaling met het orderbedrag en levert een checkout-url', function () {
        $order = Order::factory()->create();

        $url = app(StartPayment::class)->handle($order, $order->buyer);

        $payment = $order->payments()->sole();

        expect($payment->amount_cents)->toBe($order->total_cents)
            ->and($payment->status)->toBe(PaymentStatus::Pending)
            ->and($payment->provider_payment_id)->toStartWith('fake_')
            ->and($url)->toContain('/dev/checkout/');
    });

    it('weigert een ander dan de koper', function () {
        $order = Order::factory()->create();

        expect(fn () => app(StartPayment::class)->handle($order, User::factory()->create()))
            ->toThrow(ValidationException::class);
    });

    it('weigert een order die niet meer openstaat', function () {
        $order = Order::factory()->paid()->create();

        expect(fn () => app(StartPayment::class)->handle($order, $order->buyer))
            ->toThrow(ValidationException::class);
    });
});

describe('de webhook', function () {
    it('zet betaling en order op betaald', function () {
        $order = Order::factory()->create();
        $payment = startPaymentFor($order);

        fakeProvider()->pretend($payment->provider_payment_id, PaymentStatus::Paid);

        $this->post(route('webhooks.mollie'), ['id' => $payment->provider_payment_id])
            ->assertOk();

        expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->fresh()->paid_at)->not->toBeNull()
            ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
    });

    it('is idempotent: drie keer dezelfde webhook, één uitkomst', function () {
        $order = Order::factory()->create();
        $payment = startPaymentFor($order);

        fakeProvider()->pretend($payment->provider_payment_id, PaymentStatus::Paid);

        foreach (range(1, 3) as $attempt) {
            $this->post(route('webhooks.mollie'), ['id' => $payment->provider_payment_id])->assertOk();
        }

        expect($order->fresh()->status)->toBe(OrderStatus::Paid)
            ->and($order->fresh()->payout()->count())->toBe(1);
    });

    it('laat de order met rust als de betaling mislukt', function () {
        $order = Order::factory()->create();
        $payment = startPaymentFor($order);

        fakeProvider()->pretend($payment->provider_payment_id, PaymentStatus::Failed);

        $this->post(route('webhooks.mollie'), ['id' => $payment->provider_payment_id])->assertOk();

        // De koper kan gewoon een nieuwe poging starten.
        expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
            ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
    });

    it('geeft 404 op een onbekend id zodat de provider het opnieuw probeert', function () {
        $this->post(route('webhooks.mollie'), ['id' => 'tr_bestaat_niet'])->assertNotFound();
    });

    it('negeert een tweede betaalpoging als de order al betaald is', function () {
        $order = Order::factory()->create();
        $first = startPaymentFor($order);
        $second = startPaymentFor($order->fresh());

        fakeProvider()->pretend($first->provider_payment_id, PaymentStatus::Paid);
        $this->post(route('webhooks.mollie'), ['id' => $first->provider_payment_id]);

        // De tweede poging wordt óók betaald gemeld (kan echt gebeuren met
        // twee tabbladen) - de order verandert er niet meer door.
        fakeProvider()->pretend($second->provider_payment_id, PaymentStatus::Paid);
        $this->post(route('webhooks.mollie'), ['id' => $second->provider_payment_id])->assertOk();

        expect($order->fresh()->status)->toBe(OrderStatus::Paid)
            ->and($order->fresh()->payout()->count())->toBe(1);
    });
});

describe('de nepcheckout', function () {
    it('doorloopt de hele keten van knop tot betaalde order', function () {
        $order = Order::factory()->create();

        $response = $this->actingAs($order->buyer)->post(route('orders.pay', $order));

        $payment = $order->payments()->sole();
        $response->assertRedirect(route('dev.checkout', $payment));

        $this->actingAs($order->buyer)
            ->get(route('dev.checkout', $payment))
            ->assertOk()
            ->assertSee('Nepcheckout');

        $this->actingAs($order->buyer)
            ->post(route('dev.checkout.store', $payment), ['outcome' => 'paid'])
            ->assertRedirect(route('orders.show', $order));

        expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    });

    it('bestaat niet zodra de echte provider actief is', function () {
        config(['aucvy.payments.provider' => 'mollie']);

        $order = Order::factory()->create();
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => 'mollie',
            'status' => PaymentStatus::Pending,
            'amount_cents' => $order->total_cents,
            'provider_payment_id' => 'tr_x',
        ]);

        $this->actingAs($order->buyer)
            ->get(route('dev.checkout', $payment))
            ->assertNotFound();
    });
});
