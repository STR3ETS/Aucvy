<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Admin\Ledger;
use App\Enums\DepositStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\MoneyNeedsAttention;
use App\Notifications\OrderStatusChanged;
use App\Notifications\ReviewRequested;
use App\Support\Audit\Audit;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * De enige plek waar een order van status verandert.
 *
 * De statusmachine uit hfst. 9 wordt hier afgedwongen: een verboden overgang
 * is een testbare fout, geen stille update. Elke overgang schrijft naar
 * `audit_logs` en sleept zijn bijwerkingen mee - tijdstempels, de
 * payout-lifecycle, de borg-verrekening en de kavelstatus. Wie een order wil
 * verplaatsen zónder die bijwerkingen, hoort hier niet langs te kunnen.
 */
final class TransitionOrder
{
    /**
     * @param  array<string, mixed>  $context  Extra uitleg voor het auditlog.
     */
    public function handle(Order $order, OrderStatus $to, ?User $actor = null, array $context = []): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $context): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Idempotent: nogmaals dezelfde status is geen fout maar een no-op
            // - webhooks en scheduled jobs mogen elkaar overlappen.
            if ($locked->status === $to) {
                return $locked;
            }

            if (! $locked->status->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => __('orders.errors.invalid_transition', [
                        'from' => $locked->status->label(),
                        'to' => $to->label(),
                    ]),
                ]);
            }

            $from = $locked->status;
            $locked->status = $to;

            match ($to) {
                OrderStatus::Paid => $this->onPaid($locked),
                OrderStatus::ReadyForPickup, OrderStatus::Shipped => $locked->shipped_at = now(),
                OrderStatus::Delivered => $this->onDelivered($locked),
                OrderStatus::Completed => $this->onCompleted($locked),
                OrderStatus::Cancelled => $this->onCancelled($locked, $context),
                OrderStatus::Disputed, OrderStatus::AwaitingPayment => null,
            };

            $locked->save();

            AuditLog::create([
                'actor_id' => $actor?->id,
                'action' => 'order.transition',
                'subject_type' => $locked->getMorphClass(),
                'subject_id' => $locked->id,
                'before' => ['status' => $from->value],
                'after' => ['status' => $to->value, ...$context],
                'created_at' => now(),
            ]);

            $this->notifyParties($locked, $to);

            return $locked;
        });
    }

    /**
     * "Elke overgang stuurt beide partijen een bericht" (hfst. 9) - wie wat
     * krijgt staat hier; de teksten in lang/nl/notifications.php. De
     * notificaties wachten op de commit; een rollback mailt niet.
     */
    private function notifyParties(Order $order, OrderStatus $to): void
    {
        $roles = match ($to) {
            OrderStatus::ReadyForPickup, OrderStatus::Shipped => ['buyer'],
            OrderStatus::Paid, OrderStatus::Delivered, OrderStatus::Completed,
            OrderStatus::Disputed, OrderStatus::Cancelled => ['buyer', 'seller'],
            default => [],
        };

        foreach ($roles as $role) {
            $order->{$role}->notify(new OrderStatusChanged($order, $to, $role));
        }

        // Het reviewverzoek (masterbriefing 4.3): één keer, alleen de koper,
        // alleen na afronding en nooit als er al een beoordeling staat.
        if ($to === OrderStatus::Completed && $order->review()->doesntExist()) {
            $order->buyer->notify(new ReviewRequested($order));
        }
    }

    /**
     * Betaald: de payout ontstaat hier - het bedrag komt uit dezelfde
     * Money-klasse als elke andere plek - en de borg van de winnaar wordt
     * verrekend. De echte pre-autorisatie-verzilvering is providerwerk dat
     * met de derdengeldenkeuze uit hfst. 18 meekomt; tot die tijd is
     * "verrekend" hier: vrijgegeven zodra de volledige betaling binnen is.
     */
    private function onPaid(Order $order): void
    {
        $order->paid_at = now();

        $proceeds = Money::payoutFor($order->hammer_cents, $order->seller->company);

        Payout::create([
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'amount_cents' => $proceeds->payoutCents,
            'fee_cents' => $proceeds->feeCents,
            'status' => PayoutStatus::Scheduled,
        ]);

        // Het grootboek van het platform (control center): fee,
        // administratiekosten en betaalkosten, idempotent per order.
        app(Ledger::class)->recordPaidOrder($order->fresh(['payout', 'seller.company']));

        if ($order->auction_id !== null) {
            Deposit::query()
                ->where('auction_id', $order->auction_id)
                ->where('user_id', $order->buyer_id)
                ->where('status', DepositStatus::Authorised)
                ->update(['status' => DepositStatus::Released, 'released_at' => now()]);
        }
    }

    /**
     * Geleverd: de meldtermijn gaat lopen en de payout gaat in de houdstand.
     * De zekerheidspagina belooft die termijnen; hier worden ze een kolom.
     */
    private function onDelivered(Order $order): void
    {
        $order->delivered_at = now();
        $order->dispute_window_ends_at = now()->addHours((int) config('aucvy.orders.dispute_window_hours'));

        $order->payout?->update([
            'status' => PayoutStatus::Held,
            'hold_until' => now()->addHours((int) config('aucvy.orders.payout_hold_hours')),
        ]);
    }

    /**
     * Afgerond. Kwam de order hier via een geschil zonder ooit "geleverd" te
     * zijn, dan staat de payout nog op "gepland" en zou hij nooit vrijkomen:
     * dan gaat hij nu in de houdstand, zonder wachttijd, want een mens heeft
     * het geschil al beoordeeld.
     */
    private function onCompleted(Order $order): void
    {
        $order->completed_at = now();

        if ($order->payout?->status === PayoutStatus::Scheduled) {
            $order->payout->update(['status' => PayoutStatus::Held, 'hold_until' => now()]);
        }
    }

    /**
     * Geannuleerd - niet betaald of teruggedraaid na een geschil. De kavel
     * gaat terug naar de verkoper en een eventuele payout vervalt. Bleef de
     * winnaar in gebreke, dan blijft zijn borg staan en hoort support dat:
     * innen gebeurt pas als de betaalprovider dat kan.
     *
     * @param  array<string, mixed>  $context
     */
    private function onCancelled(Order $order, array $context = []): void
    {
        $order->payout?->update([
            'status' => PayoutStatus::Failed,
            'failure_reason' => 'Order geannuleerd.',
        ]);

        if ($order->auction_id !== null) {
            $deposit = Deposit::query()
                ->where('auction_id', $order->auction_id)
                ->where('user_id', $order->buyer_id)
                ->where('status', DepositStatus::Authorised)
                ->first();

            if ($deposit !== null && ($context['reason'] ?? null) === 'payment_deadline_expired') {
                Audit::record('deposit.capture_pending', $deposit, null, after: ['order_id' => $order->id, 'amount_cents' => $deposit->amount_cents]);
                DB::afterCommit(fn () => Notification::route('mail', (string) config('aucvy.support.email'))->notify(new MoneyNeedsAttention(
                    'Borg innen: winnaar van '.$order->order_number.' heeft niet betaald',
                    ['De koper van bestelling '.$order->order_number.' heeft de betaaltermijn laten verlopen. Zijn borg van '.Money::format($deposit->amount_cents).' staat nog gereserveerd en hoort volgens de voorwaarden ingehouden te worden.'],
                    route('admin.orders.show', $order),
                )));
            } elseif ($deposit !== null) {
                $deposit->update(['status' => DepositStatus::Released, 'released_at' => now()]);
            }
        }

        $lot = $order->lot;

        if ($lot->status === LotStatus::Sold) {
            $lot->update(['status' => LotStatus::Unsold]);
        }
    }
}
