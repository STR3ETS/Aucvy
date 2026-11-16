@props([
    'title',
    'subtitle' => null,
    // Een kop in de band als die anders is dan de titel in het tabblad.
    'heading' => null,
])

{{--
    De backoffice in dezelfde schil als het dashboard: links de witte zijbalk
    met het menu in groepen (elk item een pictogram), rechts het navy vlak met
    het kruimelpad en de kopband, en daaronder de werkruimte op de zachte
    vloer. Kaarten (d-card), tabellen en knoppen krijgen zo vanzelf dezelfde
    opmaak als het dashboard van de klant.

    Slots: actions (knoppen rechts in de band), crumbs (kruimelpad), tiles
    (cijfertegels half over de rand).
--}}
@php
    $user = auth()->user();
    // Het control center (briefing nieuwe functies), gefilterd op de rol
    // van de medewerker. Wat al in /admin werkte, staat in dezelfde zijbalk.
    $staffRole = App\Admin\StaffRole::of($user);
    $allowed = fn (string $area): bool => $staffRole?->can($area) ?? false;
    $groups = array_filter(array_map(fn (array $items) => array_values(array_filter($items)), [
        'Werkruimte' => [
            ['admin.index', 'Vandaag', 'layout'],
            $allowed('transactions') ? ['admin.transactions', 'Transacties', 'banknote'] : null,
            $allowed('lots') ? ['admin.lots', 'Kavels', 'gavel'] : null,
            $allowed('lots') ? ['admin.moderation', 'Moderatie', 'shield-check'] : null,
            $allowed('customers') ? ['admin.customers', 'Klanten', 'users'] : null,
            ['admin.live', 'Live', 'monitor'],
        ],
        'Klantenservice' => [
            $allowed('service') ? ['admin.service', 'Tickets en klachten', 'help'] : null,
            $allowed('service') ? ['staff.disputes.index', 'Geschillen', 'flag'] : null,
            $allowed('conversations') ? ['admin.conversations', 'Gesprekken', 'message-circle'] : null,
            $allowed('lots') ? ['admin.reviews', 'Reviews', 'badge-check'] : null,
            $allowed('lots') ? ['admin.viewings', 'Bezichtigingen', 'eye'] : null,
        ],
        'Marketing' => [
            $allowed('mail') ? ['admin.mail', 'Mail Builder', 'send'] : null,
        ],
        'Geld en cijfers' => [
            $allowed('finance') ? ['admin.finance', 'Financiën', 'file-text'] : null,
            $allowed('finance') ? ['admin.payouts', 'Uitbetalingen', 'banknote'] : null,
            $allowed('finance') ? ['admin.orders', 'Orders', 'shopping-bag'] : null,
            $allowed('reports') ? ['admin.reports', 'Rapportages', 'trending-up'] : null,
        ],
        'Veilingen' => [
            $allowed('lots') ? ['admin.auctions', 'Veilingen', 'clock'] : null,
            $allowed('lots') ? ['admin.sales', 'Gebundeld', 'layers'] : null,
            $allowed('lots') ? ['admin.bids', 'Biedingen', 'target'] : null,
            $allowed('lots') ? ['admin.risk', 'Risico', 'ban'] : null,
        ],
        'Mensen' => [
            $allowed('customers') ? ['admin.users', 'Gebruikers', 'user'] : null,
            $allowed('customers') ? ['admin.companies', 'Bedrijven', 'building'] : null,
            $allowed('customers') ? ['admin.compliance', 'Compliance', 'badge-check'] : null,
        ],
        'Systeem' => $staffRole === App\Admin\StaffRole::Owner ? [
            ['admin.ivy', 'Ivy', 'sparkles'],
            ['admin.categories', 'Categorieën', 'tag'],
            ['admin.redirects', 'Doorverwijzingen', 'refresh'],
            ['admin.ai-runs', 'AI-runs', 'wand-sparkles'],
            ['admin.auditlog', 'Auditlog', 'file'],
            ['admin.errors', 'Foutlog', 'flag'],
            ['admin.queue', 'Wachtrij', 'layers'],
            ['admin.system', 'Systeem', 'settings'],
        ] : [],
    ]));
    $isOn = fn (string $routeName): bool => request()->routeIs($routeName, $routeName.'.*')
        || ($routeName === 'staff.disputes.index' && request()->routeIs('staff.disputes.*'));
    $current = collect($groups)->flatten(1)->first(fn (array $item): bool => $isOn($item[0]));
    $initials = collect(explode(' ', (string) $user->name))->filter()->take(2)->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $hasTiles = isset($tiles);
