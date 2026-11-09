<x-layouts.auth title="Nieuw wachtwoord" heading="Nieuw wachtwoord instellen"
    intro="Kies een nieuw wachtwoord. Daarna kun je ermee inloggen.">
    <div>
        <form method="POST" action="{{ route('password.update') }}" class="d-stack">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            @if ($errors->any())
                <div role="alert" class="d-soft is-err" style="margin:0">{{ $errors->first() }}</div>
            @endif

            <label class="d-field">E-mailadres
                <input id="email" name="email" type="email" required autocomplete="username" value="{{ old('email', $email) }}" class="d-input">
            </label>

            <label class="d-field">Nieuw wachtwoord
                <input id="password" name="password" type="password" required autocomplete="new-password" class="d-input">
            </label>

            <label class="d-field">Nog een keer
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="d-input">
            </label>

            <button type="submit" class="d-btn d-btn-wide d-btn-sq">Wachtwoord opslaan</button>

            <p class="d-sub" style="margin:0;font-size:14px">
                <a href="{{ route('login') }}" class="d-link" style="font-size:inherit;text-decoration:underline">Terug naar inloggen</a>
            </p>
        </form>
    </div>
</x-layouts.auth>
