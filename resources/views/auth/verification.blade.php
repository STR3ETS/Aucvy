{{--
    De verificatie (ontwerp van november), met de checklist uit hfst. 13.6:
    e-mail, telefoon en identiteit om te bieden; de UBO-verklaring en de KVK
    om te verkopen (alleen zakelijk). Nodig vóór je eerste bod, niet vóór het
    rondkijken.
--}}
<x-layouts.public title="Verificatie" eyebrow="Account">
    <x-slot:headline>Eén keer controleren. <span class="g">Daarna bied je gewoon.</span></x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">Nodig vóór je eerste bod, niet vóór het rondkijken. Verkopen vraagt om een extra zakelijke controle.</p>
        <div class="page-actions" style="margin-top:18px">
            <a class="btn btn-primary" href="{{ route('lots.index') }}">Naar de veilingen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            <a class="btn btn-outline-white" href="{{ route('business') }}">Zakelijk verkopen</a>
        </div>
        <div class="d-hero-trust">
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Een paar minuten</b>&nbsp;werk</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Rondkijken</b>&nbsp;kan zonder</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Geen documentbeeld</b>&nbsp;bewaard</span>
        </div>
    </x-slot:lead>
    <x-slot:actions>
        <div class="d-hero-table" aria-label="Wat je nodig hebt">
            <span class="title">Wat je nodig hebt</span>
            <div><span>Om te bieden</span><b>E-mail, telefoon, identiteit</b></div>
            <div><span>Om te verkopen</span><b>KVK en UBO-verklaring</b></div>
            <div><span>Wat we bewaren</span><b>Resultaat en datum</b></div>
        </div>
    </x-slot:actions>

    <div class="d-profile d-verify">
    <div class="d-profile-main">

        @if (session('status'))
            <p class="d-soft is-ok" style="margin:0">{{ session('status') }}</p>
        @endif

        @if ($errors->any())
            <div role="alert" class="d-soft is-err" style="margin:0">{{ $errors->first() }}</div>
        @endif

        @if ($user->can_bid)
            <p class="d-soft is-ok" style="margin:0;padding:14px 18px">
                Je bent geverifieerd: bieden staat open.
                <a href="{{ route('lots.index') }}" style="text-decoration:underline">Naar de veilingen</a>
            </p>
        @endif

        <section aria-labelledby="verify-buy">
        <h2 id="verify-buy" class="d-verify-h2">Om te bieden en kopen</h2>
        <p class="d-profile-note" style="margin-top:6px">Drie stappen, een paar minuten werk.</p>
        <ol class="d-vsteps">
            {{-- 1 · E-mail --}}
            <li class="d-vstep">
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
            <li class="d-vstep">
                <p class="text-sm font-bold">
                    <x-ui.step-icon :done="$user->phone_verified_at !== null" />
                    Telefoonnummer
                    @if ($user->phone_verified_at !== null)
                        <span class="font-normal text-aj-muted">- {{ $user->phone }} (bevestigd)</span>
                    @elseif ($user->phone !== null)
                        <span class="font-normal text-aj-muted">- {{ $user->phone }} (nog niet bevestigd)</span>
                    @endif
                </p>
                @if ($user->phone !== null && $user->phone_verified_at === null)
                    {{-- Code per sms (besluit Raph, 2 november 2026). --}}
                    <form method="POST" action="{{ route('verification.phone.confirm') }}" class="mt-2 flex gap-2">
                        @csrf
                        <input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456"
                               aria-label="Code uit de sms"
                               class="tabular w-full d-input tracking-[0.2em]"
                               style="min-height:40px">
                        <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px;flex:none">
                            Bevestigen
                        </button>
                    </form>
                    <form method="POST" action="{{ route('verification.phone.resend') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-xs font-semibold underline">Stuur een nieuwe code</button>
                    </form>
                    <details class="mt-2 text-xs">
                        <summary class="cursor-pointer font-semibold underline">Ander nummer</summary>
                        <form method="POST" action="{{ route('verification.phone') }}" class="mt-2 flex gap-2">
                            @csrf
                            <input type="tel" name="phone" required placeholder="06 12345678" aria-label="Ander telefoonnummer" autocomplete="tel"
                                   class="w-full d-input" style="min-height:40px">
                            <button type="submit" class="btn btn-ghost" style="height:40px;font-size:13px;flex:none">Code sturen</button>
                        </form>
                    </details>
                @endif
                @if ($user->phone === null)
                    <form method="POST" action="{{ route('verification.phone') }}" class="mt-2 flex gap-2">
                        @csrf
                        <input type="tel" name="phone" required placeholder="+31 6 12345678"
                               aria-label="Telefoonnummer" autocomplete="tel"
                               class="w-full d-input"
                               style="min-height:40px">
                        <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px;flex:none">
                            Code sturen
                        </button>
                    </form>
                @endif
            </li>

            {{-- 3 · Identiteit --}}
            @php $identityPending = $user->kyc_status === \App\Enums\KycStatus::Pending; @endphp
            <li class="d-vstep">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold">
                        <x-ui.step-icon :done="$user->kyc_status->allowsBidding()" :open="$identityPending" />
                        Identiteit{{ $identityPending ? ' - in behandeling' : ($user->kyc_status->allowsBidding() ? '' : ' - invullen duurt een paar minuten') }}
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
                    We bewaren alleen het resultaat en de datum, nooit je documentbeeld of selfie.
                </p>
            </li>
        </ol>
        </section>

        @if ($isBusiness)
        <section aria-labelledby="verify-sell">
        <h2 id="verify-sell" class="d-verify-h2">Om te verkopen, alleen voor bedrijven</h2>
        <p class="d-profile-note" style="margin-top:6px">Vereist vóór je eerste kavel live kan.</p>
        <ol class="d-vsteps">
            {{-- 4 · UBO-verklaring, alleen zakelijk (briefing sectie 2: KYB met KvK, UBO en IBAN) --}}
            @if ($company)
                <li class="d-vstep"
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
                                    <input type="text" :name="'ubos[' + index + '][name]'" x-model="row.name" placeholder="Volledige naam" required maxlength="120" class="w-full d-input" style="min-height:38px">
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
                <li class="d-vstep">
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
        </ol>
        </section>
        @endif
    </div>

    <aside class="d-profile-side">
        <section class="d-sidecard" aria-labelledby="keep-title">
            <h2 id="keep-title">Wat we bewaren</h2>
            <p>We bewaren alleen het resultaat en de datum, nooit je documentbeeld of selfie.</p>
        </section>
        <section class="d-sidecard" aria-labelledby="why-title">
            <h2 id="why-title">Waarom dit nodig is</h2>
            <p>Geen nepbiedingen en geen wanbetaling: iedereen die biedt is echt en aanspreekbaar.</p>
        </section>
    </aside>
    </div>
</x-layouts.public>
