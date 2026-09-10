<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Orders\Contracts\PaymentProvider;
use Illuminate\Validation\ValidationException;

/**
 * Start een betaling en geef de checkout-URL terug.
 *
 * Het bedrag komt van de orderrij - die kwam op zijn beurt uit
 * App\Support\Money. Zo staan het totaal dat de koper zag, het bedrag op de
 * order en het bedrag bij de provider per constructie gelijk (hfst. 9).
 *
 * Elke klik start een verse betaling; de vorige blijft `pending` staan tot
 * de provider hem laat verlopen. De webhook kijkt per betaling, dus twee
 * open pogingen bijten elkaar niet.
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

        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => (string) config('aucvy.payments.provider'),
            'status' => PaymentStatus::Pending,
            'amount_cents' => $order->total_cents,
        ]);

        $session = $this->provider->create($payment, $order);

        $payment->update(['provider_payment_id' => $session->providerId]);

        return $session->checkoutUrl;
    }
}
