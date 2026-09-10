@props([
    'variant' => 'primary',
    'as' => 'button',
    'type' => 'button',
    'href' => null,
    'size' => 'base',
])

@php
    $base = 'inline-flex items-center justify-center gap-2.5 rounded-aj-sm border border-transparent'
        .' font-bold tracking-[-0.005em] whitespace-nowrap transition-colors'
        .' active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60';

    $sizes = [
        'base' => 'px-5 text-[15px]',
        'sm' => 'px-4 text-sm',
    ];

    // De varianten uit aucvy-home.html. `white` en `outline-white` horen op
    // een blauw of donker vlak; daarbuiten vallen ze weg.
    $variants = [
        'primary' => 'bg-aj-blue text-white hover:bg-aj-blue-deep',
        'dark' => 'bg-aj-text text-white hover:bg-[#1c2540]',
        'ghost' => 'border-aj-border-strong text-aj-text hover:border-aj-text',
        'white' => 'bg-white text-aj-blue-ink hover:bg-[#eaf0ff]',
        'outline-white' => 'border-white/35 text-white hover:border-white',
        'text' => 'text-aj-blue-text hover:underline px-0',
    ];

    $tag = $href !== null ? 'a' : $as;
    $height = $size === 'sm' ? '40px' : 'var(--spacing-cta)';
@endphp

<{{ $tag }}
    @if ($href !== null) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{-- 44px is de minimale tikoppervlakte en geldt overal (hfst. 10). --}}
    style="height: {{ $height }}"
    {{ $attributes->merge([
        'class' => $base.' '.($sizes[$size] ?? $sizes['base']).' '.($variants[$variant] ?? $variants['primary']),
    ]) }}
>
    {{ $slot }}
</{{ $tag }}>
