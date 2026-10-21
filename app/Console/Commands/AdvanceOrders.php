<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Compliance\Permission;
use App\Compliance\PermissionEngine;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\Payout;
use App\Notifications\PayoutReleased;
use App\Orders\Actions\TransitionOrder;
use App\Support\Audit\Audit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * De klokken van hfst. 9, elke minuut nagelopen.
 *
 * De zekerheidspagina belooft termijnen; dit is de job die ze afdwingt:
 *
 *  1. niet betaald binnen 48 uur      -> order geannuleerd, kavel terug;
 *  2. verzonden en 7 dagen stil       -> automatisch geleverd;
 *  3. meldtermijn verstreken          -> afgerond;
 *  4. houdtermijn om, geen geschil    -> payout vrijgegeven.
 *
 * Elke overgang loopt door TransitionOrder en is dus geauditeerd en
 * idempotent - de job mag dubbel draaien zonder schade.
 */
final class AdvanceOrders extends Command
{
    protected $signature = 'orders:advance';

    protected $description = 'Verloopt, levert, rondt af en geeft payouts vrij volgens de termijnen';

    public function handle(TransitionOrder $transition, PermissionEngine $permissions): int
    {
        $expired = $this->sweep(
            Order::query()
                ->where('status', OrderStatus::AwaitingPayment)
                ->where('payment_deadline_at', '<', now())
                // Wie nog in de betaalpagina zit, krijgt de tijd om af te rekenen.
                ->whereDoesntHave('payments', fn ($query) => $query
                    ->where('status', PaymentStatus::Pending)
                    ->where('created_at', '>', now()->subMinutes(30))),
            fn (Order $order) => $transition->handle($order, OrderStatus::Cancelled, context: [
                'reason' => 'payment_deadline_expired',
            ]),
        );

        $delivered = $this->sweep(
            Order::query()
                ->whereIn('status', [OrderStatus::ReadyForPickup, OrderStatus::Shipped])
                ->where('shipped_at', '<', now()->subDays((int) config('aucvy.orders.auto_deliver_after_days'))),
            fn (Order $order) => $transition->handle($order, OrderStatus::Delivered, context: [
                'reason' => 'auto_delivered',
            ]),
        );

        $completed = $this->sweep(
            Order::query()
                ->where('status', OrderStatus::Delivered)
                ->where('dispute_window_ends_at', '<', now()),
            fn (Order $order) => $transition->handle($order, OrderStatus::Completed, context: [
                'reason' => 'dispute_window_passed',
            ]),
        );

        /*
         | Vrijgeven kan alleen als de order écht rond is: houdtermijn om,
         | order afgerond en geen geschil dat nog loopt. De geschilcheck zit
         | hier dubbel op (een geschil zet de order al op `disputed`) - bewust:
         | dit is geld, en riemen én bretels zijn hier goedkoop.
         */
        $released = Payout::query()
            ->where('status', PayoutStatus::Held)
            ->where('hold_until', '<', now())
            ->whereHas('order', fn ($query) => $query->where('status', OrderStatus::Completed))
            ->whereDoesntHave('order.disputes', fn ($query) => $query->whereNotIn('status', [
                DisputeStatus::Resolved->value,
                DisputeStatus::Rejected->value,
            ]))
            ->get()
            ->filter(function (Payout $payout) use ($permissions): bool {
                /*
                 | De Permission Engine beslist: geblokkeerd account, afgewezen
                 | bedrijfscontrole of geen rekeningnummer houdt het geld vast.
                 | De reden staat op de uitbetaling, zodat verkoper en beheer
                 | zien wat er nodig is (sectie 7, flow D).
                 */
                $decision = $permissions->decide($payout->seller, Permission::Payout);

                if ($decision->denied()) {
                    $payout->update(['blocked_reason' => $decision->reason->value]);

                    return false;
                }

                $payout->update([
                    'status' => PayoutStatus::Released,
                    'released_at' => now(),
                    'blocked_reason' => null,
                ]);

                Audit::record('payout.released', $payout, null, after: [
                    'order_id' => $payout->order_id,
                    'amount_cents' => $payout->amount_cents,
                ]);

                // Het bericht waar de verkoper op wacht (hfst. 9).
                $payout->seller->notify(new PayoutReleased($payout));

                return true;
            })
            ->count();

        $this->info("verlopen: {$expired}, geleverd: {$delivered}, afgerond: {$completed}, vrijgegeven: {$released}");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<Order>  $query
     */
    private function sweep(Builder $query, callable $apply): int
    {
        // Eerst verzamelen, dan één voor één onder de rijlock van
        // TransitionOrder. Is een order intussen al verder (races met de
        // webhook of een gebruiker), dan is dat daar een no-op of een nette
        // weigering - de veeg gaat door met de rest.
        return $query->get()
            ->filter(function (Order $order) use ($apply): bool {
                try {
                    $apply($order);

                    return true;
                } catch (ValidationException) {
                    return false;
                }
            })
            ->count();
    }
}
