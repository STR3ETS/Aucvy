<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Order;
use App\Notifications\OrderCreated;

/**
 * Eén plek voor "er is een order ontstaan" - de hamerslag, koop-direct en de
 * afslag maken alle drie orders, en beide partijen horen het meteen
 * (hfst. 9). De notificaties wachten op de commit; een rollback mailt niet.
 */
final class OrderObserver
{
    public function created(Order $order): void
    {
        $order->buyer->notify(new OrderCreated($order, 'buyer'));
        $order->seller->notify(new OrderCreated($order, 'seller'));
    }
}
