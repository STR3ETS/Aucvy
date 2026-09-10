<?php

declare(strict_types=1);

namespace App\Ivy\Guards;

use App\Models\AiRun;
use App\Models\User;

/**
 * Twee remmen op de AI-kosten (hfst. 8): een dagbudget per gebruiker en een
 * totaalbudget per omgeving. Het totaal telt óók voor bezoekers zonder
 * account - de funnel werkt zonder login, en juist dat pad is anders
 * onbegrensd.
 *
 * Budget op is geen fout: de run wordt `skipped` en de applicatie werkt door.
 */
final class BudgetGuard
{
    /**
     * Waarom vandaag niet meer mag - of null als het nog kan.
     */
    public function refusalReason(?User $user): ?string
    {
        $today = AiRun::query()->where('created_at', '>=', now()->startOfDay());

        if ((clone $today)->sum('cost_cents') >= (int) config('aucvy.ai.daily_budget_cents_total')) {
            return 'Dagbudget van de omgeving is op.';
        }

        if ($user !== null) {
            $spent = (clone $today)->where('user_id', $user->id)->sum('cost_cents');

            if ($spent >= (int) config('aucvy.ai.daily_budget_cents_per_user')) {
                return 'Dagbudget van deze gebruiker is op.';
            }
        }

        return null;
    }
}
