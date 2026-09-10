<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboard\DemandForecast;
use App\Dashboard\IntentFunnel;
use App\Dashboard\SignalSync;
use App\Dashboard\TeamActivity;
use App\Enums\AiAgent;
use App\Models\AiInsight;
use Illuminate\Http\Request;
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

        $isSeller = $request->query('rol') !== 'koper'
            && ($user->lots()->exists() || $request->query('rol') === 'verkoper');

        $insights = AiInsight::query()
            ->where('user_id', $user->id)
            ->current()
            ->orderByRaw("field(severity, 'urgent', 'attention', 'info')")
            ->get();

        return view('dashboard.index', [
            'isSeller' => $isSeller,
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
}
