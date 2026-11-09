@props([
    'title' => null,
    'description' => null,
    // Op de home ligt de header transparant óver de blauwe hero (het ontwerp).
    // Op binnenpagina's krijgt hij de vaste witte variant.
    'overHero' => false,
    // De stijlvariant op de body. 'c' is de hybride stijlgids (hybrid.css) en
    // geldt voor elke pagina; alleen de home van ontwerp A in de A/B-test
    // zet hem op null en krijgt het kale ontwerp van aucvy-home.html.
    'theme' => 'c',
    // De paginakop van thema C: een navy band met kicker, titel en intro onder
    // de transparante header. Elke binnenpagina krijgt hem; een pagina met een
    // eigen kop (de kavelpagina, de lokale nepcheckout) zet hero op false.
    'hero' => true,
    // De kop boven de band ("Dashboard", "Verkopen"); null is geen kicker.
    'eyebrow' => null,
    // De titel in de band; standaard de paginatitel uit <title>.
    'heading' => null,
    // Eén zin onder de titel. Met opmaak of links: de slot "lead".
    'intro' => null,
    // 'left' of 'center': pagina's met een smalle, gecentreerde kolom
    // (inloggen, veelgestelde vragen) zetten de band ook in het midden.
    'align' => 'left',
    // Zoekmachines en deelknoppen: de canonieke URL (standaard de huidige
    // zonder querystring), het deelbeeld en het Open Graph-type.
    'canonical' => null,
    // Ivy weet op welke pagina je zit; de kavelpagina geeft de kavel mee.
    'ivyLot' => null,
    'ivyPage' => null,
    'image' => null,
    'imageWidth' => null,
    'imageHeight' => null,
    'ogType' => 'website',
    // Gefilterde en gepagineerde zoekpagina's: wel volgen, niet indexeren
    // (masterbriefing 7.3), de canonical wijst al naar de schone URL.
    'noindex' => false,
])

@php
    $showHero = $theme === 'c' && $hero && ! $overHero;

    // Het hoofdmenu, met de routes waarop elk item de actieve staat krijgt.
    $navItems = [
        [route('lots.index'), 'Veilingen', ['lots.*']],
        // Een categoriepagina hoort bij Categorieën, niet bij Veilingen.
        [route('categories.index'), 'Categorieën', ['categories.*', 'lots.category']],
        [route('how-it-works'), 'Hoe het werkt', ['how-it-works', 'selling.guide', 'faq']],
        [route('business'), 'Zakelijk', ['business']],
        [route('pricing'), 'Prijzen', ['pricing']],
        [route('assurance'), 'Zekerheid', ['assurance']],
    ];
