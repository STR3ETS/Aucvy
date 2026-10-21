<x-layouts.admin title="Compliance" :subtitle="$identities->count().' identiteitscontroles en '.$companies->count().' bedrijfscontroles wachten op een besluit'">

    {{-- Identiteit --}}
    <section class="mt-6" aria-labelledby="kyc-title">
        <h2 id="kyc-title" class="text-sm font-extrabold">Identiteit te beoordelen</h2>
        <p class="text-xs text-aj-muted">Zolang er geen identiteitsdienst gekoppeld is, is dit de handmatige controle. Goedkeuren zet bieden open; afwijzen gaat met reden naar de aanvrager.</p>
        @if ($identities->isEmpty())
            <p class="mt-3 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-5 text-center text-sm text-aj-muted">Niets te beoordelen.</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($identities as $user)
                    <li class="rounded-aj-s border border-aj-border bg-aj-surface p-4" x-data="{ reject: false }">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold"><a href="{{ route('admin.users.show', $user) }}" class="hover:underline">{{ $user->name }}</a>
                                    <span class="ml-2 rounded-full bg-aj-warn/10 px-2 py-0.5 text-xs font-bold text-aj-warn-text">{{ $user->kyc_status->label() }}</span></p>
                                <p class="text-sm text-aj-muted">{{ $user->email }} · {{ $user->type?->label() }}{{ $user->company ? ' · '.$user->company->name : '' }}
                                    · telefoon {{ $user->phone ?? 'onbekend' }} · aangevraagd {{ $user->kyc_requested_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</p>
                            </div>
                            <form method="POST" action="{{ route('admin.compliance.kyc', $user) }}">
                                @csrf
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">Goedkeuren</button>
                            </form>
                            <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px;color:var(--color-aj-error)" @click="reject = ! reject">Afwijzen</button>
                        </div>
                        <form method="POST" action="{{ route('admin.compliance.kyc', $user) }}" x-show="reject" x-cloak class="mt-3 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-4">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <label for="kyc-note-{{ $user->id }}" class="block text-sm font-bold">Reden voor de aanvrager</label>
                            <textarea id="kyc-note-{{ $user->id }}" name="note" rows="2" required maxlength="500" class="mt-2 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm"></textarea>
                            <div class="mt-3 flex gap-2">
                                <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Identiteit afwijzen</button>
                                <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="reject = false">Annuleren</button>
                            </div>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Bedrijven --}}
    <section class="mt-8" aria-labelledby="kyb-title">
        <h2 id="kyb-title" class="text-sm font-extrabold">Bedrijven te beoordelen (KvK)</h2>
        <p class="text-xs text-aj-muted">Nagekeken op naam, KvK-nummer en vertegenwoordiger. Goedkeuren maakt publiceren en uitbetalen mogelijk voor alle accounts van het bedrijf.</p>
        @if ($companies->isEmpty())
            <p class="mt-3 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-5 text-center text-sm text-aj-muted">Niets te beoordelen.</p>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($companies as $company)
                    <li class="rounded-aj-s border border-aj-border bg-aj-surface p-4" x-data="{ reject: false }">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold"><a href="{{ route('admin.companies.show', $company) }}" class="hover:underline">{{ $company->name }}</a></p>
                                <p class="text-sm text-aj-muted">KvK {{ $company->kvk_number }}{{ $company->vat_number ? ' · btw '.$company->vat_number : '' }}
                                    · {{ $company->users->pluck('name')->join(', ') }}
                                    · aangevraagd {{ $company->kyb_requested_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</p>
                                @if ($company->kyb_note)
                                    <p class="mt-1 text-xs text-aj-warn-text">Eerder afgewezen: {{ $company->kyb_note }}</p>
                                @endif
                                @if ($company->ubo_declared_at)
                                    <p class="mt-1 text-xs">UBO's: @foreach ($company->ubo_declaration as $person){{ $loop->first ? '' : ', ' }}{{ $person['name'] }} ({{ $person['birth_year'] }}, {{ $person['role'] === 'ubo' ? $person['share'].'%' : 'bestuurder' }})@endforeach</p>
                                @else
                                    <p class="mt-1 text-xs text-aj-warn-text">Geen UBO-verklaring.</p>
                                @endif
                            </div>
                            <a href="https://www.kvk.nl/zoeken/?source=all&q={{ urlencode($company->kvk_number) }}" target="_blank" rel="noopener" class="btn btn-ghost" style="height:40px;font-size:13px">Zoek in KvK</a>
                            <form method="POST" action="{{ route('admin.compliance.kyb', $company) }}">
                                @csrf
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">Goedkeuren</button>
                            </form>
                            <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px;color:var(--color-aj-error)" @click="reject = ! reject">Afwijzen</button>
                        </div>
                        <form method="POST" action="{{ route('admin.compliance.kyb', $company) }}" x-show="reject" x-cloak class="mt-3 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-4">
                            @csrf
                            <input type="hidden" name="decision" value="reject">
                            <label for="kyb-note-{{ $company->id }}" class="block text-sm font-bold">Reden voor het bedrijf</label>
                            <textarea id="kyb-note-{{ $company->id }}" name="note" rows="2" required maxlength="500" class="mt-2 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm"></textarea>
                            <div class="mt-3 flex gap-2">
                                <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Bedrijf afwijzen</button>
                                <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="reject = false">Annuleren</button>
                            </div>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Geblokkeerd en afgewezen --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section aria-labelledby="blocked-title">
            <h2 id="blocked-title" class="text-sm font-extrabold">Geblokkeerde accounts</h2>
            @if ($blocked->isEmpty())
                <p class="mt-3 text-sm text-aj-muted">Geen geblokkeerde accounts.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($blocked as $user)
                        <li class="flex flex-wrap items-center gap-3 rounded-aj-s border border-aj-border bg-aj-surface p-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold"><a href="{{ route('admin.users.show', $user) }}" class="hover:underline">{{ $user->name }}</a></p>
                                <p class="text-xs text-aj-muted">{{ $user->blocked_reason }} · sinds {{ $user->blocked_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') }}</p>
                            </div>
                            <form method="POST" action="{{ route('admin.compliance.unblock', $user) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px">Vrijgeven</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-labelledby="rejected-title">
            <h2 id="rejected-title" class="text-sm font-extrabold">Afgewezen identiteiten</h2>
            @if ($rejected->isEmpty())
                <p class="mt-3 text-sm text-aj-muted">Geen afgewezen aanvragen.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($rejected as $user)
                        <li class="rounded-aj-s border border-aj-border bg-aj-surface p-4">
                            <p class="font-bold"><a href="{{ route('admin.users.show', $user) }}" class="hover:underline">{{ $user->name }}</a></p>
                            <p class="text-xs text-aj-muted">{{ $user->kyc_note ?? 'Zonder toelichting' }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.admin>
