@props([
    // green, blue, orange, purple of pink: de kleurgroep van de tegel.
    'tone' => 'blue',
    // Bewaard voor bestaande aanroepen; het ontwerp van november toont geen icoon.
    'icon' => null,
    'label',
    'value',
    'sub' => null,
    'href' => null,
    // Een trend als "15%" omhoog of "4%" omlaag, met een pijlpictogram: een pil rechts van het label.
    'trend' => null,
    // De tegel is een filter en staat nu aan.
    'active' => false,
])

{{--
    Een cijfertegel bovenaan een dashboardpagina, uit het ontwerp van
    november: zacht gekleurd vlak, label met eventueel een trendpil, het
    getal en één regel context. Met een href is de hele tegel een link.
--}}
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif @if ($active) aria-current="true" @endif {{ $attributes->merge(['class' => 'd-tile tone-'.$tone.($active ? ' is-on' : '')]) }}>
    <div class="d-tile-head">
        <span class="d-tile-label">{{ $label }}</span>
        @if ($trend !== null)
            <span class="d-trend{{ $trend < 0 ? ' is-down' : '' }}"><x-v4.icon :name="$trend < 0 ? 'arrow-down-right' : 'arrow-up-right'" :size="12" stroke="2.8" /> {{ abs($trend) }}%</span>
        @endif
    </div>
    <div class="d-tile-value tabular">{{ $value }}</div>
    @if ($sub)
        <div class="d-tile-sub">{{ $sub }}</div>
    @endif
</{{ $tag }}>
