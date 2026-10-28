@props(['title', 'subtitle' => null])

{{--
    De backoffice-layout: hetzelfde ontwerp als de rest van het platform,
    maar met het menu links in het navy van de donkere vlakken. Geen eigen
    designsysteem - gewoon de aj-tokens en Manrope.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · Aucvy Backoffice</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-aj-bg text-aj-text" style="font-family:'Manrope',Inter,system-ui,sans-serif">

    @php
        $groups = [
            'Operatie' => [
                ['admin.index', 'Overzicht'],
                ['admin.live', 'Live'],
                ['admin.lots', 'Kavels'],
                ['admin.moderation', 'Moderatie'],
                ['admin.risk', 'Risico'],
                ['admin.auctions', 'Veilingen'],
                ['admin.sales', 'Gebundeld'],
                ['admin.bids', 'Biedingen'],
                ['admin.orders', 'Orders'],
                ['admin.payouts', 'Uitbetalingen'],
                ['admin.viewings', 'Bezichtigingen'],
            ],
            'Mensen' => [
                ['admin.users', 'Gebruikers'],
                ['admin.companies', 'Bedrijven'],
                ['admin.compliance', 'Compliance'],
                ['admin.support', 'Support'],
                ['admin.reviews', 'Reviews'],
            ],
            'Systeem' => [
                ['admin.categories', 'Categorieën'],
                ['admin.redirects', 'Doorverwijzingen'],
                ['admin.ai-runs', 'AI-runs'],
                ['admin.auditlog', 'Auditlog'],
                ['admin.queue', 'Wachtrij'],
                ['admin.system', 'Systeem'],
            ],
        ];
    @endphp

    <div class="flex min-h-screen">

        {{-- Linkermenu ------------------------------------------------------ --}}
        <aside class="hidden w-60 shrink-0 flex-col bg-aj-navy text-white lg:flex">
            <a href="{{ route('admin.index') }}" class="flex items-center gap-2.5 px-5 pb-4 pt-5">
                <span class="size-2.5 rotate-45 rounded-[2px] bg-aj-blue" aria-hidden="true"></span>
                <span class="text-lg font-extrabold tracking-tight">Aucvy</span>
            </a>

            <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-4" aria-label="Beheer">
                @foreach ($groups as $label => $items)
                    <div>
                        <p class="px-2 pb-1.5 text-[11px] font-extrabold uppercase tracking-[0.08em] text-[#9DB9FF]/80">{{ $label }}</p>
                        <ul class="space-y-0.5">
                            @foreach ($items as [$routeName, $itemLabel])
                                @php $isActive = request()->routeIs($routeName, $routeName.'.*'); @endphp
                                <li>
                                    <a href="{{ route($routeName) }}"
                                       @class([
                                           'block rounded-aj-sm px-3 py-2 text-sm font-semibold',
                                           'bg-aj-blue text-white' => $isActive,
                                           'text-white/70 hover:bg-white/5 hover:text-white' => ! $isActive,
                                       ])
                                       @if ($isActive) aria-current="page" @endif>{{ $itemLabel }}</a>
                                </li>
                            @endforeach
                            @if ($label === 'Operatie')
                                <li>
                                    <a href="{{ route('staff.disputes.index') }}"
                                       @class([
                                           'block rounded-aj-sm px-3 py-2 text-sm font-semibold',
                                           'bg-aj-blue text-white' => request()->routeIs('staff.disputes.*'),
                                           'text-white/70 hover:bg-white/5 hover:text-white' => ! request()->routeIs('staff.disputes.*'),
                                       ])>Geschillen</a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="space-y-1 border-t border-white/10 px-3 py-4">
                <a href="{{ route('home') }}" class="block rounded-aj-sm px-3 py-2 text-sm font-semibold text-white/70 hover:bg-white/5 hover:text-white">Naar de site</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-aj-sm px-3 py-2 text-left text-sm font-semibold text-white/70 hover:bg-white/5 hover:text-white">Uitloggen</button>
                </form>
            </div>
        </aside>

        {{-- Inhoud ----------------------------------------------------------- --}}
        <div class="min-w-0 flex-1">

            {{-- Mobiel: het menu als scrollende chips bovenaan. --}}
            <div class="border-b border-aj-border bg-aj-navy px-4 py-3 lg:hidden">
                <a href="{{ route('admin.index') }}" class="flex items-center gap-2 text-white">
                    <span class="size-2 rotate-45 rounded-[2px] bg-aj-blue" aria-hidden="true"></span>
                    <span class="font-extrabold">Aucvy</span>
                    <span class="text-[10px] font-bold uppercase tracking-[0.12em] text-white/50">Backoffice</span>
                </a>
                <nav class="chip-row -mx-4 mt-3 flex gap-1.5 overflow-x-auto px-4" aria-label="Beheer">
                    @foreach (collect($groups)->flatten(1) as [$routeName, $itemLabel])
                        @php $isActive = request()->routeIs($routeName, $routeName.'.*'); @endphp
                        <a href="{{ route($routeName) }}"
                           @class([
                               'shrink-0 rounded-aj-sm px-3 py-1.5 text-xs font-bold',
                               'bg-aj-blue text-white' => $isActive,
                               'bg-white/10 text-white/80' => ! $isActive,
                           ])>{{ $itemLabel }}</a>
                    @endforeach
                </nav>
            </div>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-6xl">
                    {{-- De paginakop als heroband: het blauwe verloop met de
                         ademende stipjes, net als op het platform zelf. --}}
                    <div class="panel-hero rounded-aj-s p-6">
                        <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>
                        <h1 class="text-2xl font-extrabold tracking-[-0.03em] text-white sm:text-3xl">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="mt-1 text-sm text-white/75">{{ $subtitle }}</p>
                        @endif
                    </div>

                    @if (session('status'))
                        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm font-semibold text-aj-success-text">
                            {{ session('status') }}
                        </p>
                    @endif

                    @if ($errors->any())
                        <p role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm font-semibold text-aj-error">
                            {{ $errors->first() }}
                        </p>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
</body>
</html>
