<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * De koper meldt een probleem (hfst. 9).
 *
 * Binnen de meldtermijn van 48 uur na levering - of eerder, zolang de order
 * onderweg is. De order gaat naar `disputed` en daarmee staat de uitbetaling
 * automatisch stil: de vrijgave-job weigert payouts met een open geschil.
 */
final class ReportProblem
{
    public function __construct(private readonly TransitionOrder $transition) {}

    public function handle(Order $order, User $buyer, string $reason, ?string $description = null): Dispute
    {
        if ($order->buyer_id !== $buyer->id) {
            throw ValidationException::withMessages(['order' => __('orders.errors.not_yours')]);
        }

        $reportable = in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::Shipped], true)
            || ($order->status === OrderStatus::Delivered && $order->isWithinDisputeWindow());

        if (! $reportable) {
            throw ValidationException::withMessages(['order' => __('orders.errors.dispute_window_closed')]);
        }

        return DB::transaction(function () use ($order, $buyer, $reason, $description): Dispute {
            $dispute = Dispute::create([
                'order_id' => $order->id,
                'reported_by' => $buyer->id,
                'reason' => $reason,
                'description' => $description,
                'status' => DisputeStatus::Reported,
                // De reactietermijn van de verkoper (72 uur) - de klok start
                // bij de melding, niet bij het eerste antwoord.
                'sla_due_at' => now()->addHours((int) config('aucvy.orders.seller_response_hours')),
            ]);

            $this->transition->handle($order, OrderStatus::Disputed, $buyer, [
                'dispute_id' => $dispute->id,
                'reason' => $reason,
            ]);

            return $dispute;
        });
    }
}
