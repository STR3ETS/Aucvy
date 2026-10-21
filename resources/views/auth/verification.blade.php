<x-layouts.public title="Verificatie" heading="Verificatie" eyebrow="Account" align="center"
    intro="Nodig vóór je eerste bod, niet vóór het rondkijken.">
    {{--
        De checklist uit hfst. 13.6: e-mail → telefoon → identiteit → KVK
        (alleen zakelijk). Nodig vóór je eerste bod, niet vóór het rondkijken.
    --}}
    <div class="auth-card mx-auto max-w-md">

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
            @php $identityPending = $user->kyc_status === \App\Enums\KycStatus::Pending; @endphp
            <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold">
                        <x-ui.step-icon :done="$user->kyc_status->allowsBidding()" :open="$identityPending" />
                        Identiteit{{ $identityPending ? ' - in behandeling' : ' - duurt twee minuten' }}
                    </p>
                    @unless ($user->kyc_status->allowsBidding() || $identityPending)
                        <form method="POST" action="{{ route('verification.identity') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">
                                {{ $user->kyc_status === \App\Enums\KycStatus::Rejected ? 'Opnieuw' : 'Starten' }}
                            </button>
                        </form>
                    @endunless
                </div>
                @if ($identityPending)
                    <p class="mt-1 text-xs text-aj-warn-text">{{ __('verification.identity.pending') }}</p>
                @elseif ($user->kyc_status === \App\Enums\KycStatus::Rejected)
                    <p class="mt-1 text-xs text-aj-error">
                        {{ $user->kyc_note ? __('verification.identity.rejected', ['reason' => rtrim($user->kyc_note, '.').'.']) : __('verification.identity.rejected_no_reason') }}
                    </p>
                @endif
                <p class="mt-1 text-xs text-aj-muted">
                    We bewaren alleen het resultaat en de datum - nooit je documentbeeld of selfie.
                </p>
            </li>

            {{-- 4 · UBO-verklaring, alleen zakelijk (briefing sectie 2: KYB met KvK, UBO en IBAN) --}}
            @if ($isBusiness && $company)
                <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4"
                    x-data="{ open: {{ $company->ubo_declared_at ? 'false' : 'true' }}, rows: {{ Js::from($company->ubo_declaration ?: [['name' => '', 'birth_year' => '', 'role' => 'ubo', 'share' => '']]) }} }">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold">
                            <x-ui.step-icon :done="$company->ubo_declared_at !== null" :open="$company->ubo_declared_at === null" />
                            UBO-verklaring @if ($company->ubo_declared_at === null)<span class="font-normal text-aj-muted">- Nog niet verklaard</span>@endif
                        </p>
                        @if (! $company->isVerified() && auth()->user()->canManageTeam())
                            <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="open = ! open" x-text="open ? 'Sluiten' : '{{ $company->ubo_declared_at ? 'Aanpassen' : 'Invullen' }}'"></button>
                        @endif
                    </div>
                    @if ($company->ubo_declared_at)
                        <ul class="mt-2 text-xs text-aj-muted">
                            @foreach ($company->ubo_declaration as $person)
                                <li>{{ $person['name'] }} ({{ $person['birth_year'] }}) · {{ $person['role'] === 'ubo' ? $person['share'].'%' : 'bestuurder, pseudo-UBO' }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="mt-1 text-xs text-aj-muted">Wie 25% of meer van het bedrijf houdt of de zeggenschap heeft. Is er niemand met 25%? Dan gelden de bestuurders. Compliance kijkt het na bij de KvK-beoordeling.</p>
                    @if (! $company->isVerified() && auth()->user()->canManageTeam())
                        <form method="POST" action="{{ route('verification.ubo') }}" x-show="open" x-cloak class="mt-3 grid gap-2">
                            @csrf
                            <template x-for="(row, index) in rows" :key="index">
                                <div class="grid gap-2 rounded-aj-sm border border-aj-border p-2">
                                    <input type="text" :name="'ubos[' + index + '][name]'" x-model="row.name" placeholder="Volledige naam" required maxlength="120" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:38px">
                                    <div class="grid grid-cols-[1fr_1fr_64px_auto] gap-2">
                                        <input type="number" :name="'ubos[' + index + '][birth_year]'" x-model="row.birth_year" placeholder="Geb.jaar" required min="1900" max="{{ now()->year - 16 }}" class="min-w-0 rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm" style="min-height:38px">
                                        <select :name="'ubos[' + index + '][role]'" x-model="row.role" class="min-w-0 rounded-aj-sm border border-aj-border-strong bg-aj-surface px-1 text-sm" style="min-height:38px">
                                            <option value="ubo">25% of meer</option>
                                            <option value="director">Bestuurder</option>
                                        </select>
                                        <input type="number" :name="'ubos[' + index + '][share]'" x-model="row.share" placeholder="%" min="25" max="100" :disabled="row.role !== 'ubo'" :required="row.role === 'ubo'" class="min-w-0 rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm disabled:bg-aj-bg" style="min-height:38px">
                                        <button type="button" class="btn btn-ghost" style="height:38px;padding:0 10px;font-size:13px" x-show="rows.length > 1" @click="rows.splice(index, 1)" aria-label="Persoon weghalen">×</button>
                                    </div>
                                </div>
                            </template>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="btn btn-ghost" style="height:38px;font-size:13px" @click="rows.push({ name: '', birth_year: '', role: 'ubo', share: '' })">Persoon toevoegen</button>
                                <button type="submit" class="btn btn-primary" style="height:38px;font-size:13px">Verklaring opslaan</button>
                            </div>
                        </form>
                    @endif
                </li>
            @endif

            {{-- 5 · KVK, alleen zakelijk --}}
            @if ($isBusiness)
                <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold">
                            <x-ui.step-icon :done="$company?->verified_at !== null" :open="$company?->verified_at === null" />
                            KVK - {{ $company?->name }}{{ $company?->awaitsVerification() ? ' - in behandeling' : '' }}
                        </p>
                        @if ($company?->verified_at === null && ! $company->awaitsVerification())
                            <form method="POST" action="{{ route('verification.kvk') }}">
                                @csrf
                                <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">
                                    {{ $company->kyb_note ? 'Opnieuw aanvragen' : 'Koppelen' }}
                                </button>
                            </form>
                        @endif
                    </div>
                    @if ($company?->awaitsVerification())
                        <p class="mt-1 text-xs text-aj-warn-text">{{ __('verification.kvk.pending') }}</p>
                    @elseif ($company?->verified_at === null && $company?->kyb_note)
                        <p class="mt-1 text-xs text-aj-error">{{ __('verification.kvk.rejected', ['reason' => rtrim($company->kyb_note, '.').'.']) }}</p>
                    @endif
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
