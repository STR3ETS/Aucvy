<x-layouts.public title="Inlogcode">
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-extrabold tracking-tight">Nog één stap</h1>
        <p class="mt-2 text-sm text-aj-muted">
            Aan dit account hangt een uitbetaling, dus vragen we een extra code.
            Hij staat in je mail en is tien minuten geldig.
        </p>

        <form method="POST" action="{{ route('two-factor.store') }}" class="mt-6 space-y-4">
            @csrf

            @if ($errors->any())
                <div role="alert"
                     class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <div>
                <label for="code" class="block text-sm font-semibold">Inlogcode</label>
                <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code"
                       required autofocus maxlength="6" placeholder="123456"
                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-lg tracking-[0.3em]"
                       style="min-height: var(--spacing-cta)">
            </div>

            <x-ui.button type="submit" class="w-full justify-center">Inloggen</x-ui.button>
        </form>

        <p class="mt-4 text-xs text-aj-muted">
            Geen mail? Kijk in je spamfolder, of
            <a href="{{ route('login') }}" class="font-semibold underline">log opnieuw in</a> voor een nieuwe code.
        </p>
    </div>
</x-layouts.public>
