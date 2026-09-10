<x-layouts.public title="Inloggen">
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-extrabold tracking-tight">Inloggen</h1>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
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

        @if (! app()->isProduction())
            <div class="mt-8 rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4 text-sm">
                <p class="font-semibold">Demo-accounts</p>
                <ul class="mt-2 space-y-1 text-aj-muted">
                    <li><code class="font-mono">anna@aucvy.test</code> - geverifieerde bieder</li>
                    <li><code class="font-mono">bram@aucvy.test</code> - geverifieerde bieder</li>
                    <li><code class="font-mono">nieuw@aucvy.test</code> - nog niet geverifieerd</li>
                    <li><code class="font-mono">verkoper@aucvy.test</code> - verkoper</li>
                    <li><code class="font-mono">beheer@aucvy.test</code> - medewerker (geschillen)</li>
                </ul>
                <p class="mt-2 text-aj-muted">Wachtwoord: <code class="font-mono">wachtwoord</code></p>
            </div>
        @endif
    </div>
</x-layouts.public>
