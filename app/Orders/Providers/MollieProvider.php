<?php

declare(strict_types=1);

namespace App\Orders\Providers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\CheckoutSession;
use App\Orders\Contracts\PaymentProvider;
use App\Orders\ProviderPaymentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Mollie via de eigen REST-API (v2), zonder extra dependency.
 *
 * De webhook van Mollie stuurt alleen een id - de status wordt altijd hier
 * opgehaald. Draait alleen met MOLLIE_API_KEY gezet en
 * AUCVY_PAYMENT_PROVIDER=mollie.
 */
final class MollieProvider implements PaymentProvider
{
    public function create(Payment $payment, Order $order): CheckoutSession
    {
        $response = $this->client()->post('payments', [
            'amount' => [
                'currency' => 'EUR',
                'value' => $this->decimalValue($payment->amount_cents),
            ],
            'description' => "Aucvy {$order->order_number}",
            'redirectUrl' => route('orders.show', $order),
            'webhookUrl' => route('webhooks.mollie'),
            'metadata' => ['order_number' => $order->order_number],
        ])->throw()->json();

        return new CheckoutSession(
            providerId: (string) $response['id'],
            checkoutUrl: (string) $response['_links']['checkout']['href'],
        );
    }

    public function status(string $providerPaymentId): ProviderPaymentStatus
    {
        $payment = $this->client()->get("payments/{$providerPaymentId}")->throw()->json();

        return new ProviderPaymentStatus(
            status: $this->mapStatus((string) $payment['status']),
            method: $payment['method'] ?? null,
            paidAt: isset($payment['paidAt']) ? CarbonImmutable::parse($payment['paidAt']) : null,
        );
    }

    private function mapStatus(string $mollieStatus): PaymentStatus
    {
        return match ($mollieStatus) {
            'paid' => PaymentStatus::Paid,
            'expired' => PaymentStatus::Expired,
            'failed', 'canceled' => PaymentStatus::Failed,
            // open, pending, authorized: de koper is er nog mee bezig.
            default => PaymentStatus::Pending,
        };
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.mollie.com/v2')
            ->withToken((string) config('services.mollie.key'))
            ->acceptJson();
    }

    /**
     * Centen naar Mollies "1234.56" - als string, zonder float in het pad.
     */
    private function decimalValue(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
