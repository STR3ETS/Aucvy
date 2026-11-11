<x-layouts.dashboard title="Organisatie" eyebrow="Account" :intro="'De gegevens van '.$company->name.' op Aucvy: bedrijf, pakket, verificatie en uitbetaling.'">
    <x-slot:crumbs>
        <a href="{{ route('account.settings') }}">Instellingen</a> <span aria-hidden="true">/</span> <span>Organisatie</span>
    </x-slot:crumbs>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_340px] lg:items-start">
        <div class="space-y-5">
            {{-- Eerst de samenvatting; de velden gaan pas open na Wijzig (of bij een fout in het formulier). --}}
            <section class="d-card" aria-labelledby="bedrijf" x-data="{ edit: {{ $errors->hasAny(['name', 'vat_number', 'description', 'logo', 'cover']) ? 'true' : 'false' }} }">
                <div class="d-card-head" style="align-items:center">
                    <h2 id="bedrijf" class="text-lg font-extrabold">Bedrijfsgegevens</h2>
                    @if ($mayEdit)
                        <button type="button" class="d-btn d-btn-secondary d-btn-sm" x-show="! edit" @click="edit = true">Wijzig</button>
                    @endif
                </div>
                <dl class="d-specs" style="margin-top:12px" x-show="! edit">
                    <div class="d-spec"><dt>Bedrijfsnaam</dt><dd>{{ $company->name }}</dd></div>
                    <div class="d-spec"><dt>KvK-nummer</dt><dd>{{ $company->kvk_number ?? 'nog niet opgegeven' }}</dd></div>
                    <div class="d-spec"><dt>Btw-nummer</dt><dd>{{ $company->vat_number ?: 'nog niet opgegeven' }}</dd></div>
                    <div class="d-spec"><dt>Over het bedrijf</dt><dd>{{ $company->description ? Str::limit($company->description, 60) : 'nog niet ingevuld' }}</dd></div>
                </dl>
                @unless ($mayEdit)
                    <p class="mt-3 text-xs text-aj-muted">Alleen een eigenaar of beheerder wijzigt deze gegevens.</p>
                @endunless
                @if ($mayEdit)
                <div x-show="edit" x-cloak>
                <p class="mt-1 text-sm text-aj-muted">
                    @if ($nameLocked)
                        De naam hoort bij de KvK-controle en staat vast. Klopt hij niet? <a class="underline" href="{{ route('contact') }}">Neem contact op</a>.
                    @else
                        De naam wordt bij de KvK-controle vergeleken met het handelsregister.
                    @endif
                </p>
                <form method="POST" action="{{ route('organisation.update') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf @method('PATCH')
                    <label class="grid gap-1 text-sm sm:col-span-2">
                        <span class="font-bold">Bedrijfsnaam</span>
                        <input type="text" name="name" value="{{ old('name', $company->name) }}" required maxlength="120" @disabled(! $mayEdit || $nameLocked) class="d-input disabled:bg-aj-bg disabled:text-aj-muted" style="min-height:42px">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-bold">KvK-nummer</span>
                        <input type="text" value="{{ $company->kvk_number ?? 'nog niet opgegeven' }}" disabled class="rounded-aj-sm border border-aj-border-strong bg-aj-bg px-3 text-sm text-aj-muted" style="min-height:42px">
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-bold">Btw-nummer</span>
                        <input type="text" name="vat_number" value="{{ old('vat_number', $company->vat_number) }}" maxlength="20" placeholder="NL123456789B01" @disabled(! $mayEdit) class="d-input disabled:bg-aj-bg disabled:text-aj-muted" style="min-height:42px">
                    </label>
                    <label class="grid gap-1 text-sm sm:col-span-2">
                        <span class="font-bold">Over het bedrijf</span>
                        <span class="text-xs text-aj-muted">Staat op je publieke verkopersprofiel. Geen contactgegevens: kopers bereiken je via het platform.</span>
                        <textarea name="description" rows="4" maxlength="800" @disabled(! $mayEdit) class="d-textarea">{{ old('description', $company->description) }}</textarea>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-bold">Beeldmerk</span>
                        @if ($company->logo_path)
                            <img src="{{ $company->logo_path }}" alt="" class="size-16 rounded-aj-sm object-cover">
                        @endif
                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" @disabled(! $mayEdit) class="text-sm">
                        <span class="text-xs text-aj-muted">Vierkant, jpg, png of webp, tot 2 MB.</span>
                    </label>
                    <label class="grid gap-1 text-sm">
                        <span class="font-bold">Cover</span>
                        @if ($company->cover_path)
                            <img src="{{ $company->cover_path }}" alt="" class="h-16 w-full rounded-aj-sm object-cover">
                        @endif
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" @disabled(! $mayEdit) class="text-sm">
                        <span class="text-xs text-aj-muted">Breed, jpg, png of webp, tot 4 MB.</span>
                    </label>
                    <div class="sm:col-span-2" style="display:flex;gap:8px;flex-wrap:wrap">
                        <button type="submit" class="btn btn-primary">Opslaan</button>
                        <button type="button" class="btn btn-ghost" @click="edit = false">Annuleren</button>
                    </div>
                </form>
                </div>
                @endif
            </section>

            <section class="d-card" aria-labelledby="rekening"
                     x-data="{ edit: {{ $errors->hasAny(['iban', 'iban_name', 'current_password']) ? 'true' : 'false' }} }"
                     x-init="if (location.hash === '#rekening' && {{ $company->payout_iban_last4 ? 'false' : 'true' }}) edit = true">
                <div class="d-titleline">
                    <h2 id="rekening" class="text-lg font-extrabold">Uitbetaalrekening</h2>
                    <x-dashboard.help text="We bewaren alleen de laatste vier cijfers en de tenaamstelling; het volledige nummer gaat naar de betaalprovider zodra die gekoppeld is. Zodra er een rekening staat, logt iedereen van het bedrijf in met een extra code per mail." />
                </div>
                @if ($company->payout_iban_last4)
                    <p class="mt-1 text-sm">Rekening eindigend op <b class="tabular">{{ $company->payout_iban_last4 }}</b>, op naam van <b>{{ $company->payout_iban_name }}</b>.</p>
                @else
                    <p class="mt-1 text-sm text-aj-muted">Nog geen rekening. Zonder rekening kan een verkoop niet worden uitbetaald.</p>
                @endif
                @if ($mayEditPayout)
                    <div class="mt-3" x-show="! edit">
                        <button type="button" class="d-btn {{ $company->payout_iban_last4 ? 'd-btn-secondary' : '' }} d-btn-sm" @click="edit = true">{{ $company->payout_iban_last4 ? 'Rekening wijzigen' : 'Rekening toevoegen' }}</button>
                    </div>
                    <form method="POST" action="{{ route('organisation.payout') }}" class="mt-4 grid gap-4 sm:grid-cols-2" x-show="edit" x-cloak>
                        @csrf @method('PATCH')
                        <label class="grid gap-1 text-sm">
                            <span class="font-bold">IBAN</span>
                            <input type="text" name="iban" value="{{ old('iban') }}" required maxlength="40" placeholder="NL00 BANK 0123 4567 89" autocomplete="off" class="d-input" style="min-height:42px">
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-bold">Tenaamstelling</span>
                            <input type="text" name="iban_name" value="{{ old('iban_name', $company->payout_iban_name) }}" required maxlength="120" class="d-input" style="min-height:42px">
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-bold">Je wachtwoord</span>
                            <span class="text-xs text-aj-muted">Ter bevestiging: dit is waar het geld heen gaat.</span>
                            <input type="password" name="current_password" required autocomplete="current-password" class="d-input" style="min-height:42px">
                            @error('current_password')<span class="text-xs text-aj-error">{{ $message }}</span>@enderror
                        </label>
                        <div class="sm:col-span-2" style="display:flex;gap:8px;flex-wrap:wrap">
                            <button type="submit" class="btn btn-primary">{{ $company->payout_iban_last4 ? 'Rekening wijzigen' : 'Rekening vastleggen' }}</button>
                            <button type="button" class="btn btn-ghost" @click="edit = false">Annuleren</button>
                        </div>
                    </form>
                @else
                    <p class="mt-3 text-xs text-aj-muted">Alleen de eigenaar legt de rekening vast of wijzigt hem.</p>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="d-card" aria-labelledby="pakket">
                <h2 id="pakket" class="text-sm font-extrabold">Pakket</h2>
                <p class="mt-2 text-2xl font-extrabold">{{ $company->plan->label() }}</p>
                <p class="text-sm text-aj-muted">Succesfee <b class="tabular text-aj-text">{{ rtrim(rtrim(number_format((float) $company->fee_percentage, 2, ',', '.'), '0'), ',') }}%</b> over het hamerbedrag, exclusief btw.</p>
                <p class="mt-3 text-xs text-aj-muted">Wisselen van pakket gaat via <a class="underline" href="{{ route('contact') }}">support</a>. Wat de pakketten bieden staat op <a class="underline" href="{{ route('pricing') }}">de tarievenpagina</a>.</p>
            </section>

            <section class="d-card" aria-labelledby="verificatie">
                <h2 id="verificatie" class="text-sm font-extrabold">Verificatie</h2>
                <p class="mt-2 text-sm">
                    @if ($company->isVerified())
                        <span class="rounded-full bg-aj-success/10 px-2.5 py-1 text-xs font-bold text-aj-success-text">KvK geverifieerd</span>
                    @elseif ($company->awaitsVerification())
                        <span class="rounded-full bg-aj-warn/10 px-2.5 py-1 text-xs font-bold text-aj-warn-text">In behandeling</span>
                    @else
                        <span class="rounded-full bg-aj-error/10 px-2.5 py-1 text-xs font-bold text-aj-error">Nog niet geverifieerd</span>
                    @endif
                </p>
                <p class="mt-2 text-xs text-aj-muted">Verkopen kan pas na de KvK-controle. <a class="underline" href="{{ route('verification.overview') }}">Naar verificatie</a>.</p>
            </section>

            <section class="d-card" aria-labelledby="team">
                <h2 id="team" class="text-sm font-extrabold">Team</h2>
                <p class="mt-2 text-sm">{{ $members }} {{ $members === 1 ? 'persoon werkt' : 'personen werken' }} namens {{ $company->name }}.</p>
                <a class="btn btn-ghost mt-3" href="{{ route('team.index') }}" style="height:38px;font-size:13px">Team beheren</a>
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
