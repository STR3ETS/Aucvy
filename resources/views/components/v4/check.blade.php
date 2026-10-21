@props([
    'size' => 17,
    'outline' => false,
])

{{-- Het vinkje in een gradientcirkel; `outline` is de versie op de categorietegels. --}}
<span
    {{ $attributes->merge(['class' => 'v4-check'.($outline ? ' v4-check--outline' : '')]) }}
    style="width:{{ $size }}px;height:{{ $size }}px"
    aria-hidden="true"
><x-v4.icon name="check" :size="max(8, (int) round($size * 0.56))" stroke="3.6" /></span>
