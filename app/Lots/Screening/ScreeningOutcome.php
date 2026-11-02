<?php

declare(strict_types=1);

namespace App\Lots\Screening;

use App\Enums\LotStatus;
use App\Lots\GoLive;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\ListingScreened;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Wat er met een ingediende kavel gebeurt na de controle (besluit Raph,
 * 2 november 2026, vervolgvraag V4 standaard (c)):
 *
 * - goedgekeurd: live;
 * - er ontbreekt of klopt iets dat de verkoper kan oplossen: terug naar
 *   concept, met de punten erbij;
 * - twijfel over echtheid, verboden waar of een controle die niet lukte:
 *   blijft in beoordeling en gaat naar een medewerker.
 *
 * Elke uitkomst staat in `lots.screening` en in het auditlog, en de
 * verkoper hoort hem.
 */
final class ScreeningOutcome
{
    public const string APPROVE = 'approve';

    public const string RETURN = 'return';

    public const string ESCALATE = 'escalate';

    public function __construct(private readonly GoLive $goLive) {}

    /**
     * @param  list<array{field: string, message: string}>  $issues
     */
    public function decide(Lot $lot, string $verdict, array $issues, ?int $runId = null, string $by = 'ivy'): void
    {
        $lot = $lot->fresh();

        // Intussen ingetrokken, goedgekeurd of al beslist: niets doen.
        if ($lot === null || $lot->status !== LotStatus::PendingReview) {
            return;
        }

        $screening = [
            'verdict' => $verdict,
            'issues' => array_values($issues),
            'by' => $by,
            'run_id' => $runId,
            'at' => now()->toIso8601String(),
        ];

        match ($verdict) {
            self::APPROVE => $this->approve($lot, $screening),
            self::RETURN => $this->returnToSeller($lot, $screening),
            default => $this->escalate($lot, $screening),
        };
    }

    /**
     * @param  array<string, mixed>  $screening
     */
    private function approve(Lot $lot, array $screening): void
    {
        $lot->forceFill(['screening' => $screening])->save();

        $live = $this->goLive->handle($lot, approvedBy: $screening['by']);

        $this->notify($live, ListingScreened::LIVE);
    }

    /**
     * @param  array<string, mixed>  $screening
     */
    private function returnToSeller(Lot $lot, array $screening): void
    {
        DB::transaction(function () use ($lot, $screening): void {
            $lot->forceFill([
                'status' => LotStatus::Draft,
                'screening' => $screening,
                'submitted_at' => null,
            ])->save();

            Audit::record('lot.returned', $lot, null, after: ['issues' => $screening['issues'], 'by' => $screening['by']]);
        });

        $this->notify($lot, ListingScreened::RETURNED);
    }

    /**
     * @param  array<string, mixed>  $screening
     */
    private function escalate(Lot $lot, array $screening): void
    {
        $lot->forceFill(['screening' => $screening])->save();

        Audit::record('lot.escalated', $lot, null, after: ['issues' => $screening['issues'], 'by' => $screening['by']]);

        $this->notify($lot, ListingScreened::ESCALATED);
    }

    private function notify(Lot $lot, string $outcome): void
    {
        $seller = $lot->seller_id === null ? null : User::query()->find($lot->seller_id);

        $seller?->notify(new ListingScreened($lot, $outcome));
    }
}
