{{--
    Interesse voor kopers (hoofdbriefing p. 7) als één rij pictogrammen in de
    verkoopkaart: bekeken, gevolgd, gedeeld en bij een veiling het aantal
    bieders. Alleen Hot als de regel daaraan voldoet, nooit "Rustig". Hoe het
    gemeten wordt staat achter het vraagteken. Wie nu kijkt staat al op de
    foto en boven de prijs.
--}}
@php
    $interest = $interest ?? \App\Lots\Interest\Interest::forBuyer($lot);
    $counters = array_values(array_filter([
        ['eye', $interest['viewers'], 'Bekeken'],
        ['heart', $interest['saved'], 'Opgeslagen'],
        ['share', $interest['shared'], 'Gedeeld'],
        $lot->sale_type === \App\Enums\SaleType::Auction ? ['users', $interest['bidders'], 'Bieders'] : null,
    ]));
    $explain = 'Alleen gemeten aantallen: unieke bezoekers (één keer per dag), wie de kavel volgt, wie hem deelde'
        .($lot->sale_type === \App\Enums\SaleType::Auction ? ' en unieke bieders' : '')
        .'. De verkoper, het team en bots tellen niet mee. Hot betekent dat de interesse van de afgelopen '
        .(int) config('aucvy.interest.window_hours', 48).' uur bij de hoogste '
        .(int) round((float) config('aucvy.interest.top_fraction', 0.1) * 100).'% van de categorie hoort. Het zegt niets over de waarde of de staat.';
@endphp

<section class="d-interest-line{{ $interest['hot'] ? ' is-hot' : '' }}" aria-label="{{ $interest['hot'] ? 'Veel interesse in deze kavel' : 'Interesse in deze kavel' }}">
    @if ($interest['hot'])
        <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
    @endif
    <ul>
        @foreach ($counters as [$icon, $value, $label])
            <li title="{{ $label }}">
                <x-v4.icon :name="$icon" :size="15" stroke="2" />
                <b class="tabular">{{ number_format($value, 0, ',', '.') }}</b>
                <span class="sr-only">{{ $label }}</span>
            </li>
        @endforeach
    </ul>
    <x-dashboard.help :text="$explain" />
</section>