@endphp

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Aucvy Backoffice</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <x-head.icons />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="no-js c dash is-admin" data-soft-nav="off">

    <a href="#hoofdinhoud" class="skip-link">Naar de hoofdinhoud</a>

    <div class="dash-shell">

        {{-- De zijbalk ------------------------------------------------------ --}}
        <aside class="dash-side">
            <a class="brand" href="{{ route('admin.index') }}" aria-label="Aucvy Backoffice - vandaag">
                <svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>
            </a>

            <div class="dash-profile" aria-label="Ingelogd als {{ $user->name }}">
                <span class="avatar" aria-hidden="true">{{ $initials !== '' ? $initials : 'A' }}</span>
                <span class="dash-profile-text">
                    <b>{{ $user->name }}</b>
                    <span class="line">Backoffice · {{ $staffRole?->label() ?? 'medewerker' }}</span>
                </span>
            </div>

            <nav class="dash-nav" aria-label="Beheer">
                @foreach ($groups as $label => $items)
                    <div class="dash-nav-group">
                        <p class="dash-nav-label">{{ $label }}</p>
                        @foreach ($items as [$routeName, $itemLabel, $icon])
                            <a href="{{ route($routeName) }}"{!! $isOn($routeName) ? ' class="is-on" aria-current="page"' : '' !!}>
                                <span class="ic"><x-v4.icon :name="$icon" :size="17" stroke="2.2" /></span>
                                {{ $itemLabel }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            <div class="dash-side-foot">
                <a class="btn btn-ghost" href="{{ route('home') }}">Naar de site</a>
            </div>
        </aside>

        <div class="dash-main">

            {{-- Smal scherm: het merk en het menu bovenaan. ----------------- --}}
            <div class="dash-top">
                <div class="dash-top-row">
                    <a class="brand" href="{{ route('admin.index') }}" aria-label="Aucvy Backoffice">
                        <svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>
                        Backoffice
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="link">Uitloggen</button>
                    </form>
                </div>
                <nav class="dash-nav" aria-label="Beheer">
                    @foreach (collect($groups)->flatten(1) as [$routeName, $itemLabel])
                        <a href="{{ route($routeName) }}"{!! $isOn($routeName) ? ' class="is-on" aria-current="page"' : '' !!}>{{ $itemLabel }}</a>
                    @endforeach
                </nav>
            </div>

            {{-- Het navy vlak: de bovenbalk met het kruimelpad, de band eronder. --}}
            <div class="dash-hero{{ $hasTiles ? '' : ' no-tiles' }}" style="background-image:url('{{ asset('images/design/dash-head.png') }}')">
                <header class="dash-bar">
                    <nav class="dash-crumbs" aria-label="Kruimelpad">
                        <a href="{{ route('admin.index') }}">Backoffice</a>
                        <span aria-hidden="true">/</span>
                        @isset($crumbs)
                            {{ $crumbs }}
                        @else
                            <span>{{ $title }}</span>
                        @endisset
                    </nav>
                    <form method="POST" action="{{ route('logout') }}" class="dash-bar-logout">
                        @csrf
                        <button type="submit" class="dash-bar-link">Uitloggen</button>
                    </form>
                </header>

                <section class="dash-band" aria-labelledby="page-title">
                    <div class="dash-band-row">
                        <div class="dash-band-text">
                            @if ($current && ($heading ?? $title) !== $current[1])
                                <span class="eyebrow">{{ $current[1] }}</span>
                            @endif
                            <h1 id="page-title">{{ $heading ?? $title }}</h1>
                            @if ($subtitle)
                                <p class="lead">{{ $subtitle }}</p>
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
                @if (session('status'))
                    <p class="d-soft is-ok" role="status" style="margin:0 0 14px">{{ session('status') }}</p>
                @endif

                @if ($errors->any())
                    <p role="alert" class="d-soft is-err" style="margin:0 0 14px">{{ $errors->first() }}</p>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
