<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Orders\Contracts\PaymentProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Start een betaling en geef de checkout-URL terug.
 *
 * Het bedrag komt van de orderrij - die kwam op zijn beurt uit
 * App\Support\Money. Zo staan het totaal dat de koper zag, het bedrag op de
 * order en het bedrag bij de provider per constructie gelijk (hfst. 9).
 *
 * Twee keer klikken geeft dezelfde checkout terug: een open betaling van de
 * laatste dertig minuten wordt hergebruikt (briefing: betaalacties
 * idempotent). Daarna start een verse; de vorige blijft `pending` tot de
 * provider hem laat verlopen. De webhook kijkt per betaling.
 */
final class StartPayment
{
    public function __construct(private readonly PaymentProvider $provider) {}

    public function handle(Order $order, User $buyer): string
    {
        if ($order->buyer_id !== $buyer->id) {
            throw ValidationException::withMessages(['order' => __('orders.errors.not_yours')]);
        }

        if ($order->status !== OrderStatus::AwaitingPayment) {
            throw ValidationException::withMessages(['order' => __('orders.errors.not_payable')]);
        }

        return Cache::lock('checkout:'.$order->id, 10)->block(5, function () use ($order): string {
            $open = Payment::query()
                ->where('order_id', $order->id)
                ->where('status', PaymentStatus::Pending)
                ->whereNotNull('checkout_url')
                ->where('created_at', '>', now()->subMinutes(30))
                ->latest('id')
                ->first();

            if ($open !== null) {
                return $open->checkout_url;
            }

            // Oudere open pogingen schrijven we lokaal af: komt daar alsnog geld
            // op binnen, dan meldt de webhook het aan support in plaats van het
            // stil te verwerken naast een tweede betaling.
            Payment::query()
                ->where('order_id', $order->id)
                ->where('status', PaymentStatus::Pending)
                ->update(['status' => PaymentStatus::Expired]);

            return $this->createCheckout($order);
        });
    }

    private function createCheckout(Order $order): string
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => (string) config('aucvy.payments.provider'),
            'status' => PaymentStatus::Pending,
            'amount_cents' => $order->total_cents,
        ]);

        $session = $this->provider->create($payment, $order);

        $payment->update([
            'provider_payment_id' => $session->providerId,
            'checkout_url' => $session->checkoutUrl,
        ]);

        return $session->checkoutUrl;
    }
}
