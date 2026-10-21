<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Supporttickets in de backoffice (briefing sectie 7): lezen, per mail
 * beantwoorden en sluiten met een korte notitie.
 */
final class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $showClosed = $request->boolean('gesloten');

        return view('admin.support', [
            'tickets' => SupportTicket::query()
                ->when(! $showClosed, fn ($query) => $query->open())
                ->with(['user', 'closer'])
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
            'openCount' => SupportTicket::query()->open()->count(),
            'showClosed' => $showClosed,
        ]);
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        if (! $ticket->isOpen()) {
            return back()->with('status', __('support.already_closed', ['id' => $ticket->id]));
        }

        $validated = $request->validate(['reply' => ['nullable', 'string', 'max:2000']]);

        $ticket->status = SupportTicket::CLOSED;
        $ticket->closed_at = now();
        $ticket->closed_by = $request->user()->id;
        $ticket->reply = $validated['reply'] ?? null;
        $ticket->save();

        Audit::record('support.closed', $ticket, $request->user(), after: ['reply' => $ticket->reply]);

        return back()->with('status', __('support.closed', ['id' => $ticket->id]));
    }

    public function reopen(Request $request, SupportTicket $ticket): RedirectResponse
    {
        if ($ticket->isOpen()) {
            return back()->with('status', __('support.already_open', ['id' => $ticket->id]));
        }

        $ticket->status = SupportTicket::OPEN;
        $ticket->closed_at = null;
        $ticket->closed_by = null;
        $ticket->save();

        Audit::record('support.reopened', $ticket, $request->user());

        return back()->with('status', __('support.reopened', ['id' => $ticket->id]));
    }
}
