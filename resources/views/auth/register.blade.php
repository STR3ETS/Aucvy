<x-layouts.auth title="Registreren" heading="Account aanmaken"
    intro="Rondkijken kan altijd. Met een account kun je bieden, of als bedrijf verkopen.">
    <div x-data="{ type: {{ Js::from(old('type', request('soort') === 'zakelijk' ? 'business' : 'private')) }} }">

        <form method="POST" action="{{ route('register.store') }}" class="d-stack">
            @csrf

            @if ($errors->any())
                <div role="alert" class="d-soft is-err" style="margin:0">{{ $errors->first() }}</div>
            @endif

            {{-- Kopen kan iedereen; verkopen alleen als bedrijf (ontwerp van november). --}}
            <fieldset class="fn-fieldset">
                <legend class="d-field" style="margin-bottom:6px">Wat wil je doen?</legend>
                <div class="fn-options is-two" style="gap:8px">
                    <label class="fn-option" :class="type === 'private' && 'is-on'" style="padding:10px 12px">
                        <input type="radio" name="type" value="private" x-model="type" class="sr-only">
                        <strong style="font-size:14px">Ik wil kopen</strong>
                        <span style="font-size:12px">Bieden en direct kopen</span>
                    </label>
                    <label class="fn-option" :class="type === 'business' && 'is-on'" style="padding:10px 12px">
                        <input type="radio" name="type" value="business" x-model="type" class="sr-only">
                        <strong style="font-size:14px">Ik wil verkopen</strong>
                        <span style="font-size:12px">Alleen voor bedrijven</span>
                    </label>
                </div>
            </fieldset>

            <label class="d-field">Je naam
                <input id="name" name="name" type="text" required autocomplete="name" value="{{ old('name') }}" class="d-input">
            </label>

            <div x-show="type === 'business'" x-cloak class="d-stack">
                <label class="d-field">Bedrijfsnaam
                    <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" autocomplete="organization" class="d-input" :required="type === 'business'">
                </label>
                <label class="d-field">KVK-nummer
                    <input id="kvk_number" name="kvk_number" type="text" inputmode="numeric" value="{{ old('kvk_number') }}" class="d-input" :required="type === 'business'">
                    <span class="d-hint">De controle op tenaamstelling volgt in de verificatiestap.</span>
                </label>
            </div>

            <label class="d-field">E-mailadres
                <input id="email" name="email" type="email" required autocomplete="username" value="{{ old('email') }}" class="d-input">
            </label>

            <label class="d-field">Wachtwoord
                <input id="password" name="password" type="password" required autocomplete="new-password" class="d-input">
            </label>

            <label class="fn-check" style="font-size:14px">
                <input type="checkbox" name="terms" value="1" required @checked(old('terms'))>
                <span>Ik ga akkoord met de <a class="d-link" style="font-size:inherit;text-decoration:underline" href="{{ route('legal.terms') }}" target="_blank" rel="noopener">voorwaarden</a> en de <a class="d-link" style="font-size:inherit;text-decoration:underline" href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">privacyverklaring</a>.</span>
            </label>

            <button type="submit" class="d-btn d-btn-wide d-btn-sq">Account aanmaken</button>

            <p class="d-sub" style="margin:0;font-size:14px">
                Heb je al een account?
                <a href="{{ route('login') }}" class="d-link" style="font-size:inherit;text-decoration:underline">Inloggen</a>
            </p>
        </form>
    </div>
</x-layouts.auth>