@endphp

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

    @php
        $canonicalUrl = $canonical ?? url()->current();
        $shareImage = $image ?? asset('images/v4/footer-gavel.jpg');
        $shareTitle = $title ? $title.' · '.config('app.name') : 'Aucvy - Upload. Ivy regelt de rest.';
    @endphp
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @if ($noindex)
        <meta name="robots" content="noindex,follow">
    @endif
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:title" content="{{ $shareTitle }}">
    @if ($description)
        <meta property="og:description" content="{{ $description }}">
    @endif
    <meta property="og:image" content="{{ $shareImage }}">
    @if ($imageWidth && $imageHeight)
        <meta property="og:image:width" content="{{ $imageWidth }}">
        <meta property="og:image:height" content="{{ $imageHeight }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $shareTitle }}">
    <meta name="twitter:image" content="{{ $shareImage }}">

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
<body class="no-js{{ $theme ? ' '.$theme : '' }}">

    <a href="#hoofdinhoud" class="skip-link">Naar de hoofdinhoud</a>

    {{-- Header uit aucvy-home.html. --}}
    <header class="header {{ ($overHero || $showHero) ? '' : 'solid' }}" x-data="{ menu: false }">
        <div class="wrap">
            <a class="brand" href="{{ route('home') }}" aria-label="Aucvy - home">@if ($theme === 'c')<svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>@else<i></i>@endif Aucvy</a>
            <nav class="nav" aria-label="Hoofdnavigatie">
                @foreach ($navItems as [$url, $label, $patterns])
                    @php $isOn = request()->routeIs(...$patterns) && ! ($label === 'Veilingen' && request()->routeIs('lots.category')); @endphp
                    <a href="{{ $url }}"{!! $isOn ? ' class="is-on" aria-current="page"' : '' !!}>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="header-actions">
                @auth
                    {{-- Ingelogd: de profielknop met de snelle acties. --}}
                    <x-marketing.user-menu />
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
            @foreach ($navItems as [$url, $label, $patterns])
                @php $isOn = request()->routeIs(...$patterns) && ! ($label === 'Veilingen' && request()->routeIs('lots.category')); @endphp
                <a href="{{ $url }}"{!! $isOn ? ' class="is-on" aria-current="page"' : '' !!}>{{ $label }}</a>
            @endforeach
            @auth
                <a href="{{ auth()->user()->is_staff ? url('/admin') : route('dashboard') }}">Dashboard</a>
                <a href="{{ route('dashboard.lots') }}">Mijn kavels</a>
                <a href="{{ route('dashboard.bids') }}">Mijn biedingen</a>
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
        @elseif ($showHero)
            {{-- De paginakop van thema C. De kruimels, de intro met opmaak en
                 de knoppen rechts komen als slots uit de pagina. --}}
            <section class="page-hero{{ $align === 'center' ? ' is-center' : '' }}" aria-labelledby="page-title">
                {{-- Hetzelfde ademende stippenveld als in de hero en de zakelijk-sectie. --}}
                <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>
                <div class="wrap">
                    @isset($crumbs)
                        <nav class="page-crumbs" aria-label="Kruimelpad">{{ $crumbs }}</nav>
                    @endisset
                    <div class="page-hero-row">
                        <div class="page-hero-text">
                            @if ($eyebrow)
                                <span class="eyebrow">{{ $eyebrow }}</span>
                            @endif
                            @isset($headline)
                                <h1 id="page-title">{{ $headline }}</h1>
                            @else
                                <h1 id="page-title">{{ $heading ?? $title }}</h1>
                            @endisset
                            @isset($lead)
                                <div class="lead">{{ $lead }}</div>
                            @elseif ($intro)
                                <p class="lead">{{ $intro }}</p>
                            @endif
                        </div>
                        @isset($actions)
                            <div class="page-actions">{{ $actions }}</div>
                        @endisset
                    </div>
                </div>
            </section>
            <div class="wrap page-body">
                {{ $slot }}
            </div>
        @else
            {{-- Binnenpagina's krijgen de inhoudskolom van het ontwerp. --}}
            <div class="wrap" style="padding-top:40px;padding-bottom:96px">
                {{ $slot }}
            </div>
        @endif
    </main>

    {{-- Footer uit aucvy-home.html. Links zonder bestaande pagina wijzen naar
         de dichtstbijzijnde bestaande route. --}}
    @if ($theme === 'c')
        {{-- Variant C heeft een uitgebreide footer in de hybride stijlgids. --}}
        <x-marketing.footer-c />
    @else
    <footer>
        <div class="wrap">
            <div class="foot">
                <div>
                    <div class="brand">@if ($theme === 'c')<svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>@else<i></i>@endif Aucvy</div>
                    <p style="margin:0;max-width:34ch">Het AI-commerceplatform voor zakelijke goederen. Nederland.</p>
                </div>
                <div><h4>Kopen</h4><ul><li><a href="{{ route('lots.index') }}">Veilingen</a></li><li><a href="{{ route('categories.index') }}">Categorieën</a></li><li><a href="{{ route('lots.buy-now') }}">Direct kopen</a></li><li><a href="{{ route('home') }}#reis">Hoe bieden werkt</a></li><li><a href="{{ route('assurance') }}">Zekerheid</a></li></ul></div>
                <div><h4>Verkopen</h4><ul><li><a href="{{ route('sell') }}">Kavel plaatsen</a></li><li><a href="{{ route('selling.guide') }}">Verkooproute</a></li><li><a href="{{ route('pricing') }}">Tarieven</a></li><li><a href="{{ route('home') }}#zakelijk">Zakelijk</a></li></ul></div>
                <div><h4>Aucvy</h4><ul><li><a href="{{ route('home') }}">Over ons</a></li><li><a href="{{ route('faq') }}">Veelgestelde vragen</a></li><li><a href="{{ route('contact') }}">Contact</a></li><li><a href="{{ route('legal.privacy') }}">Privacy</a></li></ul></div>
            </div>
            <div class="foot-bottom">
                <span>© {{ now()->year }} Aucvy B.V.</span>
                <span><a href="{{ route('legal.terms') }}">Voorwaarden</a> · <a href="{{ route('legal.privacy') }}">Privacy</a> · <a href="{{ route('legal.cookies') }}">Cookies</a></span>
            </div>
        </div>
    </footer>
    @endif

    <x-privacy.cookie-banner />

    {{-- Ivy op elke pagina (Ivy-briefing hfst. 9); de kavelpagina geeft de kavel mee. --}}
    <x-ivy.launcher :lot="$ivyLot ?? null" :page="$ivyPage ?? (request()->route()?->getName() ?? 'onbekend')" />
    @stack('scripts')
</body>
</html>
