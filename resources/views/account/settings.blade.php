@php
    $company = $user->company;
    $isBusiness = $company !== null;
    $isVerified = $user->kyc_status->allowsBidding();
    $displayName = $company?->name ?? $user->name;
    $initials = collect(explode(' ', trim((string) $displayName)))->filter()->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('');

    // De tegels onder de profielkaart (ontwerp van november). Organisatie, team
    // en koppelingen staan hier in plaats van als losse items in het menu.
    $tiles = array_values(array_filter([
        ['user', 'Persoonlijke gegevens', 'Naam, e-mail, telefoon', 'tab:gegevens'],
        ['shield-check', 'Beveiliging', 'Wachtwoord, tweede stap', 'tab:beveiliging'],
        ['bell', 'Meldingen', 'E-mail, push, voorkeuren', 'tab:meldingen'],
        ['monitor', 'Apparaten', 'Actieve sessies', 'tab:apparaten'],
        $isBusiness && $user->canSeeCompanyFinance() ? ['banknote', 'Uitbetalingsgegevens', 'Bankrekening, verificatie', route('organisation.show').'#rekening'] : null,
        $isBusiness ? ['building', 'Bedrijfsgegevens', 'KVK, btw, bevoegde personen', route('organisation.show')] : null,
        $isBusiness ? ['users', 'Team', 'Collega\'s en rollen', route('team.index')] : null,
        ['send', 'Koppelingen', 'Kanalen om kavels te delen', route('account.connections')],
        $isBusiness ? null : ['building', 'Wil je ook verkopen?', 'Daarvoor heb je een zakelijk account nodig', route('business')],
    ]));

    // Na een fout in een formulier opent het tabblad van dat formulier.
    $errorTab = match (true) {
        $errors->hasAny(['name', 'display_name', 'phone']) => 'gegevens',
        $errors->hasAny(['current_password', 'password', 'code']) => 'beveiliging',
        default => null,
    };
@endphp

