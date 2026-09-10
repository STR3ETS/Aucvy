@props([
    'title' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title . ' · ' . config('app.name') : config('app.name') }}</title>

    {{-- Ingelogde schermen horen niet in een zoekindex. --}}
    <meta name="robots" content="noindex, nofollow">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-dvh bg-aj-bg text-aj-text font-sans">

    <a href="#hoofdinhoud" class="skip-link bg-aj-navy text-white px-4 py-3 rounded-aj-sm font-semibold">
        Naar de hoofdinhoud
    </a>

    <header class="border-b border-aj-border bg-aj-surface">
        <div class="mx-auto flex max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex items-center py-3 text-lg font-extrabold tracking-tight">
                Auc<span class="text-aj-blue">vy</span>
            </a>

            <div class="ml-auto flex items-center gap-2">
                {{ $actions ?? '' }}
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:px-8">
        <nav aria-label="Dashboardnavigatie" class="hidden w-56 shrink-0 lg:block">
            {{ $sidebar ?? '' }}
        </nav>

        <main id="hoofdinhoud" tabindex="-1" class="min-w-0 flex-1">
            {{ $slot }}
        </main>
    </div>

    {{ $ivy ?? '' }}

    @stack('scripts')
</body>
</html>
