<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboard\ActivityFeed;
use App\Dashboard\BuyerOverview;
use App\Dashboard\DemandForecast;
use App\Dashboard\HeadlineStats;
use App\Dashboard\IntentFunnel;
use App\Dashboard\InterestOverview;
use App\Dashboard\SellerCharts;
use App\Dashboard\SignalSync;
use App\Dashboard\TeamActivity;
use App\Enums\AiAgent;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\AiInsight;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Het dashboard: geen cijferoverzicht maar een werkplan (hfst. 13.5).
 *
 * Eerst wat je snel kunt doen (bieden, verkopen, de cijfers), dan één lijst
 * met taken, meldingen en tickets, dan het team en pas daarna de analyse.
 * De pagina doet geen AI-calls - de deterministische signalen worden bij het
 * laden ververst (goedkope queries), de wekelijkse bevindingen komen uit de
 * tabel.
 */
final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        SignalSync $signals,
        TeamActivity $activity,
        IntentFunnel $funnel,
        DemandForecast $forecast,
        HeadlineStats $stats,
        ActivityFeed $feed,
        SellerCharts $charts,
        InterestOverview $interest,
        BuyerOverview $buyer,
    ): View|RedirectResponse {
        $user = $request->user();

        // Beheer koopt en verkoopt niet: het werkplan is niet hun plek.
        if ($user->is_staff) {
            return redirect('/admin');
        }

        // Negen query's plus schrijfwerk per bezoek; één keer per minuut is
        // genoeg voor taken die op uren en dagen lopen. Nul zet de drempel uit.
        $syncSeconds = (int) config('aucvy.dashboard.signal_sync_seconds');

        if ($syncSeconds <= 0 || Cache::add("signals:synced:{$user->id}", 1, $syncSeconds)) {
            $signals->sync($user);
        }

        /*
         | Standaard verkopersmodus alleen bij échte kavels: wie enkel een
         | concept in de la heeft, is (nog) een koper en ziet geen lege
         | verkoopblokken. De toggle blijft voor iedereen werken.
         */
        $isSeller = $request->query('rol') !== 'koper'
            && (Lot::query()->whereIn('seller_id', $user->lotSellerIds())->whereNot('status', LotStatus::Draft)->exists()
                || $request->query('rol') === 'verkoper');

        $insights = AiInsight::query()
            ->where('user_id', $user->id)
            ->current()
            ->orderByRaw("field(severity, 'urgent', 'attention', 'info')")
            ->get();

        $trend = $isSeller ? $this->revenueTrend($user) : null;
        $weekly = $insights->where('type', 'weekly');

        return view('dashboard.index', [
            'isSeller' => $isSeller,
            'greeting' => $this->greeting(),
            'firstName' => Str::before(trim($user->name), ' '),
            'revenueTrend' => $trend,
            // De tegels in de band: de samenvatting per rol.
            'stats' => $isSeller ? $stats->forSeller($user, $trend) : $stats->forBuyer($user),
            'agents' => AiAgent::cases(),
            // Taken (signalen mét knop), meldingen en tickets in één lijst.
            'feed' => $feed->for($user, $insights->filter(fn (AiInsight $insight): bool => $insight->hasAction())),
            // Wat Ivy deze week zag: observaties, bewust zonder knoppen.
            'weekly' => $weekly,
            'done' => $isSeller ? $activity->forSeller($user) : [],
            'intent' => $isSeller ? $funnel->forSeller($user) : null,
            'forecast' => $isSeller ? $forecast->forSeller($user) : null,
            // Het ontwerp van november: de grafieken, de interesse per kavel en de lijsten.
            'revenue' => $isSeller ? $charts->revenueByMonth($user) : null,
            'hours' => $isSeller ? $charts->bidsPerHour($user) : null,
            'interest' => $isSeller ? $interest->forSeller($user, $weekly) : [],
            'liveLots' => $isSeller ? $interest->liveLots($user) : collect(),
            'drafts' => $isSeller ? $interest->drafts($user) : collect(),
            'openBids' => $isSeller ? [] : $buyer->openBids($user),
            'watching' => $isSeller ? collect() : $buyer->watching($user),
            'purchases' => $isSeller ? collect() : $buyer->purchases($user),
            'toPay' => $isSeller ? null : $buyer->toPay($user),
            'alerts' => $isSeller ? 0 : $buyer->activeAlerts($user),
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
            ->whereIn('seller_id', $user->financeSellerIds())
            ->where('status', OrderStatus::Completed)
            ->where('completed_at', '>=', now()->subDays(30))
            ->sum('hammer_cents');

        $previous = (int) Order::query()
            ->whereIn('seller_id', $user->financeSellerIds())
            ->where('status', OrderStatus::Completed)
            ->whereBetween('completed_at', [now()->subDays(60), now()->subDays(30)])
            ->sum('hammer_cents');

        if ($previous === 0) {
            return null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }
}
