{{--
    De navigatie uit preview v4: vier uitklapmenu's, zoeken met Ivy, inloggen
    en de gradientknop. Op mobiel een zoekknop, een menuknop en een sheet.
    Menu-items wijzen naar bestaande routes of naar een sectie op de home.
--}}
@php
    $menus = [
        'ontdek' => ['Ontdek', [
            ['Live veilingen', route('lots.index')],
            ['Nieuwe kavels', route('lots.index', ['toon' => 'nieuw'])],
            ['Laatste kans', route('lots.index', ['toon' => 'laatste-kans'])],
            ['Onder marktwaarde', route('lots.index', ['toon' => 'onder-marktwaarde'])],
            ['Categorieën', route('home').'#ontdek-categorieen'],
            ['Hoe het werkt', route('home').'#verkoopvormen'],
        ]],
        'verkopen' => ['Verkopen', [
            ['Plaats een kavel', route('sell')],
            ['Complete inventaris', route('home').'#zakelijk'],
            ['Promoten met Ivy', route('home').'#ivy-engine'],
            ['Tarieven', route('home').'#tarieven'],
            ['Verkooproute', route('selling.guide')],
        ]],
        'ivy' => ['Ivy', [
            ['Hoe Ivy werkt', route('home').'#ivy-engine'],
            ['Ivy voor verkopers', route('home').'#verkopersstory'],
            ['Ivy voor kopers', route('home').'#kopersstory'],
            ['Zekerheid en geschillen', route('assurance')],
            ['Veelgestelde vragen', route('faq')],
        ]],
        'zakelijk' => ['Zakelijk', [
            ['Curatoren', route('home').'#zakelijk'],
            ['Retail', route('home').'#zakelijk'],
            ['Lease en remarketing', route('home').'#zakelijk'],
            ['Overheden', route('home').'#zakelijk'],
            ['Veilingvoorstel aanvragen', route('home').'#zakelijk'],
        ]],
    ];

    $accountUrl = auth()->check()
        ? (auth()->user()->is_staff ? url('/admin') : route('dashboard'))
        : route('login');
@endphp

<nav class="v4-nav" aria-label="Hoofdnavigatie" x-data="v4Nav()" @keydown.escape.window="close(); sheet = false">
    <div class="v4-nav-inner">
        <a href="{{ route('home') }}" class="v4-brand" aria-label="Aucvy - home"><x-v4.logo :size="30" />Aucvy</a>

        <div class="v4-nav-desktop v4-nav-desktop--grow">
            @foreach ($menus as $key => [$label, $items])
                <div class="v4-nav-item" @click.outside="menu === '{{ $key }}' && close()">
                    <button type="button" :aria-expanded="menu === '{{ $key }}'" @click="toggleMenu('{{ $key }}')">
                        {{ $label }}<x-v4.icon name="chevron-down" :size="13" stroke="2.6" />
                    </button>
                    <div class="v4-menu" x-show="menu === '{{ $key }}'" x-cloak x-transition.opacity.duration.150ms>
                        @foreach ($items as [$text, $url])
                            <a href="{{ $url }}">{{ $text }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="v4-nav-desktop">
            <a href="{{ route('lots.index') }}" class="v4-nav-search"><x-v4.icon name="search" :size="14" stroke="2.4" />Zoeken met Ivy</a>
            <a href="{{ $accountUrl }}" class="v4-nav-login">{{ auth()->check() ? 'Dashboard' : 'Inloggen' }}</a>
            <x-v4.button :href="route('sell')" arrow>Kavel plaatsen</x-v4.button>
        </div>

        <div class="v4-nav-mobile">
            <a href="{{ route('lots.index') }}" class="v4-icon-btn" aria-label="Zoek met Ivy"><x-v4.icon name="search" :size="17" stroke="2.4" /></a>
            <button type="button" class="v4-icon-btn" :aria-label="sheet ? 'Menu sluiten' : 'Menu openen'" :aria-expanded="sheet" @click="sheet = ! sheet">
                <x-v4.icon name="menu" :size="18" stroke="2" x-show="! sheet" />
                <x-v4.icon name="x" :size="18" stroke="2.4" x-show="sheet" x-cloak />
            </button>
        </div>
    </div>

    {{-- Het mobiele menu: dezelfde groepen, onder elkaar. --}}
    <div class="v4-sheet" x-show="sheet" x-cloak x-transition.opacity.duration.150ms aria-label="Menu">
        @foreach ($menus as [$label, $items])
            <div class="v4-sheet-group">
                <b>{{ $label }}</b>
                @foreach ($items as [$text, $url])
                    <a href="{{ $url }}" @click="sheet = false">{{ $text }}</a>
                @endforeach
            </div>
        @endforeach
        <div class="v4-sheet-group">
            <a href="{{ $accountUrl }}">{{ auth()->check() ? 'Dashboard' : 'Inloggen' }}</a>
            @auth
                <a href="{{ route('orders.index') }}">Bestellingen</a>
                <form method="POST" action="{{ route('logout') }}" style="display:contents">
                    @csrf
                    <button type="submit" class="link">Uitloggen</button>
                </form>
            @endauth
            @guest
                <a href="{{ route('register') }}">Registreren</a>
            @endguest
        </div>
    </div>
</nav>
