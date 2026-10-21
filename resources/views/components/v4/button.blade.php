@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'base',
    'arrow' => false,
    'type' => 'button',
])

{{--
    De knop uit preview v4: een pil, met als primaire variant de blauw-paarse
    gradient en een witte pijlcirkel. `ghost` hoort op een donker vlak.
--}}
@php
    $classes = 'v4-btn v4-btn--'.$variant
        .($size === 'lg' ? ' v4-btn--lg' : '')
        .($arrow ? ' v4-btn--arrow' : '');

    $tag = $href !== null ? 'a' : 'button';
@endphp

<{{ $tag }}
    @if ($href !== null) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{ $attributes->merge(['class' => $classes]) }}
>{{ $slot }}@if ($arrow)<span class="v4-btn-arrow" aria-hidden="true"><x-v4.icon name="arrow-right" :size="$size === 'lg' ? 17 : 15" stroke="2.6" /></span>@endif</{{ $tag }}>
