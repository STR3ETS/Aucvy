@use('App\Enums\SaleType')

@props(['lot'])

{{--
    De kavelkaart uit preview v4, gevuld met echte data: foto, status als
    pil, categorie, titel op twee regels, prijs, bieders en tijd, en de
    waarderange van Ivy met een stip voor de huidige prijs.

    De tellers lopen via home.js (data-countdown met data-short, data-dutch);
    de server rendert een correcte begintekst voor wie zonder JavaScript
    kijkt.
--}}
@php
    $auction = $lot->auction;
    $price = $lot->currentPriceCents();
    $isDutch = $lot->sale_type === SaleType::Dutch;
    $isBuyNow = $lot->sale_type === SaleType::BuyNow;
    $secondsLeft = $auction ? max(0, $auction->ends_at->getTimestamp() - now()->getTimestamp()) : 0;

    $euro = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.')
        .($cents % 100 !== 0 ? ','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT) : '');

    // Alleen echte statussen: wat een kavel is, niet wat hij zou kunnen zijn.
    [$badgeClass, $badgeLabel] = match (true) {
        $isDutch => ['dutch', 'Afslag'],
        $isBuyNow => ['buy', 'Koop direct'],
        $secondsLeft > 0 && $secondsLeft < 3600 => ['last', 'Nog '.str_pad((string) max(1, intdiv($secondsLeft, 60)), 2, '0', STR_PAD_LEFT).' min'],
        $secondsLeft > 0 && $secondsLeft < 86400 => ['last', 'Laatste kans'],
        default => ['live', 'Live'],
    };

    $hasValue = $lot->hasValueEstimate();

    if ($hasValue) {
        $axisMin = (int) floor($lot->value_min_cents * 0.7);
        $axisMax = max((int) ceil($lot->value_max_cents * 1.3), $axisMin + 100);
        $axisPosition = fn (int $cents): float => round(max(2, min(98, ($cents - $axisMin) / ($axisMax - $axisMin) * 100)), 1);
        $bandLeft = $axisPosition($lot->value_min_cents);
        $bandRight = 100 - $axisPosition($lot->value_max_cents);
    }

    $endsIn = $auction?->ends_at->diffForHumans(['short' => true, 'parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]);
@endphp

<article {{ $attributes->merge(['class' => 'v4-lot']) }}>
    <a href="{{ route('lots.show', $lot) }}">
        <div class="media">
            @if ($lot->media->isNotEmpty())
                <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy" decoding="async">
            @else
                <div class="ph" aria-hidden="true"><x-v4.icon name="image" :size="40" stroke="1.4" style="color:#fff" /></div>
            @endif
            <span class="v4-badge v4-badge--{{ $badgeClass }}">@if ($badgeClass === 'live')<i aria-hidden="true"></i>@endif{{ $badgeLabel }}</span>
        </div>
        <div class="body">
            <div>
                <span class="cat">{{ $lot->category?->name }}@if ($lot->location) · {{ $lot->location->city }}@endif</span>
                <h3>{{ $lot->title }}</h3>
            </div>
            <div class="meta">
                @if ($isDutch && $auction?->hasDutchSettings())
                    <div>
                        <span class="label">Prijs nu</span>
                        <span class="price v4-tnum"
                              data-dutch="{{ intdiv($price, 100) }}"
                              data-step="{{ intdiv($auction->dutch_step_cents, 100) }}"
                              data-floor="{{ intdiv($auction->dutch_floor_cents, 100) }}">{{ $euro($price) }}</span>
                    </div>
                    <div class="stats">
                        <span class="v4-tnum" data-dutch-timer><x-v4.icon name="arrow-down" :size="13" stroke="2" /> daalt over {{ $auction->dutch_interval_s }}s</span>
                    </div>
                @elseif ($isBuyNow)
                    <div>
                        <span class="label">Vaste prijs</span>
                        <span class="price v4-tnum">{{ $euro($auction?->buy_now_price_cents ?? $price) }}</span>
                    </div>
                    <div class="stats"><span><x-v4.icon name="shopping-bag" :size="13" stroke="2" /> Direct kopen</span></div>
                @else
                    <div>
                        <span class="label">Huidig bod</span>
                        <span class="price v4-tnum">{{ $euro($price) }}</span>
                    </div>
                    <div class="stats">
                        <span><x-v4.icon name="users" :size="13" stroke="2" /> {{ $auction?->bidder_count ?? 0 }}</span>
                        <span class="{{ $secondsLeft < 3600 ? 'hot' : '' }}"><x-v4.icon name="clock" :size="13" stroke="2" /> <span class="v4-tnum" data-countdown="{{ $secondsLeft }}" data-short>{{ $endsIn }}</span></span>
                    </div>
                @endif
            </div>
            @if ($hasValue)
                <div>
                    <div class="range-head">
                        <span>Ivy-waarderange (indicatie)</span>
                        <span class="v4-tnum">{{ $euro($lot->value_min_cents) }} – {{ $euro($lot->value_max_cents) }}</span>
                    </div>
                    <div class="range" title="Marktwaarde {{ $euro($lot->value_min_cents) }} – {{ $euro($lot->value_max_cents) }}">
                        <div class="band" style="left:{{ $bandLeft }}%;right:{{ $bandRight }}%"></div>
                        @if ($lot->mayShowValueComparison() && $price > 0)
                            <div class="dot" style="left:{{ $axisPosition($price) }}%" title="Huidige prijs"></div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </a>
    {{-- Volgen bestaat nog niet als functie; de knop onthoudt de keuze
         alleen voor deze pagina, zoals het ontwerp hem laat zien. --}}
    <button type="button" class="v4-follow" x-data="{ on: false }" :class="{ 'is-on': on }" @click="on = ! on"
            :aria-pressed="on" aria-label="Volgen">
        <x-v4.icon name="heart" :size="15" stroke="2.2" ::fill="on ? 'currentColor' : 'none'" />
    </button>
</article>
