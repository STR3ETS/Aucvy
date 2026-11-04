<x-layouts.admin title="Live" subtitle="Alle lopende veilingen op één scherm, met wat er de laatste minuten gebeurde. Ververst vanzelf.">
    @php $liveSessions = \App\Models\LiveSession::query()->with(['lot', 'seller'])->latest('id')->limit(20)->get(); @endphp
    <section class="mt-6 rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="uitzendingen">
        <h2 id="uitzendingen" class="text-sm font-extrabold">Uitzendingen {{ \App\Live\LiveSessions::enabled() ? '' : '(vlag staat uit)' }}</h2>
        <p class="text-xs text-aj-muted">Maandbudget {{ config('aucvy.live.monthly_budget_minutes') }} kijkminuten; gebruikt {{ app(\App\Live\LiveSessions::class)->minutesThisMonth() }}. Mediaserver: {{ config('aucvy.live.provider') }}.</p>
        @if ($liveSessions->isEmpty())
            <p class="mt-2 text-sm text-aj-muted">Nog geen uitzendingen.</p>
        @else
            <table class="admin-table mt-2"><thead><tr><th>Kavel</th><th>Verkoper</th><th>Status</th><th>Kijkers (piek)</th><th>Gestart</th></tr></thead><tbody>
                @foreach ($liveSessions as $session)
                    <tr><td>{{ $session->lot?->title }}</td><td class="text-xs">{{ $session->seller?->name }}</td><td>{{ $session->statusLabel() }}</td><td class="tabular">{{ $session->viewers_now }} ({{ $session->peak_viewers }})</td><td class="text-xs">{{ $session->started_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</td></tr>
                @endforeach
            </tbody></table>
        @endif
    </section>

    <div class="mt-6" x-data="adminLive(@js(route('admin.live.panel')))">
        @include('admin.partials.live')
    </div>
</x-layouts.admin>
