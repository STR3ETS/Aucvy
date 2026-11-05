<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\PublishLot;
use App\Lots\Sales\SaleStudio;
use App\Models\Lot;
use App\Models\LotSignal;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Auction Studio in het dashboard (briefing sectie 4): veilingen plannen,
 * kavels erin zetten, publiceren, annuleren.
 */
final class SaleStudioController extends Controller
{
    public function __construct(private readonly SaleStudio $studio) {}

    public function index(Request $request): View
    {
        $sales = Sale::query()
            ->whereIn('seller_id', $request->user()->lotSellerIds())
            ->withCount(Sale::stateCounts())
            ->with(['lots.auction', 'lots.media'])
            ->latest('id')
            ->limit(200)
            ->get();

        return view('dashboard.sales', [
            'sales' => $sales,
            'summaries' => $this->summaries($sales),
        ]);
    }

    /**
     * De cijfers op de veilingkaart (ontwerp van november): biedingen, het
     * huidige totaal en de gemeten interesse over alle kavels van de veiling.
     * Eén query voor de interesse van alle veilingen samen.
     *
     * @param  Collection<int, Sale>  $sales
     * @return array<int, array{bids: int, total_cents: int, viewers: int, saved: int, shared: int, hot: bool, cover: string|null}>
     */
    private function summaries(Collection $sales): array
    {
        $signals = LotSignal::query()
            ->whereIn('lot_id', $sales->flatMap(fn (Sale $sale) => $sale->lots->pluck('id')))
            ->whereIn('kind', [LotSignal::VIEWER, LotSignal::SAVED, LotSignal::SHARED])
            ->selectRaw('lot_id, kind, count(*) as aantal')
            ->groupBy('lot_id', 'kind')
            ->get()
            ->groupBy('lot_id');

        $summaries = [];

        foreach ($sales as $sale) {
            $count = fn (string $kind): int => (int) $sale->lots->sum(fn (Lot $lot): int => (int) ($signals->get($lot->id)?->firstWhere('kind', $kind)?->getAttribute('aantal') ?? 0));

            $summaries[$sale->id] = [
                'bids' => (int) $sale->lots->sum(fn (Lot $lot): int => (int) ($lot->auction?->bid_count ?? 0)),
                // Alleen kavels waarop geboden is tellen mee: een startprijs is nog geen opbrengst.
                'total_cents' => (int) $sale->lots->sum(fn (Lot $lot): int => ($lot->auction?->bid_count ?? 0) > 0 ? (int) $lot->auction->current_price_cents : 0),
                'viewers' => $count(LotSignal::VIEWER),
                'saved' => $count(LotSignal::SAVED),
                'shared' => $count(LotSignal::SHARED),
                'hot' => $sale->lots->contains(fn (Lot $lot): bool => (bool) $lot->is_hot),
                'cover' => $sale->lots->map(fn (Lot $lot) => $lot->media->first()?->path)->filter()->first(),
            ];
        }

        return $summaries;
    }

    public function create(): View
    {
        return view('dashboard.sale-form');
    }

    public function store(Request $request): RedirectResponse
    {
        $sale = $this->studio->create($request->user(), $this->validated($request));

        return redirect()->route('dashboard.sales.edit', $sale)->with('status', __('sales.created'));
    }

    public function edit(Request $request, Sale $sale): View
    {
        $this->own($request, $sale);

        return view('dashboard.sale-edit', [
            'sale' => $sale->load(['lots.auction', 'lots.media']),
            'state' => $sale->state(),
            'attachable' => $this->studio->attachableFor($request->user()),
            'failed' => session('failed', []),
        ]);
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $this->own($request, $sale);

        $result = $this->studio->update($sale, $request->user(), $this->validated($request));

        $message = $result['rescheduled'] > 0
            ? __('sales.rescheduled', ['count' => $result['rescheduled']]).($result['kept'] > 0 ? __('sales.kept', ['count' => $result['kept']]) : '')
            : __('sales.updated');

        return redirect()->route('dashboard.sales.edit', $sale)->with('status', $message);
    }

    public function attach(Request $request, Sale $sale): RedirectResponse
    {
        $this->own($request, $sale);

        $validated = $request->validate([
            'lots' => ['required', 'array', 'min:1', 'max:100'],
            'lots.*' => ['integer'],
        ]);

        $count = $this->studio->attachMany($sale, $request->user(), Lot::query()->whereIn('id', $validated['lots'])->get());

        return redirect()->route('dashboard.sales.edit', $sale)->with('status', trans_choice('sales.attached', $count, ['count' => $count]));
    }

    public function detach(Request $request, Sale $sale, Lot $lot): RedirectResponse
    {
        $this->own($request, $sale);

        $this->studio->detach($sale, $request->user(), $lot);

        return redirect()->route('dashboard.sales.edit', $sale)->with('status', __('sales.detached'));
    }

    public function move(Request $request, Sale $sale, Lot $lot): RedirectResponse
    {
        $this->own($request, $sale);

        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        $this->studio->move($sale, $request->user(), $lot, $validated['direction'] === 'up' ? -1 : 1);

        return redirect()->route('dashboard.sales.edit', $sale);
    }

    public function publish(Request $request, Sale $sale): RedirectResponse
    {
        $this->own($request, $sale);

        $this->studio->publish($sale, $request->user());

        return redirect()->route('dashboard.sales.edit', $sale)->with('status', __('sales.published'));
    }

    public function publishDrafts(Request $request, Sale $sale, PublishLot $publish): RedirectResponse
    {
        $this->own($request, $sale);

        $result = $this->studio->publishDrafts($sale, $request->user(), $publish, $request->ip());

        $message = trans_choice('sales.drafts_published', $result['published'], ['count' => $result['published']])
            .($result['failed'] !== [] ? __('sales.drafts_failed', ['count' => count($result['failed'])]) : '');

        return redirect()->route('dashboard.sales.edit', $sale)->with('status', $message)->with('failed', $result['failed']);
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $this->own($request, $sale);

        $this->studio->cancel($sale, $request->user());

        return redirect()->route('dashboard.sales')->with('status', __('sales.cancelled'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'ends_at' => ['required', 'date'],
            'closing_gap_seconds' => ['required', 'integer', Rule::in([60, 120, 300, 600])],
            'viewing_at' => ['nullable', 'date'],
            'viewing_note' => ['nullable', 'string', 'max:500'],
            'pickup_from' => ['nullable', 'date'],
            'pickup_until' => ['nullable', 'date', 'after_or_equal:pickup_from'],
            'pickup_note' => ['nullable', 'string', 'max:500'],
            'terms' => ['nullable', 'string', 'max:8000'],
        ]);
    }

    private function own(Request $request, Sale $sale): void
    {
        abort_unless($sale->isManagedBy($request->user()), 404);
    }
}
