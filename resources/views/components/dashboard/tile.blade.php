@props([
    // green, blue, orange of purple: de kleurgroep van de tegel.
    'tone' => 'blue',
    'icon' => 'target',
    'label',
    'value',
    'sub' => null,
    'href' => null,
])

{{--
    Een cijfertegel bovenaan een dashboardpagina: icoon, label, het getal
    en één regel context. Met een href is de hele tegel een link.
--}}
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat-tile tone-'.$tone]) }}>
    <span class="stat-head">
        <span class="stat-icon"><x-v4.icon :name="$icon" :size="17" stroke="2" /></span>
        {{ $label }}
    </span>
    <b class="stat-value tabular">{{ $value }}</b>
    @if ($sub)
        <span class="stat-sub">{{ $sub }}</span>
    @endif
</{{ $tag }}>
