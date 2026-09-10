<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboard\DemandForecast;
use App\Dashboard\IntentFunnel;
use App\Dashboard\SignalSync;
use App\Dashboard\TeamActivity;
use App\Enums\AiAgent;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\AiInsight;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Het dashboard: geen cijferoverzicht maar een werkplan (hfst. 13.5).
 *
 * De volgorde van de blokken ís het ontwerp: eerst wat het team al deed, dan
 * wat jouw akkoord nodig heeft, dan pas de cijfers. De pagina doet geen
 * AI-calls - de deterministische signalen worden bij het laden ververst
 * (goedkope queries), de wekelijkse bevindingen komen uit de tabel.
 */
final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        SignalSync $signals,
        TeamActivity $activity,
        IntentFunnel $funnel,
        DemandForecast $forecast,
    ): View {
        $user = $request->user();

        $signals->sync($user);

        /*
         | Standaard verkopersmodus alleen bij échte kavels: wie enkel een
         | concept in de la heeft, is (nog) een koper en ziet geen lege
         | verkoopblokken. De toggle blijft voor iedereen werken.
         */
        $isSeller = $request->query('rol') !== 'koper'
            && ($user->lots()->whereNot('status', LotStatus::Draft)->exists()
                || $request->query('rol') === 'verkoper');

        $insights = AiInsight::query()
            ->where('user_id', $user->id)
            ->current()
            ->orderByRaw("field(severity, 'urgent', 'attention', 'info')")
            ->get();

        return view('dashboard.index', [
            'isSeller' => $isSeller,
            'greeting' => $this->greeting(),
            'firstName' => Str::before(trim($user->name), ' '),
            'revenueTrend' => $isSeller ? $this->revenueTrend($user) : null,
            'agents' => AiAgent::cases(),
            // Vraagt aandacht: kaarten mét actie. Geen kaart zonder knop.
            'attention' => $insights->filter(fn (AiInsight $insight): bool => $insight->hasAction()),
            // Wat Ivy deze week zag: observaties, bewust zonder knoppen.
            'weekly' => $insights->where('type', 'weekly'),
            'done' => $isSeller ? $activity->forSeller($user) : [],
            'intent' => $isSeller ? $funnel->forSeller($user) : null,
            'forecast' => $isSeller ? $forecast->forSeller($user) : null,
            'isNew' => ! $user->lots()->exists()
                && ! $user->purchases()->exists()
                && ! $user->bids()->exists(),
        ]);
    }

    private function greeting(): string
    {
        $hour = (int) now()->timezone(config('aucvy.display_timezone'))->format('H');

        return match (true) {
            $hour < 6 => 'Goedenacht',
            $hour < 12 => 'Goedemorgen',
            $hour < 18 => 'Goedemiddag',
            default => 'Goedenavond',
        };
    }

    /**
     * De opbrengsttrend uit preview v4: afgeronde verkopen van de laatste
     * dertig dagen tegenover de dertig dagen ervoor. Zonder eerdere omzet is
     * er geen trend - dan zeggen we ook niets.
     */
    private function revenueTrend(User $user): ?int
    {
        $current = (int) Order::query()
            ->where('seller_id', $user->id)
            ->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', now()->subDays(30))
            ->sum('hammer_cents');

        $previous = (int) Order::query()
            ->where('seller_id', $user->id)
            ->where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', [now()->subDays(60), now()->subDays(30)])
            ->sum('hammer_cents');

        if ($previous === 0) {
            return null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }
}
