<x-layouts.public title="Verificatie">
    {{--
        De checklist uit hfst. 13.6: e-mail → telefoon → identiteit → KVK
        (alleen zakelijk). Nodig vóór je eerste bod, niet vóór het rondkijken.
    --}}
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-extrabold tracking-tight">Verificatie</h1>
        <p class="mt-1 text-sm text-aj-muted">Nodig vóór je eerste bod, niet vóór het rondkijken.</p>

        @if (session('status'))
            <p class="mt-4 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                {{ $errors->first() }}
            </div>
        @endif

        <ol class="mt-6 space-y-3">
            {{-- 1 · E-mail --}}
            <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold">
                        <x-ui.step-icon :done="$user->hasVerifiedEmail()" />
                        E-mail bevestigd
                    </p>
                    @unless ($user->hasVerifiedEmail())
                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="height:40px;font-size:13px">
                                Mail opnieuw sturen
                            </button>
                        </form>
                    @endunless
                </div>
                @unless ($user->hasVerifiedEmail())
                    <p class="mt-1 text-xs text-aj-muted">We stuurden een link naar {{ $user->email }}.</p>
                @endunless
            </li>

            {{-- 2 · Telefoon --}}
            <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <p class="text-sm font-bold">
                    <x-ui.step-icon :done="$user->phone_verified_at !== null" />
                    Telefoonnummer
                    @if ($user->phone_verified_at !== null)
                        <span class="font-normal text-aj-muted">- {{ $user->phone }}</span>
                    @endif
                </p>
                @if ($user->phone_verified_at === null)
                    <form method="POST" action="{{ route('verification.phone') }}" class="mt-2 flex gap-2">
                        @csrf
                        <input type="tel" name="phone" required placeholder="+31 6 12345678"
                               aria-label="Telefoonnummer" autocomplete="tel"
                               class="w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                               style="min-height:40px">
                        <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px;flex:none">
                            Opslaan
                        </button>
                    </form>
                @endif
            </li>

            {{-- 3 · Identiteit --}}
            <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold">
                        <x-ui.step-icon :done="$user->kyc_status->allowsBidding()" />
                        Identiteit - duurt twee minuten
                    </p>
                    @unless ($user->kyc_status->allowsBidding())
                        <form method="POST" action="{{ route('verification.identity') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">
                                Starten
                            </button>
                        </form>
                    @endunless
                </div>
                <p class="mt-1 text-xs text-aj-muted">
                    We bewaren alleen het resultaat en de datum - nooit je documentbeeld of selfie.
                </p>
            </li>

            {{-- 4 · KVK, alleen zakelijk --}}
            @if ($isBusiness)
                <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold">
                            <x-ui.step-icon :done="$company?->verified_at !== null" :open="$company?->verified_at === null" />
                            KVK - {{ $company?->name }}
                        </p>
                        @if ($company?->verified_at === null)
                            <form method="POST" action="{{ route('verification.kvk') }}">
                                @csrf
                                <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">
                                    Koppelen
                                </button>
                            </form>
                        @endif
                    </div>
                </li>
            @endif
        </ol>

        @if ($user->can_bid)
            <p class="mt-6 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">
                Je bent geverifieerd - bieden staat open.
                <a href="{{ route('lots.index') }}" class="font-bold underline">Naar de veilingen</a>
            </p>
        @endif
    </div>
</x-layouts.public>
