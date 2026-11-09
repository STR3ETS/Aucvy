<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboard\SellerFinance;
use App\Models\Bid;
use App\Models\Dispute;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\Payout;
use App\Support\PlatformFacts;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * De lijstpagina's onder het dashboard (hfst. 7): kavels, biedingen,
 * uitbetalingen en geschillen. Bestellingen heeft zijn eigen route.
 */
final class DashboardListController extends Controller
{
    public function lots(Request $request): View
    {
        return view('dashboard.lots', [
            // Alleen de omslag per kavel als subquery: een bedrijf met duizenden
            // kavels laadt anders ook al zijn foto's als rijen.
            'lots' => Lot::query()
                ->whereIn('seller_id', $request->user()->lotSellerIds())
                ->select('lots.*')
                ->addSelect(['cover_path' => LotMedia::query()
                    ->select('path')
                    ->whereColumn('lot_media.lot_id', 'lots.id')
                    ->orderBy('position')
                    ->limit(1)])
                ->with(['auction', 'category', 'order.payout'])
                ->orderByRaw('published_at is null desc')
                ->latest('published_at')
                ->get(),
        ]);
    }

    public function bids(Request $request): View
    {
        /*
         | Eén regel per veiling: je laatste eigen bod telt, niet elke
         | tussenstap. De historie staat op de kavelpagina zelf.
         */
        $bids = Bid::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', fn ($query) => $query
                ->selectRaw('max(id)')
                ->from('bids')
                ->where('user_id', $request->user()->id)
                ->groupBy('auction_id'))
            ->with(['auction.lot.media', 'auction.lot.order'])
            ->latest('id')
            ->limit(500)
            ->get();

        return view('dashboard.bids', ['bids' => $bids]);
    }

    public function payouts(Request $request): View
    {
        abort_unless($request->user()->canSeeCompanyFinance(), 403);

        return view('dashboard.payouts', [
            'payouts' => Payout::query()
                ->whereIn('seller_id', $request->user()->financeSellerIds())
                ->with(['order.lot'])
                ->latest('id')
                ->get(),
            'company' => $request->user()->company,
        ]);
    }

    /**
     * Eén uitbetaling (ontwerp van november): de stand, het bedrag, de
     * verkoop waar hij bij hoort en de stappen met hun tijdstip.
     */
    public function payout(Request $request, Payout $payout): View
    {
        $user = $request->user();

        abort_unless($user->canSeeCompanyFinance() && in_array($payout->seller_id, $user->financeSellerIds(), true), 403);

        $payout->load(['order.lot.media']);

        return view('dashboard.payout', [
            'payout' => $payout,
            // De factuur van Aucvy voor de succesfee; anders de kavelfactuur.
            'invoice' => Invoice::query()->where('order_id', $payout->order_id)->whereIn('kind', [Invoice::FEE, Invoice::LOT])
                ->orderByRaw('kind = ? desc', [Invoice::FEE])->first(),
        ]);
    }

    /**
     * Financiën (briefing sectie 4): afrekening per verkoop, totalen per
     * maand, en een csv voor de boekhouding.
     */
    public function finance(Request $request, SellerFinance $finance): View
    {
        abort_unless($request->user()->canSeeCompanyFinance(), 403);

        $lines = $finance->lines($request->user());
        $perMonth = $finance->perMonth($lines);

        return view('dashboard.finance', [
            'lines' => $lines,
            'totals' => $finance->totals($lines),
            'perMonth' => $perMonth,
            'thisMonth' => $perMonth->get(now()->timezone(config('aucvy.display_timezone'))->format('Y-m'), $finance->totals(collect())),
            'invoices' => Invoice::query()->with('order')
                ->where(fn ($query) => $query->where('recipient_user_id', $request->user()->id)
                    ->orWhere('issuer_company_id', $request->user()->company_id)
                    ->orWhereHas('order', fn ($order) => $order->where('seller_id', $request->user()->id)))
                ->latest('id')->limit(100)->get(),
            'feePercentage' => PlatformFacts::feePercentageFor($request->user()->company),
            'tier' => [
                'revenue_cents' => PlatformFacts::revenueCentsFor($request->user()->company),
                'next' => PlatformFacts::nextTierFor(PlatformFacts::revenueCentsFor($request->user()->company)),
                'tiers' => PlatformFacts::feeTiers(),
            ],
        ]);
    }

    public function financeExport(Request $request, SellerFinance $finance): Response
    {
        abort_unless($request->user()->canSeeCompanyFinance(), 403);

        $csv = $finance->csv($finance->lines($request->user()));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="aucvy-verkopen-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    public function disputes(Request $request): View
    {
        return view('dashboard.disputes', [
            'disputes' => Dispute::query()
                ->whereHas('order', fn ($query) => $query
                    ->where('buyer_id', $request->user()->id)
                    ->orWhere('seller_id', $request->user()->id))
                ->with(['order.lot.media'])
                ->latest('id')
                ->get(),
        ]);
    }
}
