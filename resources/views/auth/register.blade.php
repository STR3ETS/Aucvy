<x-layouts.auth title="Registreren" heading="Account aanmaken"
    intro="Rondkijken, volgen en verkopen voorbereiden kan meteen. Vóór je eerste bod vragen we je verificatie af te ronden.">
    <div x-data="{ type: {{ Js::from(old('type', 'private')) }} }">

        <form method="POST" action="{{ route('register.store') }}" class="space-y-4">
            @csrf

            @if ($errors->any())
                <div role="alert" class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Soort account">
                <label class="btn" :class="type === 'private' ? 'btn-primary' : 'btn-ghost'">
                    <input type="radio" name="type" value="private" x-model="type" class="sr-only">
                    Particulier
                </label>
                <label class="btn" :class="type === 'business' ? 'btn-primary' : 'btn-ghost'">
                    <input type="radio" name="type" value="business" x-model="type" class="sr-only">
                    Zakelijk
                </label>
            </div>

            <div>
                <label for="name" class="block text-sm font-semibold">Naam</label>
                <input id="name" name="name" type="text" required autocomplete="name"
                       value="{{ old('name') }}"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <div x-show="type === 'business'" x-cloak class="space-y-4">
                <div>
                    <label for="company_name" class="block text-sm font-semibold">Bedrijfsnaam</label>
                    <input id="company_name" name="company_name" type="text"
                           value="{{ old('company_name') }}" autocomplete="organization"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                           style="min-height: var(--spacing-cta)">
                </div>
                <div>
                    <label for="kvk_number" class="block text-sm font-semibold">KVK-nummer</label>
                    <input id="kvk_number" name="kvk_number" type="text" inputmode="numeric"
                           value="{{ old('kvk_number') }}"
                           class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                           style="min-height: var(--spacing-cta)">
                    <p class="mt-1 text-xs text-aj-muted">De controle op tenaamstelling volgt in de verificatiestap.</p>
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-semibold">E-mailadres</label>
                <input id="email" name="email" type="email" required autocomplete="username"
                       value="{{ old('email') }}"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <div>
                <label for="password" class="block text-sm font-semibold">Wachtwoord</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-semibold">Wachtwoord (nog een keer)</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                       autocomplete="new-password"
                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3"
                       style="min-height: var(--spacing-cta)">
            </div>

            <x-ui.button type="submit" class="w-full justify-center">Account aanmaken</x-ui.button>

            <p class="text-sm text-aj-muted">
                Al een account?
                <a href="{{ route('login') }}" class="font-bold text-aj-blue-text underline">Inloggen</a>
            </p>
        </form>
    </div>
</x-layouts.auth>
