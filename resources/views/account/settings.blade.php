@php
    $company = $user->company;
    $isBusiness = $company !== null;
    $isVerified = $user->kyc_status->allowsBidding();
    $displayName = $company?->name ?? $user->name;
    $initials = collect(explode(' ', trim((string) $displayName)))->filter()->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('');

    // De tegels onder de profielkaart (ontwerp van november): elk onderdeel
    // opent een eigen pagina. Organisatie, team en koppelingen staan hier in
    // plaats van als losse items in het menu.
    $tiles = array_values(array_filter([
        ['user', 'Persoonlijke gegevens', 'Naam, e-mail, telefoon', route('account.settings.section', 'gegevens')],
        ['shield-check', 'Beveiliging', 'Wachtwoord, tweede stap', route('account.settings.section', 'beveiliging')],
        ['bell', 'Meldingen', 'E-mail, push, voorkeuren', route('account.settings.section', 'meldingen')],
        ['monitor', 'Apparaten', 'Actieve sessies', route('account.settings.section', 'apparaten')],
        $isBusiness && $user->canSeeCompanyFinance() ? ['banknote', 'Uitbetalingsgegevens', 'Bankrekening, verificatie', route('organisation.show').'#rekening'] : null,
        $isBusiness ? ['building', 'Bedrijfsgegevens', 'KVK, btw, bevoegde personen', route('organisation.show')] : null,
        $isBusiness ? ['users', 'Team', 'Collega\'s en rollen', route('team.index')] : null,
        ['send', 'Koppelingen', 'Kanalen om kavels te delen', route('account.connections')],
        $isBusiness ? null : ['building', 'Wil je ook verkopen?', 'Daarvoor heb je een zakelijk account nodig', route('business')],
    ]));

@endphp

<x-layouts.dashboard title="Instellingen" eyebrow="Account"
    :intro="$isBusiness ? 'Beheer je account, beveiliging, meldingen en uitbetaling.' : 'Beheer je account, beveiliging en meldingen.'">

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif

    {{--
        Alleen de profielkaart en de tegels: geen velden op deze pagina. Een
        oude link met een anker (#meldingen, #tweestaps) gaat door naar de
        pagina van dat onderdeel.
    --}}
    <div class="d-stack" style="gap:18px"
         x-data
         x-init="(() => {
            const sections = { gegevens: 'gegevens', profiel: 'gegevens', beveiliging: 'beveiliging', tweestaps: 'beveiliging', wachtwoord: 'beveiliging', meldingen: 'meldingen', apparaten: 'apparaten', sessies: 'apparaten' };
            const section = sections[window.location.hash.slice(1)];
            if (section) window.location.replace(@js(url('/account')) + '/' + section);
         })()">

        {{-- Wie je bent, en of je geverifieerd bent. --}}
        <section class="d-profilecard" aria-label="Account">
            <span class="d-profilecard-avatar" aria-hidden="true">{{ $initials !== '' ? $initials : 'A' }}</span>
            <div class="d-profilecard-text">
                <div class="d-lot-title">
                    <strong>{{ $displayName }}</strong>
                    @if ($isVerified)
                        <span class="d-tag tone-ok">Geverifieerd</span>
                    @else
                        <span class="d-tag tone-warn">Nog niet geverifieerd</span>
                    @endif
                </div>
                <span>{{ $isBusiness ? 'Zakelijk account' : 'Particulier account' }} · {{ $user->email }}</span>
            </div>
            <div class="d-chips" style="gap:8px">
                @unless ($isVerified)
                    <a class="d-btn d-btn-sq" href="{{ route('verification.overview') }}">Verificatie afronden</a>
                @endunless
                <a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('account.settings.section', 'gegevens') }}">Gegevens bewerken</a>
            </div>
        </section>

        <nav class="d-navtiles" aria-label="Onderdelen van je instellingen">
            @foreach ($tiles as [$icon, $label, $hint, $href])
                <a class="d-navtile" href="{{ $href }}">
                    <span class="d-navtile-icon" aria-hidden="true"><x-v4.icon :name="$icon" :size="18" stroke="2" /></span>
                    <span><strong>{{ $label }}</strong><span>{{ $hint }}</span></span>
                </a>
            @endforeach
        </nav>
    </div>
</x-layouts.dashboard>
