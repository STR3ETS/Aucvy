{{--
    Interesseblok voor kopers (hoofdbriefing p. 7): alleen Hot als de regel
    daaraan voldoet, nooit "Rustig". Tellers: Bekeken, Opgeslagen, Gedeeld,
    Bieders (bij een veiling) en Kijken nu. In de biedkaart, zoals in het
    ontwerp van november: roze als de kavel hot is.
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

<section class="d-interest{{ $interest['hot'] ? ' is-hot' : '' }}" aria-labelledby="interesse-kop">
    <div class="d-task-tags" style="align-items:center">
        @if ($interest['hot'])
            <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
            <strong id="interesse-kop" style="font-size:13px">Veel interesse in deze kavel</strong>
        @else
            <strong id="interesse-kop" style="font-size:13px">Interesse in deze kavel</strong>
        @endif
    </div>
    <div class="d-interest-grid">
        @foreach ($counters as [$label, $value])
            <div><b>{{ number_format($value, 0, ',', '.') }}</b><span>{{ $label }}</span></div>
        @endforeach
    </div>
    <details class="d-details" style="margin-top:4px">
        <summary>Hoe meten we dit?</summary>
        <p class="d-note">
            Alleen gemeten aantallen: unieke bezoekers (één keer per dag), wie de kavel volgt, wie hem deelde en, bij een veiling, unieke bieders. De verkoper, het team en bots tellen niet mee.
            Hot betekent dat de interesse van de afgelopen {{ (int) config('aucvy.interest.window_hours', 48) }} uur bij de hoogste {{ (int) round((float) config('aucvy.interest.top_fraction', 0.1) * 100) }}% van de categorie hoort, met minimaal {{ (int) config('aucvy.interest.min_viewers', 50) }} kijkers{{ $lot->sale_type === \App\Enums\SaleType::Auction ? ' en '.(int) config('aucvy.interest.min_bidders', 3).' bieders' : '' }}. Het zegt niets over de waarde of de staat.
        </p>
    </details>
</section>
