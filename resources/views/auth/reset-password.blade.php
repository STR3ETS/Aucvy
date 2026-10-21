<x-layouts.auth title="Nieuw wachtwoord" heading="Nieuw wachtwoord instellen">
    <div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            @if ($errors->any())
                <div role="alert" class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label for="email" class="block text-sm font-semibold">E-mailadres</label>
                <input id="email" name="email" type="email" required autocomplete="username"
                       value="{{ old('email', $email) }}"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold">Nieuw wachtwoord</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold">Nog een keer</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       autocomplete="new-password"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <x-ui.button type="submit" class="w-full justify-center">Wachtwoord opslaan</x-ui.button>
        </form>
    </div>
</x-layouts.auth>
