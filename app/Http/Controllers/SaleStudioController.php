<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\PublishLot;
use App\Lots\Sales\SaleStudio;
use App\Models\Lot;
use App\Models\Sale;
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
        return view('dashboard.sales', [
            'sales' => Sale::query()
                ->whereIn('seller_id', $request->user()->lotSellerIds())
                ->withCount(Sale::stateCounts())
                ->latest('id')
                ->limit(200)
                ->get(),
        ]);
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
