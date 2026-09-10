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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Nepprovider voor lokaal en tests - draait zonder Mollie-account.
 *
 * De checkout-URL wijst naar een lokale neppagina met een "Betaal"-knop; die
 * zet de gewenste uitkomst in de cache en vuurt daarna dezelfde webhookroute
 * af als Mollie zou doen. Zo test je de hele keten - redirect, webhook,
 * idempotentie - zonder echte betaling.
 */
final class FakePaymentProvider implements PaymentProvider
{
    public function create(Payment $payment, Order $order): CheckoutSession
    {
        return new CheckoutSession(
            providerId: 'fake_'.Str::random(20),
            checkoutUrl: route('dev.checkout', $payment),
        );
    }

    public function status(string $providerPaymentId): ProviderPaymentStatus
    {
        $status = PaymentStatus::tryFrom(
            (string) Cache::get($this->cacheKey($providerPaymentId), '')
        ) ?? PaymentStatus::Pending;

        return new ProviderPaymentStatus(
            status: $status,
            method: $status === PaymentStatus::Paid ? 'ideal' : null,
            paidAt: $status === PaymentStatus::Paid ? CarbonImmutable::now() : null,
        );
    }

    /** Wat de nepcheckout (of een test) als uitkomst klaarzet. */
    public function pretend(string $providerPaymentId, PaymentStatus $status): void
    {
        Cache::put($this->cacheKey($providerPaymentId), $status->value, now()->addDay());
    }

    private function cacheKey(string $providerPaymentId): string
    {
        return 'fake-payment:'.$providerPaymentId;
    }
}
