@props([
    'title' => null,
    // De kop in de kaart; standaard de paginatitel.
    'heading' => null,
    'eyebrow' => 'Account',
    'intro' => null,
])

{{--
    De inlogomgeving van thema C: geen header, geen footer, geen launcher.
    Eén kaart in het midden van het navy vlak met de gloed en het stippenveld
    van de hero, met het merk erboven en de weg terug eronder. Inloggen,
    registreren, wachtwoord en de inlogcode delen deze schil.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ? $title . ' · ' . config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="no-js c auth">

    <a href="#hoofdinhoud" class="skip-link">Naar de hoofdinhoud</a>

    <div class="auth-shell">
        <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>

        <a class="auth-brand" href="{{ route('home') }}" aria-label="Aucvy - home">
            <svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>
            Aucvy
        </a>

        <main id="hoofdinhoud" tabindex="-1" class="auth-card">
            <div class="auth-head">
                @if ($eyebrow)
                    <span class="eyebrow">{{ $eyebrow }}</span>
                @endif
                <h1>{{ $heading ?? $title }}</h1>
                @isset($lead)
                    <div class="lead">{{ $lead }}</div>
                @elseif ($intro)
                    <p class="lead">{{ $intro }}</p>
                @endif
            </div>

            {{ $slot }}
        </main>

        <nav class="auth-foot" aria-label="Terug">
            <a href="{{ route('home') }}">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M13 8H3M7 4 3 8l4 4"/></svg>
                Terug naar Aucvy
            </a>
            <a href="{{ route('faq') }}">Veelgestelde vragen</a>
        </nav>

        @isset($float)
            {{-- Een zwevend paneel rechtsonder; op een smal scherm staat het onder de kaart. --}}
            <aside class="auth-float" aria-label="Demo-accounts">{{ $float }}</aside>
        @endisset
    </div>

    @stack('scripts')
</body>
</html>
