@use('App\Support\Money\Money')

<x-layouts.admin title="Gesprekken" subtitle="Wat de bot vasthield of vlagde, met het zwaarste bovenaan.">

    <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="Filter">
        @foreach (['alles' => 'Alles', 'vastgehouden' => 'Vastgehouden', 'vlag' => 'Vlag', 'melding' => 'Melding'] as $key => $label)
            <a href="{{ route('admin.conversations', ['filter' => $key]) }}" @class(['d-chip', 'is-on' => $filter === $key])>
                {{ $label }}@if (isset($counts[$key])) <span class="tabular opacity-70">{{ $counts[$key] }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if ($conversations->isEmpty())
        <p class="mt-4 d-card d-admin-empty">Niets wacht op een medewerker.</p>
    @else
        <div class="mt-4 overflow-x-auto d-card">
            <table class="admin-table">
                <thead><tr><th>Signaal</th><th>Kavel</th><th>Partijen</th><th>Bedrag</th><th>Bot</th><th>Wacht</th><th></th></tr></thead>
                <tbody>
                    @foreach ($conversations as $conversation)
                        @php
                            $signal = $conversation->reported_at ? 'reported' : ($conversation->flag ?? 'none');
                            $kind = $conversation->held_count > 0 ? 'Vastgehouden' : ($conversation->reported_at ? 'Melding' : 'Vlag');
                        @endphp
                        <tr>
                            <td>
                                <span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-aj-error/10 text-aj-error' => $kind === 'Vastgehouden', 'bg-aj-warn/15 text-aj-warn-text' => $kind === 'Vlag', 'bg-aj-blue/10 text-aj-blue-text' => $kind === 'Melding'])>{{ $kind }}</span>
                                <span class="block text-xs">{{ __('messaging.signals.'.$signal) }}</span>
                            </td>
                            <td><a href="{{ route('admin.lots.show', $conversation->lot) }}" class="d-tablelink">{{ $conversation->lot->title }}</a></td>
                            <td class="text-xs">{{ $conversation->sellerLabel() }} · {{ $conversation->buyerLabel() }}</td>
                            <td class="tabular">{{ $conversation->order ? Money::format($conversation->order->total_cents) : ($conversation->lot->auction ? Money::format($conversation->lot->currentPriceCents()) : '-') }}</td>
                            <td class="text-xs">{{ $conversation->held_count > 0 ? 'Tegengehouden' : ($conversation->flag ? 'Laten staan' : 'Geen actie') }}</td>
                            <td class="whitespace-nowrap text-xs text-aj-muted">{{ ($conversation->flagged_at ?? $conversation->reported_at ?? $conversation->updated_at)->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</td>
                            <td><a href="{{ route('admin.conversations.show', $conversation) }}" class="d-btn d-btn-dark d-btn-sq" style="height:34px;font-size:12px">Openen</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $conversations->links() }}</div>
    @endif

    <p class="mt-4 text-xs text-aj-muted">Een gesprek zonder vlag openen kan alleen met een reden. Elke inzage komt in het auditlog.</p>
</x-layouts.admin>
