<x-layouts.auth title="Inloggen" heading="Inloggen"
    intro="Welkom terug. Log in om te bieden, te verkopen en je dashboard te zien.">
    <div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            @if ($errors->any())
                {{-- role="alert" zodat een schermlezer de fout meekrijgt zonder
                     dat de gebruiker eerst terug moet tabben. --}}
                <div role="alert"
                     class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label for="email" class="block text-sm font-semibold">E-mailadres</label>
                <input id="email" name="email" type="email" required autofocus
                       autocomplete="username"
                       value="{{ old('email') }}"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold">Wachtwoord</label>
                <input id="password" name="password" type="password" required
                       autocomplete="current-password"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="rounded">
                Ingelogd blijven
            </label>

            <x-ui.button type="submit" class="w-full justify-center">Inloggen</x-ui.button>

            <p class="text-sm text-aj-muted">
                <a href="{{ route('password.request') }}" class="underline">Wachtwoord vergeten?</a>
                · Nog geen account?
                <a href="{{ route('register') }}" class="font-bold text-aj-blue-text underline">Registreren</a>
            </p>
        </form>

        @if (session('status'))
            <p class="mt-4 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">{{ session('status') }}</p>
        @endif
    </div>

    {{-- Lokaal en op demo: de accounts als zwevend paneel rechtsonder, buiten de kaart. --}}
    @if (! app()->isProduction())
        <x-slot:float>
            <p class="auth-float-title">Demo-accounts</p>
            <ul>
                <li><code>anna@aucvy.test</code> geverifieerde bieder</li>
                <li><code>bram@aucvy.test</code> geverifieerde bieder</li>
                <li><code>nieuw@aucvy.test</code> nog niet geverifieerd</li>
                <li><code>verkoper@aucvy.test</code> verkoper</li>
                <li><code>beheer@aucvy.test</code> medewerker (geschillen)</li>
            </ul>
            <p class="auth-float-note">Wachtwoord: <code>wachtwoord</code></p>
        </x-slot:float>
    @endif
</x-layouts.auth>
