<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\User;
use App\Notifications\DisputeResolved;
use App\Notifications\MoneyNeedsAttention;
use App\Support\Audit\Audit;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Een medewerker beoordeelt het geschil - geen algoritme (hfst. 9).
 *
 * Vier uitkomsten: afgewezen of alsnog leveren (de order gaat door naar
 * `completed`), prijscorrectie (ook `completed`, maar met een bedrag dat van
 * de uitbetaling af gaat en terug moet naar de koper) en terugdraaien
 * (`cancelled`, de payout vervalt). Altijd met motivering; die gaat het
 * auditlog in.
 *
 * Geld terug naar een koper kan dit platform nog niet zelf (dat vraagt de
 * betaalprovider en een besluit over refunds); daarom krijgt support bij een
 * correctie of een terugdraai van een betaalde order een mail met het
 * bedrag, in plaats van dat het alleen in de motivering staat.
 */
final class ResolveDispute
{
    public function handle(
        Dispute $dispute,
        User $staff,
        bool $upheld,
        string $resolution,
        string $notes,
        ?int $correctionCents = null,
    ): Dispute {
        if (! $dispute->status->isOpen()) {
            throw ValidationException::withMessages(['dispute' => __('orders.errors.dispute_not_open')]);
        }

        if ($resolution === 'prijscorrectie' && ($correctionCents === null || $correctionCents <= 0)) {
            throw ValidationException::withMessages(['correction' => __('orders.errors.correction_required')]);
        }

        return DB::transaction(function () use ($dispute, $staff, $upheld, $resolution, $notes, $correctionCents): Dispute {
            $from = $dispute->status;
            $order = $dispute->order;
            $wasPaid = $order->status->isPaidOrLater();

            $dispute->status = $upheld ? DisputeStatus::Resolved : DisputeStatus::Rejected;
            $dispute->resolution = $resolution;
            $dispute->resolution_notes = $notes;
            $dispute->correction_cents = $resolution === 'prijscorrectie' ? $correctionCents : null;
            $dispute->handled_by = $staff->id;
            $dispute->resolved_at = now();
            $dispute->save();

            /*
             | `terugdraaien` is de enige uitkomst die de koop ontbindt; alle
             | andere routes ronden de order af zodat de uitbetaling weer kan
             | gaan lopen (de houdtermijn geldt onverkort).
             */
            app(TransitionOrder::class)->handle(
                $order,
                $resolution === 'terugdraaien' ? OrderStatus::Cancelled : OrderStatus::Completed,
                $staff,
                ['dispute_id' => $dispute->id, 'resolution' => $resolution],
            );

            if ($resolution === 'prijscorrectie') {
                $this->applyCorrection($dispute, $staff, (int) $correctionCents);
            } elseif ($resolution === 'terugdraaien' && $wasPaid) {
                $this->alertSupport(
                    'Terugbetaling aan koper: '.$order->order_number.' is teruggedraaid',
                    ['Het geschil bij bestelling '.$order->order_number.' is beslist als terugdraaien. De koper heeft '.Money::format($order->total_cents).' betaald en hoort dat terug te krijgen; dat loopt via de betaalprovider.'],
                    route('admin.orders.show', $order),
                );
            }

            AuditLog::create([
                'actor_id' => $staff->id,
                'action' => 'dispute.resolved',
                'subject_type' => $dispute->getMorphClass(),
                'subject_id' => $dispute->id,
                'before' => ['status' => $from->value],
                'after' => [
                    'status' => $dispute->status->value,
                    'resolution' => $resolution,
                    'notes' => $notes,
                    'correction_cents' => $dispute->correction_cents,
                ],
                'created_at' => now(),
            ]);

            // De uitkomst mét motivering gaat naar beide partijen (hfst. 9).
            $dispute->order->buyer->notify(new DisputeResolved($dispute));
            $dispute->order->seller->notify(new DisputeResolved($dispute));

            return $dispute;
        });
    }

    /**
     * De correctie gaat van de uitbetaling af (die staat nog in de
     * houdstand) en support hoort welk bedrag naar de koper terug moet.
     */
    private function applyCorrection(Dispute $dispute, User $staff, int $correctionCents): void
    {
        $order = $dispute->order;
        $payout = $order->payout()->first();

        if ($payout !== null && $payout->status !== PayoutStatus::Paid) {
            $before = (int) $payout->amount_cents;
            $payout->amount_cents = max(0, $before - $correctionCents);
            $payout->save();

            Audit::record('payout.corrected', $payout, $staff, before: ['amount_cents' => $before], after: [
                'amount_cents' => $payout->amount_cents,
                'correction_cents' => $correctionCents,
                'dispute_id' => $dispute->id,
            ]);
        }

        $this->alertSupport(
            'Terugbetaling aan koper: prijscorrectie op '.$order->order_number,
            [
                'Het geschil bij bestelling '.$order->order_number.' is beslist als prijscorrectie van '.Money::format($correctionCents).'.',
                $payout !== null && $payout->status !== PayoutStatus::Paid
                    ? 'De uitbetaling aan de verkoper is met dat bedrag verlaagd naar '.Money::format((int) $payout->amount_cents).'.'
                    : 'De uitbetaling aan de verkoper was al gedaan of bestaat niet; het bedrag moet apart met de verkoper verrekend worden.',
                'De koper hoort '.Money::format($correctionCents).' terug te krijgen; dat loopt via de betaalprovider.',
            ],
            route('admin.orders.show', $order),
        );
    }

    /**
     * @param  list<string>  $lines
     */
    private function alertSupport(string $subject, array $lines, string $url): void
    {
        DB::afterCommit(fn () => Notification::route('mail', (string) config('aucvy.support.email'))
            ->notify(new MoneyNeedsAttention($subject, $lines, $url)));
    }
}
