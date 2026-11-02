<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ErrorEvent;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het foutlog in beheer (besluit Raph, 2 november 2026): open fouten eerst,
 * met hoe vaak en waar, en afhandelen met een notitie. Afhandelen en
 * heropenen staan in het auditlog; komt een fout terug, dan opent hij
 * vanzelf weer.
 */
final class ErrorLogController extends Controller
{
    public function index(Request $request): View
    {
        $showResolved = $request->query('status') === 'afgehandeld';

        return view('admin.errors', [
            'events' => ErrorEvent::query()
                ->when($showResolved, fn ($query) => $query->whereNotNull('resolved_at'), fn ($query) => $query->open())
                ->with(['resolver', 'lastUser'])
                ->latest('last_seen_at')
                ->paginate(30)
                ->withQueryString(),
            'showResolved' => $showResolved,
            'openCount' => ErrorEvent::query()->open()->count(),
        ]);
    }

    public function resolve(Request $request, ErrorEvent $event): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $event->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'resolution_note' => $validated['note'] ?? null,
        ])->save();

        Audit::record('error.resolved', $event, $request->user(), after: ['class' => $event->class, 'note' => $validated['note'] ?? null], ip: $request->ip());

        return back()->with('status', 'Fout afgehandeld. Komt hij terug, dan staat hij weer open.');
    }

    public function reopen(Request $request, ErrorEvent $event): RedirectResponse
    {
        $event->forceFill(['resolved_at' => null])->save();

        Audit::record('error.reopened', $event, $request->user(), ip: $request->ip());

        return back()->with('status', 'Fout staat weer open.');
    }
}
