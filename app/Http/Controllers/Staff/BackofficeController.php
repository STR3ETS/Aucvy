<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Review;
use App\Models\User;
use App\Models\Viewing;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * De backoffice op /admin: alles inzien, in dezelfde huisstijl als de
 * rest van het platform. Lijsten zijn doorzoekbaar en gepagineerd;
 * muteren blijft beperkt tot wat een medewerker echt nodig heeft.
 */
final class BackofficeController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'stats' => [
                ['Live kavels', Lot::query()->whereIn('status', [LotStatus::Live, LotStatus::Closing])->count(), route('admin.lots')],
                ['Biedingen (24 uur)', Bid::query()->where('created_at', '>=', now()->subDay())->count(), route('admin.bids')],
                ['Open orders', Order::query()->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->count(), route('admin.orders')],
                ['Omzet 30 dagen', Money::format((int) Order::query()->where('status', OrderStatus::Completed)->where('completed_at', '>=', now()->subDays(30))->sum('hammer_cents')), route('admin.orders')],
                ['Gebruikers', User::query()->count(), route('admin.users')],
                ['Open geschillen', Dispute::query()->whereNull('resolved_at')->count(), route('staff.disputes.index')],
            ],
            'recentOrders' => Order::query()->with(['lot', 'buyer'])->latest('id')->limit(8)->get(),
        ]);
    }

    public function lots(Request $request): View
    {
        return view('admin.lots', [
            'lots' => Lot::query()
                ->with(['seller', 'category', 'auction'])
                ->when($request->query('q'), fn ($query, $term) => $query->where(
                    fn ($inner) => $inner->where('title', 'like', "%{$term}%")->orWhere('public_id', $term),
                ))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function auctions(Request $request): View
    {
        return view('admin.auctions', [
            'auctions' => Auction::query()
                ->with('lot')
                ->when($request->query('q'), fn ($query, $term) => $query
                    ->whereHas('lot', fn ($inner) => $inner->where('title', 'like', "%{$term}%")))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function bids(Request $request): View
    {
        return view('admin.bids', [
            'bids' => Bid::query()
                ->with(['user', 'auction.lot'])
                ->when($request->query('q'), fn ($query, $term) => $query
                    ->whereHas('user', fn ($inner) => $inner->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('auction.lot', fn ($inner) => $inner->where('title', 'like', "%{$term}%")))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function orders(Request $request): View
    {
        return view('admin.orders', [
            'orders' => Order::query()
                ->with(['lot', 'buyer', 'seller'])
                ->when($request->query('q'), fn ($query, $term) => $query
                    ->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('lot', fn ($inner) => $inner->where('title', 'like', "%{$term}%")))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function payouts(Request $request): View
    {
        return view('admin.payouts', [
            'payouts' => Payout::query()
                ->with(['seller', 'order.lot'])
                ->when($request->query('q'), fn ($query, $term) => $query
                    ->whereHas('seller', fn ($inner) => $inner->where('name', 'like', "%{$term}%")))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function users(Request $request): View
    {
        return view('admin.users', [
            'users' => User::query()
                ->withTrashed()
                ->withCount(['lots', 'bids'])
                ->when($request->query('q'), fn ($query, $term) => $query
                    ->where(fn ($inner) => $inner->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function companies(Request $request): View
    {
        return view('admin.companies', [
            'companies' => Company::query()
                ->when($request->query('q'), fn ($query, $term) => $query
                    ->where('name', 'like', "%{$term}%")->orWhere('kvk_number', 'like', "%{$term}%"))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function reviews(Request $request): View
    {
        return view('admin.reviews', [
            'reviews' => Review::query()
                ->with(['reviewer', 'seller', 'order.lot'])
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function viewings(Request $request): View
    {
        return view('admin.viewings', [
            'viewings' => Viewing::query()
                ->with(['user', 'lot'])
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function aiRuns(Request $request): View
    {
        return view('admin.ai-runs', [
            'runs' => AiRun::query()
                ->when($request->query('q'), fn ($query, $term) => $query->where('task', 'like', "%{$term}%"))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'totalCostCents' => (int) AiRun::query()->sum('cost_cents'),
        ]);
    }
}
