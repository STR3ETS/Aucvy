@props(['size' => 30])

{{--
    Het A-merkteken uit preview v4. De gradient heeft per instantie een eigen
    id: staat het teken vijf keer op de pagina, dan zou één gedeeld id de
    kleuren van de laatste op alle vijf zetten.
--}}
@php
    $gradient = uniqid('v4-logo-');
@endphp

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" fill="none" aria-hidden="true" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gradient }}" x1="6" y1="44" x2="42" y2="6" gradientUnits="userSpaceOnUse">
            <stop stop-color="#8B3BF0"/>
            <stop offset="0.45" stop-color="#5148E6"/>
            <stop offset="1" stop-color="#3C86F5"/>
        </linearGradient>
    </defs>
    <path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="url(#{{ $gradient }})" stroke-width="10" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
    <circle cx="24" cy="35.6" r="6.9" fill="url(#{{ $gradient }})"/>
</svg>
