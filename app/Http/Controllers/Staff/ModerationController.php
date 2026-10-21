<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Lots\Moderation\ApproveLot;
use App\Lots\Moderation\RejectLot;
use App\Models\Lot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Moderatie: elke gepubliceerde kavel komt één keer langs zolang hij nog
 * loopt (gepland, live, slotfase). Goedkeuren legt de beoordeling vast;
 * afwijzen haalt de kavel offline met een reden. Afgelopen kavels zijn
 * geen moderatiewerk meer.
 */
final class ModerationController extends Controller
{
    public function index(): View
    {
        return view('admin.moderation', [
            'lots' => Lot::query()
                ->whereIn('status', [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing])
                ->whereNotNull('published_at')
                ->whereNull('reviewed_at')
                ->with(['seller', 'company', 'category', 'media', 'auction', 'documents'])
                ->oldest('published_at')
                ->paginate(20),
            'reviewedToday' => Lot::query()->whereDate('reviewed_at', today())->count(),
        ]);
    }

    public function approve(Request $request, Lot $lot, ApproveLot $approve): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $approve->handle($lot, $request->user(), $validated['note'] ?? null);

        return back()->with('status', __('moderation.approved', ['lot' => $lot->title]));
    }

    public function reject(Request $request, Lot $lot, RejectLot $reject): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        $reject->handle($lot, $request->user(), $validated['reason']);

        return back()->with('status', __('moderation.rejected', ['lot' => $lot->title]));
    }
}
