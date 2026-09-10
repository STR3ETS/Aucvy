<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Enums\DisputeStatus;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * De verkoper reageert op een melding - binnen de 72 uur van de SLA.
 *
 * Ook een te late reactie wordt aangenomen: de klok is er voor de
 * beoordelaar (die ziet dat de termijn verstreken was), niet om de verkoper
 * de mond te snoeren. Na de reactie ligt het geschil bij een medewerker.
 */
final class RespondToDispute
{
    public function handle(Dispute $dispute, User $seller, string $response): Dispute
    {
        if ($dispute->order->seller_id !== $seller->id) {
            throw ValidationException::withMessages(['dispute' => __('orders.errors.not_yours')]);
        }

        if ($dispute->status !== DisputeStatus::Reported) {
            throw ValidationException::withMessages(['dispute' => __('orders.errors.dispute_not_open')]);
        }

        $dispute->seller_response = $response;
        $dispute->seller_responded_at = now();
        $dispute->status = DisputeStatus::UnderReview;
        $dispute->save();

        AuditLog::create([
            'actor_id' => $seller->id,
            'action' => 'dispute.seller_responded',
            'subject_type' => $dispute->getMorphClass(),
            'subject_id' => $dispute->id,
            'before' => ['status' => DisputeStatus::Reported->value],
            'after' => ['status' => DisputeStatus::UnderReview->value],
            'created_at' => now(),
        ]);

        return $dispute;
    }
}
