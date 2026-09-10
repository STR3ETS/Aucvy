<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Orders\Contracts\PaymentProvider;
use Illuminate\Support\Facades\DB;

/**
 * De webhook, idempotent (hfst. 14).
 *
 * De provider stuurt alleen een id; de status wordt altijd bij de provider
 * opgehaald - nooit uit de redirect of de payload afgeleid. Twee keer
 * dezelfde webhook (en Mollie stuurt hem gerust vaker) levert exact dezelfde
 * eindtoestand op: de rijlock plus de terminale-statuscheck maken de tweede
 * aanroep een no-op.
 */
final class ProcessPaymentWebhook
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly TransitionOrder $transition,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  De ruwe webhookbody, voor het log.
     */
    public function handle(string $providerPaymentId, array $payload = []): ?Payment
    {
        $payment = Payment::query()->where('provider_payment_id', $providerPaymentId)->first();

        if ($payment === null) {
            return null;
        }

        $current = $this->provider->status($providerPaymentId);

        $becamePaid = DB::transaction(function () use ($payment, $current, $payload): bool {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Terminale statussen zijn definitief: een late of dubbele webhook
            // mag een betaald record niet meer aanraken.
            if ($locked->status !== PaymentStatus::Pending) {
                return false;
            }

            $locked->status = $current->status;
            $locked->method = $current->method;
            $locked->webhook_payload = $payload;

            if ($current->status === PaymentStatus::Paid) {
                $locked->paid_at = $current->paidAt ?? now();
            }

            $locked->save();

            return $current->status === PaymentStatus::Paid;
        });

        /*
         | De orderovergang buiten de betalingstransactie maar erná: pas als de
         | betaling vastligt. Alleen vanuit `awaiting_payment` - betaalt iemand
         | een tweede openstaande poging nadat de eerste al verwerkt is, dan
         | verandert dat niets meer aan de order.
         */
        if ($becamePaid && $payment->order->status === OrderStatus::AwaitingPayment) {
            $this->transition->handle($payment->order, OrderStatus::Paid, context: [
                'payment_id' => $payment->id,
                'provider_payment_id' => $providerPaymentId,
            ]);
        }

        return $payment->refresh();
    }
}
