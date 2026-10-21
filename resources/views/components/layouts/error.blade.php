@props([
    'code',
    'title',
    'message',
])

{{--
    De foutpagina's in thema C: het navy vlak met gloed en stippen, één
    kaart met de code, een titel in gewone taal en de weg terug. Geen
    header of footer: zo min mogelijk dat zelf kan falen.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="no-js c auth">
    <div class="auth-shell">
        <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>

        <a class="auth-brand" href="{{ url('/') }}" aria-label="Aucvy - home">
            <svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>
            Aucvy
        </a>

        <main class="auth-card error-card">
            <span class="error-code">{{ $code }}</span>
            <h1>{{ $title }}</h1>
            <p>{{ $message }}</p>
            <div class="error-actions">
                {{ $slot }}
            </div>
        </main>

        <nav class="auth-foot" aria-label="Verder">
            <a href="{{ url('/') }}">Naar de home</a>
            <a href="{{ url('/veilingen') }}">Veilingen</a>
            <a href="{{ url('/faq') }}">Veelgestelde vragen</a>
        </nav>
    </div>
</body>
</html>
