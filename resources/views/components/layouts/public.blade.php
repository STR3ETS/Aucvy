@props([
    'title' => null,
    'description' => null,
    // Op de home ligt de header transparant óver de blauwe hero (het ontwerp).
    // Op binnenpagina's krijgt hij de vaste witte variant.
    'overHero' => false,
])

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

    {{-- Exact de fontlaad-regels uit aucvy-home.html. Google serveert Manrope
         als variabel font en dat rendert zichtbaar lichter dan de statische
         instanties die een self-host-pakket levert - vandaar niet via bunny. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
{{-- `no-js` wordt door het script verwijderd; zonder JavaScript blijven de
     .rv-blokken daardoor gewoon zichtbaar (zoals in het ontwerp). --}}
<body class="no-js">

    <a href="#hoofdinhoud" class="skip-link">Naar de hoofdinhoud</a>

    {{-- Header uit aucvy-home.html. --}}
    <header class="header {{ $overHero ? '' : 'solid' }}" x-data="{ menu: false }">
        <div class="wrap">
            <a class="brand" href="{{ route('home') }}" aria-label="Aucvy - home"><i></i>Aucvy</a>
            <nav class="nav" aria-label="Hoofdnavigatie">
                <a href="{{ route('lots.index') }}">Veilingen</a>
                <a href="{{ route('lots.index') }}">Categorieën</a>
                <a href="{{ route('home') }}#reis">Hoe het werkt</a>
                <a href="{{ route('home') }}#zakelijk">Zakelijk</a>
            </nav>
            <div class="header-actions">
                <a class="link" href="{{ route('lots.index') }}">Zoeken</a>
                @auth
                    {{-- TOEVOEGING t.o.v. het ontwerp: ingelogd moet je bij je
                         dashboard en bestellingen kunnen (stap 10/11). --}}
                    <a class="link" href="{{ auth()->user()->is_staff ? url('/admin') : route('dashboard') }}">Dashboard</a>
                    <a class="link" href="{{ route('orders.index') }}">Bestellingen</a>
                    <form method="POST" action="{{ route('logout') }}" style="display:contents">
                        @csrf
                        <button type="submit" class="link">Uitloggen</button>
                    </form>
                @endauth
                @guest
                    <a class="link" href="{{ route('login') }}">Inloggen</a>
                @endguest
                <a class="btn btn-white" href="{{ route('sell') }}">Kavel plaatsen</a>
                {{-- TOEVOEGING: de knop bestond in het ontwerp, het menu erachter
                     nog niet. Hamburgermenu ook op contentpagina's (hfst. 12). --}}
                <button class="burger" aria-label="Menu openen"
                        :aria-expanded="menu" @click="menu = ! menu">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 5h14M2 9h14M2 13h14"/></svg>
                </button>
            </div>
        </div>

        {{-- TOEVOEGING: het mobiele menu achter de burger. --}}
        <nav class="mobile-menu" x-show="menu" x-cloak aria-label="Menu" @click.outside="menu = false">
            <a href="{{ route('lots.index') }}">Veilingen</a>
            <a href="{{ route('home') }}#reis">Hoe het werkt</a>
            <a href="{{ route('assurance') }}">Zekerheid</a>
            @auth
                <a href="{{ auth()->user()->is_staff ? url('/admin') : route('dashboard') }}">Dashboard</a>
                <a href="{{ route('orders.index') }}">Bestellingen</a>
                <form method="POST" action="{{ route('logout') }}" style="display:contents">
                    @csrf
                    <button type="submit">Uitloggen</button>
                </form>
            @endauth
            @guest
                <a href="{{ route('login') }}">Inloggen</a>
                <a href="{{ route('register') }}">Registreren</a>
            @endguest
        </nav>
    </header>

    <main id="hoofdinhoud" tabindex="-1">
        @if ($overHero)
            {{ $slot }}
        @else
            {{-- Binnenpagina's krijgen de inhoudskolom van het ontwerp. --}}
            <div class="wrap" style="padding-top:40px;padding-bottom:96px">
                {{ $slot }}
            </div>
        @endif
    </main>

    {{-- Footer uit aucvy-home.html. Links zonder bestaande pagina wijzen naar
         de dichtstbijzijnde bestaande route. --}}
    <footer>
        <div class="wrap">
            <div class="foot">
                <div>
                    <div class="brand"><i></i>Aucvy</div>
                    <p style="margin:0;max-width:34ch">Het AI-commerceplatform voor zakelijke goederen. Nederland.</p>
                </div>
                <div><h4>Kopen</h4><ul><li><a href="{{ route('lots.index') }}">Veilingen</a></li><li><a href="{{ route('lots.index') }}">Categorieën</a></li><li><a href="{{ route('home') }}#reis">Hoe bieden werkt</a></li><li><a href="{{ route('assurance') }}">Zekerheid</a></li></ul></div>
                <div><h4>Verkopen</h4><ul><li><a href="{{ route('sell') }}">Kavel plaatsen</a></li><li><a href="{{ route('selling.guide') }}">Verkooproute</a></li><li><a href="{{ route('faq') }}">Tarieven</a></li><li><a href="{{ route('home') }}#zakelijk">Zakelijk</a></li></ul></div>
                <div><h4>Aucvy</h4><ul><li><a href="{{ route('home') }}">Over ons</a></li><li><a href="{{ route('faq') }}">Veelgestelde vragen</a></li><li><a href="{{ route('home') }}">Contact</a></li><li><a href="{{ route('home') }}">Privacy</a></li></ul></div>
            </div>
            <div class="foot-bottom">
                <span>© {{ now()->year }} Aucvy B.V.</span>
                <span>Voorwaarden · Privacy · Cookies</span>
            </div>
        </div>
    </footer>

    {{-- Ivy-launcher uit het ontwerp. Het paneel erachter komt in stap 9;
         tot die tijd opent hij het zoekveld op de veilingenpagina. --}}
    <a class="ivy-launch" href="{{ route('lots.index') }}" aria-label="Open Ivy"><img
        src="{{ asset('images/agents/ivy.jpg') }}" alt="">Vraag Ivy</a>

    @stack('scripts')
</body>
</html>
