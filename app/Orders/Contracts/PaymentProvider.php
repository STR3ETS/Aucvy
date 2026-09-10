<?php

declare(strict_types=1);

namespace App\Orders\Contracts;

use App\Models\Order;
use App\Models\Payment;
use App\Orders\CheckoutSession;
use App\Orders\ProviderPaymentStatus;

/**
 * De betaalprovider achter de orders.
 *
 * Zelfde patroon als de AI-laag (hfst. 14): één interface, wisselen is één
 * klasse. En dezelfde huisregel als bij het bieden: de betaalstatus komt
 * nooit uit de redirect maar altijd uit `status()` - dat is wat de webhook
 * idempotent maakt.
 */
interface PaymentProvider
{
    /** Start een checkout voor deze betaling bij de provider. */
    public function create(Payment $payment, Order $order): CheckoutSession;

    /** De actuele status volgens de provider - de enige bron van waarheid. */
    public function status(string $providerPaymentId): ProviderPaymentStatus;
}
