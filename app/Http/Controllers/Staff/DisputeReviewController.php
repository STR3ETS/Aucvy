<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Orders\Actions\ResolveDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * De beoordelaarskant van de geschilroute: een medewerker, geen algoritme
 * (hfst. 9). Routes zitten achter de staff-gate; de uitkomst loopt door
 * dezelfde ResolveDispute-actie als overal, dus met motivering en auditlog.
 */
final class DisputeReviewController extends Controller
{
    public function index(): View
    {
        return view('staff.disputes.index', [
            'disputes' => Dispute::query()
                ->whereNotIn('status', ['resolved', 'rejected'])
                ->with(['order.lot', 'reporter'])
                ->orderBy('sla_due_at')
                ->get(),
        ]);
    }

    public function show(Dispute $dispute): View
    {
        return view('staff.disputes.show', [
            'dispute' => $dispute->load(['order.lot', 'order.payout', 'reporter']),
        ]);
    }

    public function resolve(Request $request, Dispute $dispute, ResolveDispute $action): RedirectResponse
    {
        // De uitkomsten uit de briefing plus "afgewezen" voor een melding die
        // niet terecht bleek. Alleen terugdraaien ontbindt de koop.
        $validated = $request->validate([
            'resolution' => ['required', Rule::in(['afgewezen', 'alsnog_leveren', 'prijscorrectie', 'terugdraaien'])],
            'notes' => ['required', 'string', 'max:4000'],
            // Een prijscorrectie zonder bedrag is een motivering, geen correctie.
            'correction' => ['required_if:resolution,prijscorrectie', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
        ]);

        $action->handle(
            $dispute,
            $request->user(),
            upheld: $validated['resolution'] !== 'afgewezen',
            resolution: $validated['resolution'],
            notes: $validated['notes'],
            correctionCents: $validated['resolution'] === 'prijscorrectie'
                ? (int) round((float) $validated['correction'] * 100)
                : null,
        );

        return redirect()->route('staff.disputes.index');
    }
}