<x-layouts.dashboard title="Instellingen" eyebrow="Account"
    :intro="$isBusiness ? 'Beheer je account, beveiliging, meldingen en uitbetaling.' : 'Beheer je account, beveiliging en meldingen.'">

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="d-soft is-err" style="margin:0 0 14px">{{ $errors->first() }}</div>
    @endif

    {{--
        De tegels zijn tabs: één aanklikken toont alleen dat onderdeel. Zonder
        keuze staan Beveiliging en Meldingen naast elkaar, zoals in het ontwerp.
        De keuze staat in de hash (deelbaar, en redirects als #tweestaps werken)
        en blijft na het opslaan van een formulier bewaard.
    --}}
    <div class="d-stack" style="gap:18px"
         x-data="{
            tab: 'overzicht',
            init() {
                const fromHash = { gegevens: 'gegevens', profiel: 'gegevens', beveiliging: 'beveiliging', tweestaps: 'beveiliging', wachtwoord: 'beveiliging', meldingen: 'meldingen', apparaten: 'apparaten', sessies: 'apparaten' }[window.location.hash.slice(1)];
                let remembered = null;
                try { remembered = sessionStorage.getItem('instellingen-tab'); sessionStorage.removeItem('instellingen-tab'); } catch (error) {}
                this.tab = @js($errorTab) ?? fromHash ?? remembered ?? 'overzicht';
            },
            select(name) {
                // Nog een keer op dezelfde tegel: terug naar het overzicht.
                this.tab = this.tab === name ? 'overzicht' : name;
                history.replaceState(history.state, '', this.tab === 'overzicht' ? window.location.pathname : '#' + this.tab);
            },
            remember() {
                try { sessionStorage.setItem('instellingen-tab', this.tab); } catch (error) {}
            },
         }"
         @submit="remember()">

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
                <button type="button" class="d-btn d-btn-secondary d-btn-sq" @click="tab = 'gegevens'; history.replaceState(history.state, '', '#gegevens'); $nextTick(() => document.getElementById('gegevens')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))">Gegevens bewerken</button>
            </div>
        </section>

        <nav class="d-navtiles" aria-label="Onderdelen van je instellingen">
            @foreach ($tiles as [$icon, $label, $hint, $href])
                @if (str_starts_with($href, 'tab:'))
                    @php $name = substr($href, 4); @endphp
                    <button type="button" class="d-navtile" :class="tab === '{{ $name }}' && 'is-on'" :aria-pressed="tab === '{{ $name }}'" @click="select('{{ $name }}')">
                        <span class="d-navtile-icon" aria-hidden="true"><x-v4.icon :name="$icon" :size="18" stroke="2" /></span>
                        <span><strong>{{ $label }}</strong><span>{{ $hint }}</span></span>
                    </button>
                @else
                    <a class="d-navtile" href="{{ $href }}">
                        <span class="d-navtile-icon" aria-hidden="true"><x-v4.icon :name="$icon" :size="18" stroke="2" /></span>
                        <span><strong>{{ $label }}</strong><span>{{ $hint }}</span></span>
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="d-cols">
            {{-- Wachtwoord en de tweede inlogstap --}}
            <section id="beveiliging" class="d-card d-tabpanel" x-show="tab === 'overzicht' || tab === 'beveiliging'" aria-labelledby="wachtwoord">
                <h2 id="wachtwoord" class="d-h3" style="font-size:17px">Wachtwoord en beveiliging</h2>
                <form method="POST" action="{{ route('account.password') }}" class="d-stack-tight" style="margin-top:12px">
                    @csrf
                    @method('PATCH')
                    <label class="d-field">Huidig wachtwoord
                        <input type="password" name="current_password" required autocomplete="current-password" class="d-input">
                    </label>
                    <label class="d-field">Nieuw wachtwoord
                        <input type="password" name="password" required autocomplete="new-password" class="d-input">
                    </label>
                    <label class="d-field">Nog een keer
                        <input type="password" name="password_confirmation" required autocomplete="new-password" class="d-input">
                    </label>
                    <button type="submit" class="d-btn d-btn-sq" style="align-self:flex-start">Wachtwoord wijzigen</button>
                </form>

                {{-- Tweede inlogstap: authenticator-app (besluit Raph, 2 november 2026), mail als terugval. --}}
                <div id="tweestaps" class="d-soft" style="margin-top:18px;padding:14px">
                    <h3 class="d-h3" style="font-size:14px">Tweede inlogstap</h3>

                    @if ($twoFactor['active'])
                        <p class="d-note" style="margin-top:4px">
                            <span class="d-tag tone-ok">actief</span>
                            Bij elke login vragen we de code uit je authenticator-app. Telefoon niet bij de hand? Dan kun je een code per mail laten sturen of een herstelcode gebruiken.
                        </p>
                        <p class="d-hint">Nog {{ $twoFactor['recovery_left'] }} van de {{ \App\Support\TwoFactor\Authenticator::RECOVERY_CODE_COUNT }} herstelcodes over.</p>
                    @elseif ($twoFactor['setup_secret'])
                        <div class="mt-3 flex flex-wrap items-start gap-6">
                            <div class="rounded-aj-sm border border-aj-border bg-white p-3" aria-hidden="true">{!! $twoFactor['setup_qr'] !!}</div>
                            <div class="min-w-0 flex-1 text-sm">
                                <ol class="list-decimal space-y-1 pl-5">
                                    <li>Open je authenticator-app (bijvoorbeeld Google Authenticator, Microsoft Authenticator of 1Password).</li>
                                    <li>Scan de QR-code, of voer deze sleutel in: <code class="break-all rounded bg-white px-1.5 py-0.5 font-mono text-xs">{{ chunk_split($twoFactor['setup_secret'], 4, ' ') }}</code></li>
                                    <li>Vul de code van zes cijfers in die de app toont.</li>
                                </ol>
                                <form method="POST" action="{{ route('account.two-factor.confirm') }}" class="mt-4 flex flex-wrap items-end gap-2">
                                    @csrf
                                    <label class="d-field">Code uit de app
                                        <input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" class="d-input tabular" style="width:160px;letter-spacing:.2em">
                                    </label>
                                    <button type="submit" class="d-btn d-btn-sq">Koppelen</button>
                                </form>
                                <form method="POST" action="{{ route('account.two-factor.cancel') }}" class="mt-2">
                                    @csrf
                                    <button type="submit" class="d-link">Annuleren</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <p class="d-note" style="margin-top:4px">
                            {{ $user->requiresSecondFactor()
                                ? 'Nu krijg je bij elke login een code per mail, omdat er een uitbetaalrekening aan je account hangt. Met een authenticator-app gaat dat sneller en veiliger.'
                                : 'Koppel een authenticator-app, dan vragen we bij elke login ook een code uit die app. Met een uitbetaalrekening is een tweede stap altijd aan.' }}
                        </p>
                    @endif

                    @if (session('recovery_codes'))
                        <div class="d-soft is-warn" style="margin-top:12px;font-weight:500;color:#0e1526">
                            <strong>Je herstelcodes</strong>
                            <p class="d-hint">Elke code werkt één keer, als je je telefoon kwijt bent. Je ziet ze alleen nu; bewaar ze in een wachtwoordmanager of op papier.</p>
                            <ul class="tabular mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                                @foreach (session('recovery_codes') as $code)
                                    <li class="rounded bg-white px-2 py-1">{{ $code }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @unless ($twoFactor['setup_secret'])
                        <div class="d-chips" style="margin-top:12px;gap:12px;align-items:flex-end">
                            @if ($twoFactor['active'])
                                <form method="POST" action="{{ route('account.two-factor.recovery') }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <label>
                                        <span class="sr-only">Je wachtwoord</span>
                                        <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord" class="d-input" style="margin:0">
                                    </label>
                                    <button type="submit" class="d-btn d-btn-secondary d-btn-sq">Nieuwe herstelcodes</button>
                                </form>
                                <form method="POST" action="{{ route('account.two-factor.disable') }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    @method('DELETE')
                                    <label>
                                        <span class="sr-only">Je wachtwoord</span>
                                        <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord" class="d-input" style="margin:0">
                                    </label>
                                    <button type="submit" class="d-btn d-btn-secondary d-btn-sq" style="color:#a93226">App ontkoppelen</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('account.two-factor.start') }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <label>
                                        <span class="sr-only">Je wachtwoord</span>
                                        <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord" class="d-input" style="margin:0">
                                    </label>
                                    <button type="submit" class="d-btn d-btn-secondary d-btn-sq">Authenticator-app koppelen</button>
                                </form>
                            @endif
                        </div>
                    @endunless
                </div>
            </section>

            {{-- Meldingen: de voorkeuren die je zelf aan of uit zet. --}}
            <section id="meldingen" class="d-card d-tabpanel" x-show="tab === 'overzicht' || tab === 'meldingen'" aria-labelledby="meldingen-kop">
                <h2 id="meldingen-kop" class="d-h3" style="font-size:17px">Meldingen</h2>
                <p class="d-sub">Berichten over je bestellingen, uitbetalingen en verificatie krijg je altijd.</p>
                @unless ($hasPush)
                    <p class="d-hint">Pushmeldingen staan voor dit apparaat nog niet aan. Zet ze aan via de knop op een veilingpagina.</p>
                @endunless
                <form method="POST" action="{{ route('account.notifications') }}" style="margin-top:10px">
                    @csrf
                    @method('PATCH')
                    <div class="d-prefs">
                        @foreach ([
                            'outbid_push' => ['Overboden: push', 'Direct op je apparaat, nog tijdens de veiling.'],
                            'outbid_email' => ['Overboden: mail', 'Als er geen apparaat voor push is ingeschreven.'],
                            'alerts_email' => ['Alerts: mail', 'Nieuwe kavels die aan je zoekopdracht voldoen.'],
                            'watchlist_push' => ['Volglijst: push', 'Een gevolgde veiling sluit binnen een uur.'],
                            'watchlist_email' => ['Volglijst: mail', 'Als er geen push is.'],
                        ] as $key => [$label, $hint])
                            <label class="d-pref">
                                <span><strong>{{ $label }}</strong><span>{{ $hint }}</span></span>
                                <input type="checkbox" name="{{ $key }}" value="1" @checked($preferences[$key])>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit" class="d-btn d-btn-sq" style="margin-top:12px">Voorkeuren opslaan</button>
                </form>

                {{-- Toestemming voor marketingmail (Mail Builder): service blijft altijd komen. --}}
                <form method="POST" action="{{ route('account.mail-consent') }}" style="margin-top:18px;padding-top:14px;border-top:1px solid rgba(14,21,38,.08)">
                    @csrf
                    <h3 class="d-h3" style="font-size:14px">Mail van Aucvy</h3>
                    <div class="d-prefs">
                        <label class="d-pref">
                            <span><strong>Herinneringen en tips van Ivy</strong><span>Een concept dat nog niet af is, een gevolgde kavel die sluit, kavels die bij je zoekvraag passen. Maximaal {{ (int) config('aucvy.mail.weekly_marketing_limit', 3) }} per week.</span></span>
                            <input type="checkbox" name="activation" value="1" @checked($mailConsent['activation'])>
                        </label>
                        <label class="d-pref">
                            <span><strong>Nieuwsbrief en campagnes</strong><span>Nieuwe kavels per week, kijkdagen en één nieuwsbrief per maand. Alleen met jouw ja.</span></span>
                            <input type="checkbox" name="campaign" value="1" @checked($mailConsent['campaign'])>
                        </label>
                    </div>
                    <p class="d-hint">Mails over je account en je aankopen (betaalverzoek, uitbetaling, geschil) zijn nodig en blijven komen.</p>
                    <button type="submit" class="d-btn d-btn-secondary d-btn-sq" style="margin-top:10px">Mailvoorkeuren opslaan</button>
                </form>
            </section>
            {{-- Persoonlijke gegevens --}}
            <section id="gegevens" class="d-card d-tabpanel" x-show="tab === 'gegevens'" x-cloak aria-labelledby="profiel">
                <h2 id="profiel" class="d-h3" style="font-size:17px">Persoonlijke gegevens</h2>
                <p class="d-sub">Je e-mailadres is {{ $user->email }}. Dat wijzigen gaat via de klantenservice, omdat het je inlognaam en je bevestiging is.</p>
                <form method="POST" action="{{ route('account.profile') }}" class="d-stack-tight" style="margin-top:12px">
                    @csrf
                    @method('PATCH')
                    <label class="d-field">Naam
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name" class="d-input">
                    </label>
                    <label class="d-field">Biednaam
                        <input type="text" name="display_name" value="{{ old('display_name', $user->display_name) }}" maxlength="40" placeholder="Bieder {{ $user->bidderNumber() }}" class="d-input">
                        <span class="d-hint">Zo zien verkopers en andere bieders je. Leeg is "Bieder {{ $user->bidderNumber() }}". Nooit je echte naam, mailadres of telefoonnummer.</span>
                    </label>
                    <label class="d-field">Telefoonnummer
                        <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" placeholder="+31 6 12345678" class="d-input">
                        <span class="d-hint">Een nieuw nummer vraagt om een nieuwe bevestiging bij je verificatie.</span>
                    </label>
                    <button type="submit" class="d-btn d-btn-sq" style="align-self:flex-start">Opslaan</button>
                </form>
            </section>

            {{-- Apparaten: waar je nu bent ingelogd. --}}
            <section id="apparaten" class="d-card d-tabpanel" x-show="tab === 'apparaten'" x-cloak aria-labelledby="sessies">
                <h2 id="sessies" class="d-h3" style="font-size:17px">Apparaten</h2>
                <p class="d-sub">Waar je nu bent ingelogd.</p>
                <div class="d-prefs" style="margin-top:8px">
                    @foreach ($sessions as $session)
                        <div class="d-pref">
                            <span>
                                <strong>{{ $session['agent'] }} @if ($session['current'])<span class="d-tag tone-ok" style="display:inline-flex;margin-left:6px">dit apparaat</span>@endif</strong>
                                <span>{{ $session['ip'] ?? 'ip onbekend' }} · {{ $session['last_active']->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
                <form method="POST" action="{{ route('account.sessions.logout') }}" class="flex flex-wrap items-end gap-2" style="margin-top:12px">
                    @csrf
                    <label class="d-field" style="flex:1 1 200px;min-width:0">Andere apparaten uitloggen
                        <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord" class="d-input">
                    </label>
                    <button type="submit" class="d-btn d-btn-secondary d-btn-sq">Uitloggen elders</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.dashboard>
