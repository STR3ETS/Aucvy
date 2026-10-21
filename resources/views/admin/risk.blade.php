<x-layouts.admin title="Risico" :subtitle="$signals->count().' '.($signals->count() === 1 ? 'signaal' : 'signalen').' uit vaste regels over de laatste '.$days.' dagen; beheer beslist'">
    <div class="mt-6 flex flex-wrap items-center gap-2">
        @foreach ([7, 30, 90] as $option)
            <a href="{{ route('admin.risk', ['dagen' => $option]) }}" class="btn {{ $days === $option ? 'btn-dark' : 'btn-ghost' }}" style="height:38px;font-size:13px">{{ $option }} dagen</a>
        @endforeach
        <p class="ml-auto text-xs text-aj-muted">Regels: bieden op eigen kavel of bedrijfskavel, zelfde IP-adres als de verkoper, twee keer niet betaald, twee keer door moderatie afgewezen.</p>
    </div>

    @if ($signals->isEmpty())
        <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center text-sm text-aj-muted">Geen signalen. De regels draaien bij elk bezoek over de echte data.</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($signals as $signal)
                <li class="flex flex-wrap items-center gap-4 rounded-aj-s border bg-aj-surface p-5 {{ $signal['severity'] === 'hoog' ? 'border-aj-error/40' : 'border-aj-warn/40' }}">
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $signal['severity'] === 'hoog' ? 'bg-aj-error/10 text-aj-error' : 'bg-aj-warn/10 text-aj-warn-text' }}">{{ $signal['severity'] === 'hoog' ? 'Hoog' : 'Midden' }}</span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-extrabold">{{ $signal['title'] }}</h2>
                        <p class="text-sm text-aj-muted">{{ $signal['body'] }}</p>
                    </div>
                    <a href="{{ $signal['url'] }}" class="btn btn-ghost" style="height:40px;font-size:13px">Nakijken</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.admin>
