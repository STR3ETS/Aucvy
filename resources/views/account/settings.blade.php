<x-layouts.dashboard title="Instellingen" eyebrow="Account" intro="Je gegevens, wachtwoord, meldingen en de apparaten waarop je bent ingelogd.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('verification.overview') }}">Verificatie</a>
    </x-slot:actions>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">

        {{-- Profiel --}}
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="profiel">
            <h2 id="profiel" class="text-lg font-extrabold">Gegevens</h2>
            <p class="mt-1 text-sm text-aj-muted">Je e-mailadres is {{ $user->email }}. Dat wijzigen gaat via de klantenservice, omdat het je inlognaam en je bevestiging is.</p>
            <form method="POST" action="{{ route('account.profile') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <label class="block">
                    <span class="text-sm font-bold">Naam</span>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="120" autocomplete="name"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Biednaam</span>
                    <span class="block text-xs text-aj-muted">Zo zien verkopers en andere bieders je. Leeg is "Bieder {{ $user->bidderNumber() }}". Nooit je echte naam, mailadres of telefoonnummer.</span>
                    <input type="text" name="display_name" value="{{ old('display_name', $user->display_name) }}" maxlength="40" placeholder="Bieder {{ $user->bidderNumber() }}"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Telefoonnummer</span>
                    <span class="block text-xs text-aj-muted">Een nieuw nummer vraagt om een nieuwe bevestiging bij je verificatie.</span>
                    <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" placeholder="+31 6 12345678"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                </label>
                <button type="submit" class="btn btn-primary">Opslaan</button>
            </form>
        </section>

        {{-- Wachtwoord --}}
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="wachtwoord">
            <h2 id="wachtwoord" class="text-lg font-extrabold">Wachtwoord</h2>
            <form method="POST" action="{{ route('account.password') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <label class="block">
                    <span class="text-sm font-bold">Huidig wachtwoord</span>
                    <input type="password" name="current_password" required autocomplete="current-password"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Nieuw wachtwoord</span>
                    <input type="password" name="password" required autocomplete="new-password"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                </label>
                <label class="block">
                    <span class="text-sm font-bold">Nog een keer</span>
                    <input type="password" name="password_confirmation" required autocomplete="new-password"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                </label>
                <button type="submit" class="btn btn-primary">Wachtwoord wijzigen</button>
            </form>
        </section>

        {{-- Tweede inlogstap: authenticator-app (besluit Raph, 2 november 2026), mail als terugval. --}}
        <section id="tweestaps" class="rounded-aj-s border border-aj-border bg-aj-surface p-6 lg:col-span-2" aria-labelledby="tweestaps-kop">
            <h2 id="tweestaps-kop" class="text-lg font-extrabold">Tweede inlogstap</h2>

            @if ($twoFactor['active'])
                <p class="mt-1 text-sm">
                    <span class="rounded-full bg-aj-success/10 px-2 py-0.5 text-xs font-bold text-aj-success-text">actief</span>
                    Bij elke login vragen we de code uit je authenticator-app. Telefoon niet bij de hand? Dan kun je een code per mail laten sturen of een herstelcode gebruiken.
                </p>
                <p class="mt-2 text-xs text-aj-muted">Nog {{ $twoFactor['recovery_left'] }} van de {{ \App\Support\TwoFactor\Authenticator::RECOVERY_CODE_COUNT }} herstelcodes over.</p>
            @elseif ($twoFactor['setup_secret'])
                <div class="mt-3 flex flex-wrap items-start gap-6">
                    <div class="rounded-aj-sm border border-aj-border bg-white p-3" aria-hidden="true">{!! $twoFactor['setup_qr'] !!}</div>
                    <div class="min-w-0 flex-1 text-sm">
                        <ol class="list-decimal space-y-1 pl-5">
                            <li>Open je authenticator-app (bijvoorbeeld Google Authenticator, Microsoft Authenticator of 1Password).</li>
                            <li>Scan de QR-code, of voer deze sleutel in: <code class="break-all rounded bg-aj-bg px-1.5 py-0.5 font-mono text-xs">{{ chunk_split($twoFactor['setup_secret'], 4, ' ') }}</code></li>
                            <li>Vul de code van zes cijfers in die de app toont.</li>
                        </ol>
                        <form method="POST" action="{{ route('account.two-factor.confirm') }}" class="mt-4 flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="min-w-0">
                                <span class="text-sm font-bold">Code uit de app</span>
                                <input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456"
                                       class="tabular mt-1 w-40 rounded-aj-sm border border-aj-border-strong px-3 text-sm tracking-[0.2em]" style="min-height:var(--cta-h)">
                            </label>
                            <button type="submit" class="btn btn-primary">Koppelen</button>
                        </form>
                        <form method="POST" action="{{ route('account.two-factor.cancel') }}" class="mt-2">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-aj-muted underline">Annuleren</button>
                        </form>
                    </div>
                </div>
            @else
                <p class="mt-1 text-sm text-aj-muted">
                    {{ $user->requiresSecondFactor()
                        ? 'Nu krijg je bij elke login een code per mail, omdat er een uitbetaalrekening aan je account hangt. Met een authenticator-app gaat dat sneller en veiliger.'
                        : 'Koppel een authenticator-app, dan vragen we bij elke login ook een code uit die app. Met een uitbetaalrekening is een tweede stap altijd aan.' }}
                </p>
            @endif

            @if (session('recovery_codes'))
                <div class="mt-4 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-4">
                    <p class="text-sm font-bold">Je herstelcodes</p>
                    <p class="mt-1 text-xs text-aj-muted">Elke code werkt één keer, als je je telefoon kwijt bent. Je ziet ze alleen nu; bewaar ze in een wachtwoordmanager of op papier.</p>
                    <ul class="tabular mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4">
                        @foreach (session('recovery_codes') as $code)
                            <li class="rounded bg-aj-surface px-2 py-1">{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless ($twoFactor['setup_secret'])
                <div class="mt-4 flex flex-wrap gap-4">
                    @if ($twoFactor['active'])
                        <form method="POST" action="{{ route('account.two-factor.recovery') }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <label>
                                <span class="sr-only">Je wachtwoord</span>
                                <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord"
                                       class="rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                            </label>
                            <button type="submit" class="btn btn-ghost">Nieuwe herstelcodes</button>
                        </form>
                        <form method="POST" action="{{ route('account.two-factor.disable') }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            @method('DELETE')
                            <label>
                                <span class="sr-only">Je wachtwoord</span>
                                <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord"
                                       class="rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                            </label>
                            <button type="submit" class="btn btn-ghost" style="color:var(--color-aj-error)">App ontkoppelen</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('account.two-factor.start') }}" class="flex flex-wrap items-end gap-2">
                            @csrf
                            <label>
                                <span class="sr-only">Je wachtwoord</span>
                                <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord"
                                       class="rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                            </label>
                            <button type="submit" class="btn btn-primary">Authenticator-app koppelen</button>
                        </form>
                    @endif
                </div>
            @endunless
        </section>

        {{-- Meldingen --}}
        <section id="meldingen" class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="meldingen-kop">
            <h2 id="meldingen-kop" class="text-lg font-extrabold">Meldingen</h2>
            <p class="mt-1 text-sm text-aj-muted">Berichten over je bestellingen, uitbetalingen en verificatie krijg je altijd. Dit zijn de meldingen die je zelf aan of uit zet; in-app staan ze altijd.</p>
            @unless ($hasPush)
                <p class="mt-2 text-xs text-aj-muted">Pushmeldingen staan voor dit apparaat nog niet aan. Zet ze aan via de knop op een veilingpagina.</p>
            @endunless
            <form method="POST" action="{{ route('account.notifications') }}" class="mt-4 space-y-3">
                @csrf
                @method('PATCH')
                @foreach ([
                    'outbid_push' => ['Overboden: push', 'Direct op je apparaat, nog tijdens de veiling.'],
                    'outbid_email' => ['Overboden: mail', 'Als er geen apparaat voor push is ingeschreven.'],
                    'alerts_email' => ['Alerts: mail', 'Nieuwe kavels die aan een bewaarde zoekopdracht voldoen.'],
                    'watchlist_push' => ['Volglijst: push', 'Een gevolgde veiling sluit binnen een uur.'],
                    'watchlist_email' => ['Volglijst: mail', 'Als er geen push is.'],
                ] as $key => [$label, $hint])
                    <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                        <input type="checkbox" name="{{ $key }}" value="1" class="mt-0.5 size-4" @checked($preferences[$key])>
                        <span><span class="font-bold">{{ $label }}</span><span class="block text-xs text-aj-muted">{{ $hint }}</span></span>
                    </label>
                @endforeach
                <button type="submit" class="btn btn-primary">Voorkeuren opslaan</button>
            </form>

            {{-- Toestemming voor marketingmail (Mail Builder): service blijft altijd komen. --}}
            <form method="POST" action="{{ route('account.mail-consent') }}" class="mt-6 space-y-3 border-t border-aj-border pt-5">
                @csrf
                <p class="text-sm font-bold">Mail van Aucvy</p>
                <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                    <input type="checkbox" name="activation" value="1" class="mt-0.5 size-4" @checked($mailConsent['activation'])>
                    <span><span class="font-bold">Herinneringen en tips van Ivy</span><span class="block text-xs text-aj-muted">Een concept dat nog niet af is, een gevolgde kavel die sluit, kavels die bij je zoekvraag passen. Maximaal {{ (int) config('aucvy.mail.weekly_marketing_limit', 3) }} per week.</span></span>
                </label>
                <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                    <input type="checkbox" name="campaign" value="1" class="mt-0.5 size-4" @checked($mailConsent['campaign'])>
                    <span><span class="font-bold">Nieuwsbrief en campagnes</span><span class="block text-xs text-aj-muted">Nieuwe kavels per week, kijkdagen en één nieuwsbrief per maand. Alleen met jouw ja.</span></span>
                </label>
                <p class="text-xs text-aj-muted">Mails over je account en je aankopen (betaalverzoek, uitbetaling, geschil) zijn nodig en blijven komen.</p>
                <button type="submit" class="btn btn-ghost">Mailvoorkeuren opslaan</button>
            </form>
        </section>

        {{-- Sessies --}}
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="sessies">
            <h2 id="sessies" class="text-lg font-extrabold">Apparaten</h2>
            <p class="mt-1 text-sm text-aj-muted">Waar je nu bent ingelogd.</p>
            <ul class="mt-4 space-y-2 text-sm">
                @foreach ($sessions as $session)
                    <li class="flex flex-wrap items-center gap-2 rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                        <span class="font-bold">{{ $session['agent'] }}</span>
                        @if ($session['current'])
                            <span class="rounded-full bg-aj-success/10 px-2 py-0.5 text-xs font-bold text-aj-success-text">dit apparaat</span>
                        @endif
                        <span class="ml-auto text-xs text-aj-muted">{{ $session['ip'] ?? 'ip onbekend' }} · {{ $session['last_active']->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('account.sessions.logout') }}" class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                <label class="min-w-0 flex-1">
                    <span class="text-sm font-bold">Andere apparaten uitloggen</span>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                </label>
                <button type="submit" class="btn btn-ghost">Uitloggen elders</button>
            </form>
        </section>
    </div>
</x-layouts.dashboard>
