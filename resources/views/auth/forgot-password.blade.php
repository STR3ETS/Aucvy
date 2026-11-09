<x-layouts.auth title="Wachtwoord vergeten" heading="Wachtwoord vergeten"
    intro="Vul je e-mailadres in. Bestaat er een account, dan sturen we een link om een nieuw wachtwoord in te stellen.">
    <div>
        @if (session('status'))
            <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="d-stack">
            @csrf

            @error('email')
                <div role="alert" class="d-soft is-err" style="margin:0">{{ $message }}</div>
            @enderror

            <label class="d-field">E-mailadres
                <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}" class="d-input">
            </label>

            <button type="submit" class="d-btn d-btn-wide d-btn-sq">Stuur de link</button>

            <p class="d-sub" style="margin:0;font-size:14px">
                <a href="{{ route('login') }}" class="d-link" style="font-size:inherit;text-decoration:underline">Terug naar inloggen</a>
            </p>
        </form>
    </div>
</x-layouts.auth>
