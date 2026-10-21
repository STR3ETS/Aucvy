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
            <p class="mt-4 text-xs text-aj-muted">
                Tweede inlogstap: {{ $user->requiresSecondFactor() ? 'actief, omdat er een uitbetaalrekening aan je account hangt. Bij elke login krijg je een code per mail.' : 'nog niet actief. Hij gaat automatisch aan zodra er een uitbetaalrekening aan je account hangt.' }}
            </p>
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
