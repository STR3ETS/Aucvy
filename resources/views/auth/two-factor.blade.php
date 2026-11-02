<x-layouts.auth title="Inlogcode" heading="Nog één stap"
    :intro="$method === 'app'
        ? 'Vul de code van zes cijfers in uit je authenticator-app.'
        : 'We vragen een extra code. Hij staat in je mail en is tien minuten geldig.'">
    <div x-data="{ recovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }">

        @if (session('status'))
            <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('two-factor.store') }}" class="space-y-4">
            @csrf

            @if ($errors->any())
                <div role="alert"
                     class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div x-show="! recovery">
                <label for="code" class="block text-sm font-semibold">{{ $method === 'app' ? 'Code uit de app' : 'Inlogcode' }}</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       :required="! recovery" :disabled="recovery" autofocus maxlength="7" placeholder="123456"
                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-lg tracking-[0.3em]"
                       style="min-height: var(--spacing-cta)">
            </div>

            @if ($hasApp)
                <div x-show="recovery" x-cloak>
                    <label for="recovery_code" class="block text-sm font-semibold">Herstelcode</label>
                    <input id="recovery_code" name="recovery_code" type="text" autocomplete="off" maxlength="24" placeholder="abcde-12345"
                           :required="recovery" :disabled="! recovery"
                           class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 font-mono text-lg"
                           style="min-height: var(--spacing-cta)">
                </div>
            @endif

            <x-ui.button type="submit" class="w-full justify-center">Inloggen</x-ui.button>
        </form>

        <div class="mt-4 space-y-2 text-xs text-aj-muted">
            @if ($hasApp)
                <p>
                    Telefoon niet bij de hand?
                    <button type="button" class="font-semibold underline" @click="recovery = ! recovery"
                            x-text="recovery ? 'Toch de code uit de app' : 'Gebruik een herstelcode'"></button>
                </p>
            @endif
            @if ($method === 'app')
                <form method="POST" action="{{ route('two-factor.mail') }}">
                    @csrf
                    <button type="submit" class="font-semibold underline">Stuur me een code per mail</button>
                </form>
            @else
                <p>
                    Geen mail? Kijk in je spamfolder, of
                    <a href="{{ route('login') }}" class="font-semibold underline">log opnieuw in</a> voor een nieuwe code.
                </p>
            @endif
        </div>
    </div>
</x-layouts.auth>
