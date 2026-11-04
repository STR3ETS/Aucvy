{{--
    LotInterestCard voor de verkoper (hoofdbriefing p. 7, Ivy-briefing V5):
    kijkers, weergaven, documenten, opgeslagen, gedeeld, bieders en herkomst.
    Dezelfde cijfers als de dagmail.
--}}
@php
    $stats = $stats ?? \App\Lots\Interest\Interest::forSeller($lot);
    $totalSources = max(1, array_sum($stats['sources']));
    $sourceLabels = ['alert' => 'Zoekalerts', 'google' => 'Google', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'shared' => 'Gedeelde links', 'direct' => 'Direct', 'other' => 'Overig'];
@endphp

<section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="interesse">
    <div class="flex items-center justify-between gap-2">
        <h2 id="interesse" class="text-sm font-extrabold">Interesse</h2>
        @if ($stats['hot'])
            <span class="rounded-full px-2 py-0.5 text-xs font-extrabold uppercase tracking-wide" style="background:#fbe9f4;color:#b0247f">Hot</span>
        @endif
    </div>
    <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
        @foreach ([['Kijkers', $stats['viewers']], ['Weergaven', $stats['views']], ['Opgeslagen', $stats['saved']], ['Gedeeld', $stats['shared']], ['Documenten', $stats['documents']], ['Bieders', $stats['bidders']]] as [$label, $value])
            <div class="rounded-aj-sm bg-aj-bg px-2 py-2"><dd class="tabular text-lg font-extrabold">{{ number_format($value, 0, ',', '.') }}</dd><dt class="text-[11px] text-aj-muted">{{ $label }}</dt></div>
        @endforeach
    </dl>
    @if ($stats['sources'] !== [])
        <p class="mt-3 text-xs font-bold">Herkomst van kijkers</p>
        <ul class="mt-1 space-y-1 text-xs">
            @foreach (collect($stats['sources'])->sortDesc() as $source => $count)
                <li class="flex items-center gap-2"><span class="w-24 shrink-0 text-aj-muted">{{ $sourceLabels[$source] ?? ucfirst($source) }}</span><span class="h-1.5 flex-1 rounded bg-aj-bg"><span class="block h-1.5 rounded bg-aj-blue" style="width: {{ (int) round($count / $totalSources * 100) }}%"></span></span><span class="tabular w-10 text-right">{{ (int) round($count / $totalSources * 100) }}%</span></li>
            @endforeach
        </ul>
    @endif
    <p class="mt-3 text-[11px] text-aj-muted">Score laatste {{ $stats['window_hours'] }} uur: {{ $stats['score'] }}. Alleen gemeten aantallen; jijzelf, het team en bots tellen niet mee.</p>
</section>
