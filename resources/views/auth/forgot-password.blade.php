<x-layouts.auth title="Wachtwoord vergeten" heading="Wachtwoord vergeten"
    intro="Vul je e-mailadres in; je krijgt een link om een nieuw wachtwoord in te stellen.">
    <div>

        @if (session('status'))
            <p class="mt-4 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf

            @error('email')
                <div role="alert" class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <label for="email" class="block text-sm font-semibold">E-mailadres</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username"
                       value="{{ old('email') }}"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <x-ui.button type="submit" class="w-full justify-center">Stuur herstel-link</x-ui.button>
        </form>
    </div>
</x-layouts.auth>
