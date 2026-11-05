{{-- Instellingen, onderdeel beveiliging: Wachtwoord en de tweede inlogstap. Eigen pagina sinds het ontwerp van november. --}}
<section id="beveiliging" class="d-card" aria-labelledby="wachtwoord">
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
