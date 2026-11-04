<?php

declare(strict_types=1);

namespace App\Observers;

use App\Lots\Dossier\ProjectSources;
use App\Models\Order;
use App\Notifications\OrderCreated;
use Throwable;

/**
 * Eén plek voor "er is een order ontstaan" - de hamerslag, koop-direct en de
 * afslag maken alle drie orders, en beide partijen horen het meteen
 * (hfst. 9). De notificaties wachten op de commit; een rollback mailt niet.
 *
 * Daarnaast landt elke order en elke statuswijziging in het kaveldossier.
 */
final class OrderObserver
{
    public function __construct(private readonly ProjectSources $project) {}

    public function created(Order $order): void
    {
        $order->buyer->notify(new OrderCreated($order, 'buyer'));
        $order->seller->notify(new OrderCreated($order, 'seller'));

        $this->safely(fn () => $this->project->orderCreated($order));
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('status')) {
            $this->safely(fn () => $this->project->orderStatus($order));
        }
    }

    private function safely(callable $projection): void
    {
        try {
            $projection();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
