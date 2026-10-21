@php
    $stats = $room['stats'];
    $tiles = [
        ['Live veilingen', $stats['live'], null],
        ['Sluiten binnen een uur', $stats['closing_hour'], null],
        ['Biedingen (5 min)', $stats['bids_5m'], null],
        ['Biedingen (uur)', $stats['bids_1h'], null],
        ['Actieve bieders (kwartier)', $stats['bidders_15m'], null],
        ['Verlengingen (uur)', $stats['extensions_1h'], null],
        ['Gepauzeerd', $stats['paused'], null],
    ];
    $tz = config('aucvy.display_timezone');
@endphp

<div class="flex flex-wrap items-center gap-3">
    <span class="relative flex size-2" aria-hidden="true">
        <span class="absolute inline-flex size-full animate-ping rounded-full bg-aj-success opacity-60"></span>
        <span class="relative inline-flex size-2 rounded-full bg-aj-success"></span>
    </span>
    <p class="text-xs text-aj-muted">Stand van {{ $room['at']->timezone($tz)->isoFormat('HH:mm:ss') }}</p>
</div>

@if ($stats['overdue'] > 0 || $stats['realtime_lag'] > 0)
    <div class="mt-3 space-y-2">
        @if ($stats['overdue'] > 0)
            <p class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $stats['overdue'] }} {{ $stats['overdue'] === 1 ? 'veiling is' : 'veilingen zijn' }} over tijd en nog niet gesloten. Het vangnet (<code>auctions:close-overdue</code>) hoort elke minuut te draaien; controleer de scheduler.</p>
        @endif
        @if ($stats['realtime_lag'] > 0)
            <p class="rounded-aj-sm border border-aj-warn/40 bg-aj-warn/10 p-3 text-sm text-aj-warn-text">{{ $stats['realtime_lag'] }} realtime-{{ $stats['realtime_lag'] === 1 ? 'bericht wacht' : 'berichten wachten' }} langer dan een minuut: bieders zien de stand pas na een refresh. Controleer de worker en Reverb. <a class="underline" href="{{ route('admin.queue') }}">Naar de wachtrij</a>.</p>
        @endif
    </div>
@endif

<div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    @foreach ($tiles as [$label, $value])
        <div class="rounded-aj-s border border-aj-border bg-aj-surface p-4">
            <p class="text-xs font-bold text-aj-muted">{{ $label }}</p>
            <p class="tabular mt-1 text-2xl font-extrabold">{{ $value }}</p>
        </div>
    @endforeach
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
    <section class="overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Lopende veilingen">
        <h2 class="text-sm font-extrabold">Lopende veilingen</h2>
        <p class="mt-1 text-xs text-aj-muted">Op sluittijd. In de antisnipe-periode krijgt een bod er {{ (int) config('aucvy.auctions.anti_snipe_extend_seconds', 60) }} seconden bij.</p>
        @if ($room['auctions']->isEmpty())
            <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong p-6 text-center text-sm text-aj-muted">Geen lopende veilingen.</p>
        @else
            <table class="admin-table mt-3">
                <thead><tr><th>Kavel</th><th>Stand</th><th>Biedingen</th><th>Sluit</th><th>Verlengd</th><th></th></tr></thead>
                <tbody>
                    @foreach ($room['auctions'] as $row)
                        @php
                            $left = $row['seconds_left'];
                            $leftText = $row['paused'] ? 'gepauzeerd' : ($row['overdue'] ? 'over tijd' : ($left < 3600 ? sprintf('%d:%02d', intdiv($left, 60), $left % 60) : $row['ends_at']->timezone($tz)->isoFormat('D MMM, HH:mm')));
                        @endphp
                        <tr class="{{ $row['overdue'] ? 'bg-aj-error/5' : ($row['in_window'] ? 'bg-aj-warn/10' : '') }}">
                            <td><a href="{{ $row['url'] }}" class="font-bold hover:underline">{{ $row['title'] }}</a><span class="block text-xs text-aj-muted">#{{ $row['public_id'] }}{{ $row['reserve_met'] ? '' : ' · reserve niet gehaald' }}</span></td>
                            <td class="tabular font-bold">{{ $row['price'] }}</td>
                            <td class="tabular">{{ $row['bids'] }} <span class="text-xs text-aj-muted">van {{ $row['bidders'] }}</span></td>
                            <td class="tabular {{ $row['in_window'] ? 'font-bold text-aj-warn-text' : '' }}">{{ $leftText }}</td>
                            <td class="tabular">{{ $row['extensions'] > 0 ? $row['extensions'].'×' : '-' }}</td>
                            <td class="whitespace-nowrap text-right"><a href="{{ $row['public_url'] }}" class="text-xs font-bold underline" target="_blank" rel="noopener">Bekijk</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Laatste biedingen">
        <h2 class="text-sm font-extrabold">Laatste biedingen</h2>
        @if ($room['bids']->isEmpty())
            <p class="mt-3 text-sm text-aj-muted">Nog geen biedingen.</p>
        @else
            <ol class="mt-3 divide-y divide-aj-border text-sm">
                @foreach ($room['bids'] as $bid)
                    <li class="py-2">
                        <a href="{{ $bid['url'] }}" class="block hover:underline"><b>{{ $bid['name'] }}</b> bood <b class="tabular">{{ $bid['amount'] }}</b></a>
                        <span class="block truncate text-xs text-aj-muted">{{ $bid['title'] }} · {{ $bid['at']->diffForHumans(short: true) }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</div>
