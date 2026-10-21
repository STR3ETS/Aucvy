<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Lots\DeleteDraft;
use App\Models\AiRun;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Review;
use App\Models\Sale;
use App\Models\User;
use App\Models\Viewing;
use App\Risk\RiskScan;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
            'stats' => $this->overviewStats(),
            'feed' => $this->activityFeed(),
        ]);
    }

    /**
     * Het pollbare deel van het overzicht: cijfers plus activiteitenfeed.
     * Het overzicht ververst dit blok elke paar seconden zonder reload,
     * zodat beheer dezelfde levendigheid ziet als een bezoeker.
     */
    public function activity(): View
    {
        return view('admin.partials.activity', [
            'stats' => $this->overviewStats(),
            'feed' => $this->activityFeed(),
        ]);
    }

    /**
     * @return array<int, array{0: string, 1: int|string, 2: string}>
     */
    private function overviewStats(): array
    {
        return [
            ['Live kavels', Lot::query()->whereIn('status', [LotStatus::Live, LotStatus::Closing])->count(), route('admin.lots')],
            ['Biedingen (24 uur)', Bid::query()->where('placed_at', '>=', now()->subDay())->count(), route('admin.bids')],
            ['Open orders', Order::query()->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Cancelled])->count(), route('admin.orders')],
            ['Omzet 30 dagen', Money::format((int) Order::query()->where('status', OrderStatus::Completed)->where('completed_at', '>=', now()->subDays(30))->sum('hammer_cents')), route('admin.orders')],
            ['Gebruikers', User::query()->count(), route('admin.users')],
            ['Open geschillen', Dispute::query()->whereNull('resolved_at')->count(), route('staff.disputes.index')],
            ['Risicosignalen', app(RiskScan::class)->count(), route('admin.risk')],
            ['Mislukte jobs', DB::table('failed_jobs')->count(), route('admin.queue')],
        ];
    }

    /**
     * Recente biedingen, plaatsingen en orders door elkaar, nieuwste eerst.
     * Alles wat hier langskomt is een echte databaserij - ook de simulator
     * plaatst echte biedingen, dus die verschijnen hier vanzelf.
     *
     * @return Collection<int, array{at: CarbonInterface, name: string, text: string, url: string}>
     */
    private function activityFeed(): Collection
    {
        $bids = Bid::query()
            ->with(['user', 'auction.lot'])
            ->latest('id')->limit(12)->get()
            ->map(fn (Bid $bid): array => [
                'at' => $bid->created_at,
                'name' => $bid->user->name,
                'text' => 'bood '.Money::format($bid->amount_cents).' op '.$bid->auction->lot->title,
                'url' => route('admin.lots.show', $bid->auction->lot),
            ]);

        $lots = Lot::query()
            ->with('seller')
            ->whereNotNull('published_at')
            ->latest('published_at')->limit(6)->get()
            ->map(fn (Lot $lot): array => [
                'at' => $lot->published_at,
                'name' => $lot->seller->name,
                'text' => 'plaatste '.$lot->title,
                'url' => route('admin.lots.show', $lot),
            ]);

        $orders = Order::query()
            ->with(['buyer', 'lot'])
            ->latest('id')->limit(6)->get()
            ->map(fn (Order $order): array => [
                'at' => $order->created_at,
                'name' => $order->buyer->name,
                'text' => 'kocht '.$order->lot->title.' voor '.Money::format($order->total_cents),
                'url' => route('admin.orders.show', $order),
            ]);

        return $bids->concat($lots)->concat($orders)
            ->sortByDesc('at')
            ->take(14)
            ->values();
    }

    public function lots(Request $request): View
    {
        return view('admin.lots', [
            'lots' => Lot::query()
                ->with(['seller', 'category', 'auction'])
                ->when($request->query('q'), fn ($query, $term) => $query->where(
                    fn ($inner) => $inner->where('title', 'like', "%{$term}%")->orWhere('public_id', $term),
                ))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    /**
     * Kaveldetail binnen de backoffice: alles over één kavel op één scherm,
     * zonder de beheerder naar de publieke site te sturen.
     */
    public function showLot(Lot $lot): View
    {
        $lot->load(['seller', 'company', 'category', 'location', 'media', 'documents', 'auction', 'order', 'viewings.user']);

        return view('admin.lots.show', [
            'lot' => $lot,
            'bids' => $lot->auction?->bids()->with('user')->latest('id')->limit(25)->get() ?? collect(),
        ]);
    }

    /**
     * Bulk: concepten opruimen. Alleen concepten - alles met een publieke
     * status of een verkoop erachter blijft onaangeroerd staan.
     */
    public function destroyDraftLots(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'max:100'], 'ids.*' => ['integer']]);

        $drafts = Lot::query()
            ->whereIn('id', $validated['ids'])
            ->where('status', LotStatus::Draft)
            ->get();

        $drafts->each(fn (Lot $lot) => app(DeleteDraft::class)->handle($lot, $request->user()));

        $deleted = $drafts->count();
        $skipped = count($validated['ids']) - $deleted;

        return back()->with('status', trim(
            "{$deleted} ".($deleted === 1 ? 'concept' : 'concepten').' verwijderd.'
            .($skipped > 0 ? " {$skipped} overgeslagen (geen concept)." : ''),
        ));
    }

    public function auctions(Request $request): View
    {
        return view('admin.auctions', [
            'auctions' => Auction::query()
                ->with('lot')
                ->when($request->query('q'), fn ($query, $term) => $query
                    ->whereHas('lot', fn ($inner) => $inner->where('title', 'like', "%{$term}%")))
                ->when($request->query('vorm'), fn ($query, $type) => $query
                    ->whereHas('lot', fn ($inner) => $inner->where('sale_type', $type)))
                ->when($request->query('status') === 'loopt', fn ($query) => $query->whereNull('closed_at'))
                ->when($request->query('status') === 'gesloten', fn ($query) => $query->whereNotNull('closed_at'))
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
                ->when($request->query('q'), fn ($query, $term) => $query->where(fn ($search) => $search
                    ->whereHas('user', fn ($inner) => $inner->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('auction.lot', fn ($inner) => $inner->where('title', 'like', "%{$term}%"))))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
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
                ->when($request->query('q'), fn ($query, $term) => $query->where(fn ($search) => $search
                    ->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('lot', fn ($inner) => $inner->where('title', 'like', "%{$term}%"))))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function showOrder(Order $order): View
    {
        $order->load(['lot', 'buyer', 'seller', 'payments', 'payout', 'disputes.reporter', 'review.reviewer', 'messages.author']);

        return view('admin.orders.show', ['order' => $order]);
    }

    /**
     * Gebundelde veilingen uit Auction Studio (meerdere kavels per veiling).
     */
    public function sales(Request $request): View
    {
        return view('admin.sales', [
            'sales' => Sale::query()
                ->with(['seller', 'company'])
                ->withCount(Sale::stateCounts())
                ->when($request->query('q'), fn ($query, $term) => $query
                    ->where(fn ($inner) => $inner
                        ->where('title', 'like', "%{$term}%")
                        ->orWhereHas('seller', fn ($seller) => $seller->where('name', 'like', "%{$term}%"))))
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
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
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
                ->when($request->query('kyc'), fn ($query, $status) => $query->where('kyc_status', $status))
                ->when($request->query('rol') === 'beheer', fn ($query) => $query->where('is_staff', true))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function showUser(User $user): View
    {
        $user->load('company')->loadCount(['lots', 'bids']);

        return view('admin.users.show', [
            'user' => $user,
            'lots' => $user->lots()->with('category')->latest('id')->limit(8)->get(),
            'bids' => $user->bids()->with('auction.lot')->latest('id')->limit(10)->get(),
            'purchases' => Order::query()->with('lot')->where('buyer_id', $user->id)->latest('id')->limit(8)->get(),
            'sales' => Order::query()->with('lot')->where('seller_id', $user->id)->latest('id')->limit(8)->get(),
        ]);
    }

    public function companies(Request $request): View
    {
        return view('admin.companies', [
            'companies' => Company::query()
                ->when($request->query('q'), fn ($query, $term) => $query->where(fn ($search) => $search
                    ->where('name', 'like', "%{$term}%")->orWhere('kvk_number', 'like', "%{$term}%")))
                ->when($request->query('status') === 'geverifieerd', fn ($query) => $query->whereNotNull('verified_at'))
                ->when($request->query('status') === 'ongeverifieerd', fn ($query) => $query->whereNull('verified_at'))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function showCompany(Company $company): View
    {
        return view('admin.companies.show', [
            'company' => $company,
            'users' => $company->users()->withCount(['lots', 'bids'])->get(),
            'lots' => $company->lots()->with('category')->latest('id')->limit(10)->get(),
            'reviews' => $company->reviews()->with('reviewer')->latest('id')->limit(8)->get(),
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
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
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
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
            'totalCostCents' => (int) AiRun::query()->sum('cost_cents'),
        ]);
    }

    /**
     * Bulk: AI-runs opschonen. Logregels, geen transacties - de reguliere
     * bewaartermijn ruimt ze anders na twaalf maanden op.
     */
    public function destroyAiRuns(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);

        $deleted = AiRun::query()->whereIn('id', $validated['ids'])->delete();

        return back()->with('status', "{$deleted} AI-runs verwijderd.");
    }
}
