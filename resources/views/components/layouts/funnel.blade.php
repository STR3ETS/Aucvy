@props([
    'title' => null,
    'description' => null,
])

{{--
    De schil van "Kavel plaatsen" uit het ontwerp van november: geen sitemenu
    en geen voettekst, alleen de kaart met de stappen. De pagina zelf levert
    de navy kop en de inhoud; hier staan alleen de head, de cookiebanner en
    Ivy.
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

    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:title" content="{{ $title ? $title.' · '.config('app.name') : config('app.name') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="no-js c funnel">

    <a href="#hoofdinhoud" class="skip-link">Naar de hoofdinhoud</a>

    <main id="hoofdinhoud" tabindex="-1">
        {{ $slot }}
    </main>

    <x-privacy.cookie-banner />

    <x-ivy.launcher :lot="null" page="verkopen" />
    @stack('scripts')
</body>
</html>
