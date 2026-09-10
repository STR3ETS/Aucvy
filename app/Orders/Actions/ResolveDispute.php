<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\User;
use App\Notifications\DisputeResolved;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Een medewerker beoordeelt het geschil - geen algoritme (hfst. 9).
 *
 * Drie uitkomsten uit de briefing: alsnog leveren of afwikkelen (de order
 * gaat door naar `completed`), of terugdraaien (`cancelled`, de payout
 * vervalt). Altijd met motivering; die gaat het auditlog in.
 *
 * Wie "medewerker" mag zijn is nog geen rol in het datamodel - de
 * beheeromgeving komt in stap 12. Tot die tijd is deze actie alleen vanaf
 * de command line aan te roepen en dwingt de code de motivering af.
 */
final class ResolveDispute
{
    public function handle(
        Dispute $dispute,
        User $staff,
        bool $upheld,
        string $resolution,
        string $notes,
    ): Dispute {
        if (! $dispute->status->isOpen()) {
            throw ValidationException::withMessages(['dispute' => __('orders.errors.dispute_not_open')]);
        }

        return DB::transaction(function () use ($dispute, $staff, $upheld, $resolution, $notes): Dispute {
            $from = $dispute->status;

            $dispute->status = $upheld ? DisputeStatus::Resolved : DisputeStatus::Rejected;
            $dispute->resolution = $resolution;
            $dispute->resolution_notes = $notes;
            $dispute->handled_by = $staff->id;
            $dispute->resolved_at = now();
            $dispute->save();

            /*
             | `terugdraaien` is de enige uitkomst die de koop ontbindt; alle
             | andere routes ronden de order af zodat de uitbetaling weer kan
             | gaan lopen (de houdtermijn geldt onverkort).
             */
            app(TransitionOrder::class)->handle(
                $dispute->order,
                $resolution === 'terugdraaien' ? OrderStatus::Cancelled : OrderStatus::Completed,
                $staff,
                ['dispute_id' => $dispute->id, 'resolution' => $resolution],
            );

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
                ],
                'created_at' => now(),
            ]);

            // De uitkomst mét motivering gaat naar beide partijen (hfst. 9).
            $dispute->order->buyer->notify(new DisputeResolved($dispute));
            $dispute->order->seller->notify(new DisputeResolved($dispute));

            return $dispute;
        });
    }
}
