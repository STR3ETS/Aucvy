<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Payout;
use Illuminate\Http\Request;
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
            'lots' => Lot::query()
                ->where('seller_id', $request->user()->id)
                ->with(['auction', 'category'])
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
            ->with(['auction.lot'])
            ->latest('id')
            ->get();

        return view('dashboard.bids', ['bids' => $bids]);
    }

    public function payouts(Request $request): View
    {
        return view('dashboard.payouts', [
            'payouts' => Payout::query()
                ->where('seller_id', $request->user()->id)
                ->with(['order.lot'])
                ->latest('id')
                ->get(),
        ]);
    }

    public function disputes(Request $request): View
    {
        return view('dashboard.disputes', [
            'disputes' => Dispute::query()
                ->whereHas('order', fn ($query) => $query
                    ->where('buyer_id', $request->user()->id)
                    ->orWhere('seller_id', $request->user()->id))
                ->with(['order.lot'])
                ->latest('id')
                ->get(),
        ]);
    }
}
