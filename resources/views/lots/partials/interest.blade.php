{{--
    Interesseblok voor kopers (hoofdbriefing p. 7): alleen Hot als de regel
    daaraan voldoet, nooit "Rustig". Tellers: Bekeken, Opgeslagen, Gedeeld,
    Bieders (bij een veiling) en Kijken nu.
--}}
@php
    $interest = $interest ?? \App\Lots\Interest\Interest::forBuyer($lot);
    $counters = array_values(array_filter([
        ['Bekeken', $interest['viewers']],
        ['Opgeslagen', $interest['saved']],
        ['Gedeeld', $interest['shared']],
        $lot->sale_type === \App\Enums\SaleType::Auction ? ['Bieders', $interest['bidders']] : null,
        $interest['now'] > 0 ? ['Kijken nu', $interest['now']] : null,
    ]));
@endphp

<section class="rounded-aj-s border border-aj-border bg-aj-surface p-4" aria-labelledby="interesse-kop" x-data="{ open: false }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 id="interesse-kop" class="text-sm font-extrabold">
            @if ($interest['hot'])
                <span class="mr-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-extrabold uppercase tracking-wide" style="background:#fbe9f4;color:#b0247f">Hot</span> Veel interesse in deze kavel
            @else
                Interesse in deze kavel
            @endif
        </h2>
        <button type="button" class="text-xs font-semibold underline" @click="open = ! open" :aria-expanded="open">Hoe meten we dit?</button>
    </div>
    <dl class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-{{ min(5, max(2, count($counters))) }}">
        @foreach ($counters as [$label, $value])
            <div class="rounded-aj-sm bg-aj-bg px-3 py-2"><dt class="text-xs text-aj-muted">{{ $label }}</dt><dd class="tabular text-lg font-extrabold">{{ number_format($value, 0, ',', '.') }}</dd></div>
        @endforeach
    </dl>
    <p x-show="open" x-cloak class="mt-3 text-xs text-aj-muted">
        Alleen gemeten aantallen: unieke bezoekers (één keer per dag), wie de kavel volgt, wie hem deelde en, bij een veiling, unieke bieders. De verkoper, het team en bots tellen niet mee.
        Hot betekent dat de interesse van de afgelopen {{ (int) config('aucvy.interest.window_hours', 48) }} uur bij de hoogste {{ (int) round((float) config('aucvy.interest.top_fraction', 0.1) * 100) }}% van de categorie hoort, met minimaal {{ (int) config('aucvy.interest.min_viewers', 50) }} kijkers{{ $lot->sale_type === \App\Enums\SaleType::Auction ? ' en '.(int) config('aucvy.interest.min_bidders', 3).' bieders' : '' }}.
    </p>
</section>
