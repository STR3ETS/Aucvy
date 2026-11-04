@props([
    'title' => null,
    // De kop in de band; standaard de paginatitel.
    'heading' => null,
    'eyebrow' => null,
    // Eén zin onder de kop. Met opmaak: de slot "lead".
    'intro' => null,
    // De regel onder de paginatitel in de bovenbalk.
    'subtitle' => 'Vandaag op Aucvy',
])

{{--
    De dashboardlayout van thema C.

    Links de witte zijbalk: het menu in groepen, de knop om te plaatsen. Rechts het navy vlak met de gloed en het stippenveld van de
    hero: daarop een doorzichtige bovenbalk met paginatitel, zoeken, hulp en
    de profielknop met dropdown, en daaronder de kopband met titel, intro en
    knoppen, met de cijfertegels half over de rand. Dan de werkruimte. Op een smal scherm wordt de zijbalk een balk bovenaan met het
    menu als chips.

    Slots: lead (intro met opmaak), actions (knoppen rechts in de band),
    crumbs (kruimelpad boven de kop), tiles (de cijfertegels).
--}}
@php
    $user = auth()->user();
    // Het overzicht staat los boven de groepen, als eerste item onder het profiel.
    $overview = ['dashboard', ['dashboard'], 'Overzicht', 'target'];
    $initials = collect(explode(' ', trim((string) $user->name)))
        ->filter()
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $groups = [
        'Dagelijks' => [
            ['dashboard.lots', ['dashboard.lots'], 'Kavels', 'gavel'],
            ['dashboard.sales', ['dashboard.sales', 'dashboard.sales.*'], 'Veilingen', 'clock'],
            ['dashboard.bids', ['dashboard.bids'], 'Biedingen', 'trending-up'],
            ['orders.index', ['orders.*'], 'Bestellingen', 'shopping-bag'],
            ['messages.index', ['messages.*'], 'Berichten', 'message-circle'],
        ],
        'Volgen' => [
            ['dashboard.watchlist', ['dashboard.watchlist'], 'Volglijst', 'heart'],
            ['dashboard.alerts', ['dashboard.alerts'], 'Alerts', 'search'],
            ['notifications.index', ['notifications.*'], 'Meldingen', 'bell'],
        ],
        'Geld en zekerheid' => array_values(array_filter([
            $user->canSeeCompanyFinance() ? ['dashboard.payouts', ['dashboard.payouts'], 'Uitbetalingen', 'banknote'] : null,
            $user->canSeeCompanyFinance() ? ['dashboard.finance', ['dashboard.finance', 'dashboard.finance.*'], 'Financiën', 'file-text'] : null,
            ['dashboard.disputes', ['dashboard.disputes'], 'Geschillen', 'shield-check'],
        ])),
        'Account' => array_values(array_filter([
            ['account.settings', ['account.*'], 'Instellingen', 'settings'],
            $user->company_id !== null ? ['organisation.show', ['organisation.*'], 'Organisatie', 'building'] : null,
            $user->company_id !== null ? ['team.index', ['team.*'], 'Team', 'users'] : null,
        ])),
    ];
    $hasTiles = isset($tiles);
    $unreadCount = $user->unreadNotifications()->count();
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
    {{-- Ingelogde schermen horen niet in een zoekindex. --}}
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ? $title . ' · ' . config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="no-js c dash">

    <a href="#hoofdinhoud" class="skip-link">Naar de hoofdinhoud</a>

    <div class="dash-shell">

        {{-- De zijbalk ------------------------------------------------------ --}}
        <aside class="dash-side">
            {{-- Alleen het beeldmerk; de naam staat al overal in de bovenbalk. --}}
            <a class="brand" href="{{ route('home') }}" aria-label="Aucvy - home">
                <svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>
            </a>

            {{-- De profielkaart: wie je bent en of je geverifieerd bent. --}}
            <a class="dash-profile" href="{{ route('account.settings') }}" aria-label="Instellingen van {{ $user->name }}">
                <span class="avatar" aria-hidden="true">
                    {{ $initials !== '' ? $initials : 'A' }}
                    @if ($user->kyc_status->allowsBidding())
                        <span class="badge" title="Geverifieerd"><x-v4.icon name="check" :size="11" stroke="3" /></span>
                    @endif
                </span>
                <b>{{ $user->name }}</b>
                <span class="line">{{ $user->company?->name ?? ($user->type?->label() ?? 'Particulier') }}</span>
                <span class="line">{{ $user->email }}</span>
            </a>

            <nav class="dash-nav" aria-label="Dashboardonderdelen">
                @php [$route, $patterns, $itemLabel, $icon] = $overview; $isOn = request()->routeIs(...$patterns); @endphp
                <a class="dash-nav-overview{{ $isOn ? ' is-on' : '' }}" href="{{ route($route) }}"@if ($isOn) aria-current="page" @endif>
                    <span class="ic"><x-v4.icon :name="$icon" :size="17" stroke="2.2" /></span>
                    {{ $itemLabel }}
                </a>
                @foreach ($groups as $label => $items)
                    <p class="dash-nav-label">{{ $label }}</p>
                    @foreach ($items as [$route, $patterns, $itemLabel, $icon])
                        @php $isOn = request()->routeIs(...$patterns); @endphp
                        <a href="{{ route($route) }}"{!! $isOn ? ' class="is-on" aria-current="page"' : '' !!}>
                            <span class="ic"><x-v4.icon :name="$icon" :size="17" stroke="2.2" /></span>
                            {{ $itemLabel }}
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <div class="dash-side-foot">
                <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen</a>
            </div>
        </aside>

        <div class="dash-main">

            {{-- Smal scherm: het merk en het menu bovenaan. ----------------- --}}
            <div class="dash-top">
                <div class="dash-top-row">
                    <a class="brand" href="{{ route('home') }}" aria-label="Aucvy - home">
                        <svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>
                        Aucvy
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="link">Uitloggen</button>
                    </form>
                </div>
                <nav class="dash-nav" aria-label="Dashboardonderdelen">
                    @foreach (collect([$overview])->merge(collect($groups)->flatten(1)) as [$route, $patterns, $itemLabel, $icon])
                        @php $isOn = request()->routeIs(...$patterns); @endphp
                        <a href="{{ route($route) }}"{!! $isOn ? ' class="is-on" aria-current="page"' : '' !!}>{{ $itemLabel }}</a>
                    @endforeach
                </nav>
            </div>

            {{-- Het navy vlak: de bovenbalk ligt er doorzichtig op, de band eronder. --}}
            <div class="dash-hero{{ $hasTiles ? '' : ' no-tiles' }}">
            {{-- De gloedvlekken uit de hero van de home, onder het stippenveld. --}}
            <span class="dash-flares" aria-hidden="true"><i class="f1"></i><i class="f2"></i><i class="f3"></i></span>
            <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>

            <header class="dash-bar">
                <div class="dash-bar-title">
                    <b>{{ $title }}</b>
                    <span>{{ $subtitle }}</span>
                </div>
                <form class="dash-search" method="GET" action="{{ route('lots.index') }}" role="search">
                    <x-v4.icon name="search" :size="16" stroke="2.2" />
                    <label class="sr-only" for="dash-q">Zoek kavels</label>
                    <input id="dash-q" name="q" type="search" placeholder="Zoek kavels, categorieën of merken" autocomplete="off">
                    <kbd>Enter</kbd>
                </form>
                <a class="dash-bar-link" href="{{ route('faq') }}"><x-v4.icon name="message-circle" :size="16" stroke="2" /> Hulp</a>
                <a class="dash-bar-link dash-bell" href="{{ route('notifications.index') }}" aria-label="Meldingen{{ $unreadCount > 0 ? ', '.$unreadCount.' ongelezen' : '' }}">
                    <x-v4.icon name="bell" :size="16" stroke="2" />
                    @if ($unreadCount > 0)
                        <span class="notif-count" aria-hidden="true">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                    @endif
                </a>
                <x-marketing.user-menu />
            </header>

            {{-- De kopband met de tegels half over de rand. ---------------- --}}
            <section class="dash-band" aria-labelledby="page-title">
                <div class="dash-band-row">
                    <div class="dash-band-text">
                        @isset($crumbs)
                            <nav class="page-crumbs" aria-label="Kruimelpad">{{ $crumbs }}</nav>
                        @endisset
                        @if ($eyebrow)
                            <span class="eyebrow">{{ $eyebrow }}</span>
                        @endif
                        <h1 id="page-title">{{ $heading ?? $title }}</h1>
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
            </section>
            </div>
            @if ($hasTiles)
                <div class="dash-tiles">{{ $tiles }}</div>
            @endif

            <main id="hoofdinhoud" tabindex="-1" class="dash-body">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Op een smal scherm, zonder zijbalk, blijft de launcher de weg naar Ivy. --}}
    <a class="ivy-launch" href="{{ route('lots.index') }}" aria-label="Open Ivy"><img
        src="{{ asset('images/agents/ivy.jpg') }}" alt="">Vraag Ivy</a>

    <x-privacy.cookie-banner />

    @stack('scripts')
</body>
</html>
