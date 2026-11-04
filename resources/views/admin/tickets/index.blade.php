<x-layouts.admin title="Tickets en klachten" subtitle="Vragen, klachten en geschillen, met een concept van Ivy. Ivy maakt alleen concepten; een medewerker verstuurt.">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif

    <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="Filter">
        @foreach (['open' => 'Open', 'telaat' => 'Te laat', 'klachten' => 'Klachten', 'mijn' => 'Mijn tickets', 'gesloten' => 'Gesloten'] as $key => $label)
            <a href="{{ route('admin.service', ['filter' => $key]) }}" @class(['rounded-full px-3 py-1 font-bold', 'bg-aj-navy text-white' => $filter === $key, 'border border-aj-border bg-aj-surface' => $filter !== $key])>{{ $label }}@if (isset($counts[$key])) <span class="tabular opacity-70">{{ $counts[$key] }}</span>@endif</a>
        @endforeach
        <a href="{{ route('staff.disputes.index') }}" class="rounded-full border border-aj-border bg-aj-surface px-3 py-1 font-bold">Geschillen</a>
    </nav>

    @if ($tickets->isEmpty())
        <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center text-sm text-aj-muted">Geen tickets in deze lijst.</p>
    @else
        <div class="mt-4 overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface p-5">
            <table class="admin-table">
                <thead><tr><th>Ticket</th><th>Type</th><th>Klant</th><th>Gekoppeld</th><th>Status</th><th>Wacht</th><th>Toegewezen</th><th></th></tr></thead>
                <tbody>
                    @foreach ($tickets as $ticket)
                        <tr>
                            <td><a href="{{ route('admin.service.show', $ticket) }}" class="font-semibold underline">{{ $ticket->subject }}</a><span class="block text-xs text-aj-muted">{{ $ticket->number }} · {{ __('admin.tickets.sources.'.$ticket->source) }}</span></td>
                            <td><span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-aj-error/10 text-aj-error' => $ticket->isComplaint(), 'bg-aj-bg' => ! $ticket->isComplaint()])>{{ $ticket->typeLabel() }}</span>@if ($ticket->priority === 'high') <span class="rounded-full bg-aj-warn/15 px-2 py-0.5 text-xs font-bold text-aj-warn-text">hoog</span>@endif</td>
                            <td class="text-xs">{{ $ticket->name }}</td>
                            <td class="text-xs">{{ $ticket->lot?->title ?? $ticket->order?->order_number ?? '-' }}</td>
                            <td>{{ $ticket->isOpen() ? ($ticket->isLate() ? 'Te laat' : ($ticket->first_response_at ? 'In behandeling' : 'Open')) : 'Gesloten' }}</td>
                            <td class="whitespace-nowrap text-xs text-aj-muted">{{ $ticket->created_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</td>
                            <td class="text-xs">{{ $ticket->assignee?->name ?? '-' }}</td>
                            <td><a href="{{ route('admin.service.show', $ticket) }}" class="btn btn-dark" style="height:30px;font-size:12px">Open ticket</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
</x-layouts.admin>
