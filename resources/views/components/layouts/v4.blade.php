@props([
    'title' => null,
    'description' => null,
    // De variant en de demo-vlag van de A/B-test (HomeController).
    'variant' => null,
    'demo' => false,
])

{{--
    De schil van variant B van de A/B-test: het ontwerp uit preview v4. Eigen
    navigatie, footer, Ivy-dock en mobiele knop; de rest van de site houdt de
    publieke layout. Zelfde toegankelijkheidsvloer als die layout: skip-link,
    één main met id, lang="nl".
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (config('webpush.vapid.public_key'))
        <meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
    @endif

    <title>{{ $title ? $title . ' · ' . config('app.name') : 'Aucvy - Upload. Ivy regelt de rest.' }}</title>

    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    {{-- Preview v4 zet in Inter, niet in Manrope. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
{{-- `no-js` wordt door het script verwijderd; zonder JavaScript blijven de
     .v4-reveal-blokken daardoor gewoon zichtbaar. --}}
<body class="no-js v4">

    <a href="#hoofdinhoud" class="v4-skip">Naar de hoofdinhoud</a>

    <x-v4.nav />

    <main id="hoofdinhoud" tabindex="-1">
        {{ $slot }}
    </main>

    <x-v4.footer />
    <x-v4.mobile-sticky />
    <x-v4.ivy-dock />

    @if ($variant !== null)
        <x-marketing.ab-switch :variant="$variant" :demo="$demo" />
    @endif

    @stack('scripts')
</body>
</html>
