<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\ProviderEvent;
use App\Notifications\MoneyNeedsAttention;
use App\Orders\Contracts\PaymentProvider;
use App\Support\Audit\Audit;
use App\Support\Money\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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

        /*
         | Eén rij per provider en event: dezelfde betaling in dezelfde
         | status die nog eens binnenkomt, is al verwerkt en doet niets meer.
         | De rij blijft staan als spoor voor een replay (sectie 7, flow D).
         */
        try {
            $event = ProviderEvent::query()->create([
                'provider' => $payment->provider,
                'event_id' => $providerPaymentId.':'.$current->status->value,
                'type' => 'payment.'.$current->status->value,
                'payload' => $payload,
                'received_at' => now(),
                'status' => ProviderEvent::STATUS_RECEIVED,
            ]);
        } catch (UniqueConstraintViolationException) {
            $event = ProviderEvent::query()
                ->where('provider', $payment->provider)
                ->where('event_id', $providerPaymentId.':'.$current->status->value)
                ->first();

            // Eerder ontvangen maar nooit afgerond (fout halverwege): de herhaling
            // van de provider maakt het alsnog af in plaats van te zwijgen.
            if ($event === null || $event->status !== ProviderEvent::STATUS_RECEIVED) {
                return $payment;
            }
        }

        $becamePaid = DB::transaction(function () use ($payment, $current, $payload, $providerPaymentId): bool {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Terminale statussen zijn definitief: een late of dubbele webhook
            // mag een betaald record niet meer aanraken.
            if ($locked->status !== PaymentStatus::Pending) {
                // Geld binnen op een betaling die wij al als verlopen of mislukt
                // afgeschreven hadden: dat moet een mens zien.
                if ($current->status === PaymentStatus::Paid && $locked->status !== PaymentStatus::Paid) {
                    $this->flagForSupport($locked, 'Betaling binnen op een afgeschreven poging', 'Betaling '.$providerPaymentId.' voor bestelling '.$locked->order->order_number.' is betaald gemeld terwijl hij hier op "'.$locked->status->value.'" stond. Controleer bij de provider of het bedrag dubbel is en boek zo nodig terug.');
                }

                return false;
            }

            $locked->status = $current->status;
            $locked->method = $current->method;
            $locked->webhook_payload = $payload;

            if ($current->status === PaymentStatus::Paid) {
                $locked->paid_at = $current->paidAt ?? now();
            }

            $locked->save();

            if ($current->status === PaymentStatus::Paid) {
                Audit::record('payment.paid', $locked, null, after: [
                    'order_id' => $locked->order_id,
                    'amount_cents' => $locked->amount_cents,
                    'provider_payment_id' => $providerPaymentId,
                ]);
            }

            return $current->status === PaymentStatus::Paid;
        });

        /*
         | De orderovergang buiten de betalingstransactie maar erná: pas als de
         | betaling vastligt. Niet op "werd zojuist betaald" maar op de stand:
         | ging een eerdere poging halverwege mis, dan maakt de herhaling het af.
         */
        $payment->refresh();
        $order = $payment->order()->first();

        if ($payment->status === PaymentStatus::Paid && $order->status === OrderStatus::AwaitingPayment) {
            $this->transition->handle($order, OrderStatus::Paid, context: [
                'payment_id' => $payment->id,
                'provider_payment_id' => $providerPaymentId,
            ]);
        } elseif ($becamePaid && $order->status !== OrderStatus::AwaitingPayment && ! $order->status->isPaidOrLater()) {
            // Betaald nadat de order al geannuleerd was (termijn verlopen): het
            // geld is binnen, de order niet meer. Terugboeken is handwerk tot de
            // provider dat kan.
            $this->flagForSupport($payment, 'Betaling binnen voor een geannuleerde bestelling', 'Bestelling '.$order->order_number.' was al '.$order->status->label().' toen betaling '.$providerPaymentId.' van '.Money::format($payment->amount_cents).' binnenkwam. Boek het bedrag terug of herstel de bestelling.');
        }

        $event->markProcessed();

        return $payment->refresh();
    }

    private function flagForSupport(Payment $payment, string $subject, string $line): void
    {
        Audit::record('payment.needs_attention', $payment, null, after: ['subject' => $subject]);

        Notification::route('mail', (string) config('aucvy.support.email'))
            ->notify(new MoneyNeedsAttention($subject, [$line], route('admin.orders.show', $payment->order_id)));
    }
}
