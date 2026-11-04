<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Admin\Tickets;
use App\Http\Controllers\Controller;
use App\Ivy\StartTask;
use App\Ivy\Tasks\DraftTicketReply;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Klantenservice (control center): tickets, klachten en geschillen met
 * een concept van Ivy. Ivy maakt alleen concepten; een medewerker
 * verstuurt. Een ticket gaat met één handeling door naar een geschil.
 */
final class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->boolean('gesloten') ? 'gesloten' : (string) $request->query('filter', 'open');

        return view('admin.tickets.index', [
            'filter' => $filter,
            'tickets' => SupportTicket::query()
                ->with(['user', 'assignee', 'lot', 'order'])
                ->when($filter === 'open', fn ($query) => $query->open())
                ->when($filter === 'telaat', fn ($query) => $query->late())
                ->when($filter === 'klachten', fn ($query) => $query->open()->where('type', SupportTicket::TYPE_COMPLAINT))
                ->when($filter === 'mijn', fn ($query) => $query->open()->where('assigned_to', $request->user()->id))
                ->when($filter === 'gesloten', fn ($query) => $query->where('status', SupportTicket::CLOSED))
                ->orderByRaw("case when priority = 'high' then 0 when priority = 'normal' then 1 else 2 end")
                ->orderBy('first_response_due_at')
                ->paginate(30)
                ->withQueryString(),
            'counts' => [
                'open' => SupportTicket::query()->open()->count(),
                'telaat' => SupportTicket::query()->late()->count(),
                'klachten' => SupportTicket::query()->open()->where('type', SupportTicket::TYPE_COMPLAINT)->count(),
                'mijn' => SupportTicket::query()->open()->where('assigned_to', $request->user()->id)->count(),
            ],
            'staff' => User::query()->where('is_staff', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['user', 'assignee', 'lot', 'order.disputes', 'messages.sender']);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'draft' => $ticket->messages->first(fn (TicketMessage $message) => $message->draft),
            'staff' => User::query()->where('is_staff', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, Tickets $tickets): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);

        $tickets->reply($ticket, $request->user(), $validated['body']);

        return redirect()->route('admin.service.show', $ticket)->with('status', 'Antwoord verstuurd naar de klant.');
    }

    public function note(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);

        $ticket->messages()->create(['sender_type' => TicketMessage::FROM_STAFF, 'sender_id' => $request->user()->id, 'body' => trim($validated['body']), 'internal' => true, 'created_at' => now()]);

        return redirect()->route('admin.service.show', $ticket)->with('status', 'Notitie opgeslagen.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', Rule::in([SupportTicket::TYPE_QUESTION, SupportTicket::TYPE_COMPLAINT])],
            'priority' => ['sometimes', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
        ]);

        $ticket->forceFill($validated)->save();

        return redirect()->route('admin.service.show', $ticket)->with('status', 'Ticket bijgewerkt.');
    }

    public function draft(SupportTicket $ticket): RedirectResponse
    {
        StartTask::run(new DraftTicketReply($ticket));

        return redirect()->route('admin.service.show', $ticket)->with('status', 'Ivy zet een concept klaar.');
    }

    public function resolve(Request $request, SupportTicket $ticket, Tickets $tickets): RedirectResponse
    {
        $tickets->resolve($ticket, $request->user());

        return redirect()->route('admin.service.show', $ticket)->with('status', 'Ticket opgelost.');
    }

    public function dispute(Request $request, SupportTicket $ticket, Tickets $tickets): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        $dispute = $tickets->toDispute($ticket, $request->user(), $validated['reason']);

        return redirect()->route('staff.disputes.show', $dispute)->with('status', 'Geschil gestart vanuit ticket '.$ticket->number.'.');
    }
}
