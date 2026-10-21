<x-layouts.admin :title="$company->name" :subtitle="'KVK '.$company->kvk_number.' · '.($company->isVerified() ? 'geverifieerd' : 'nog niet geverifieerd')">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.companies') }}" class="underline">Bedrijven</a> · #{{ $company->id }}
    </p>

    {{-- Bedrijfscontrole (KYB): goedkeuren of afwijzen met reden. --}}
    @if (! $company->isVerified() && ! $company->trashed())
        <section class="mt-4 rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Bedrijfscontrole" x-data="{ reject: false }">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-extrabold">Bedrijfscontrole</h2>
                    @if ($company->awaitsVerification())
                        <p class="mt-1 text-sm text-aj-warn-text">Aangevraagd {{ $company->kyb_requested_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}; wacht op een besluit.</p>
                    @elseif ($company->kyb_note)
                        <p class="mt-1 text-sm text-aj-muted">Eerder afgewezen: {{ $company->kyb_note }}</p>
                    @else
                        <p class="mt-1 text-sm text-aj-muted">Nog niet aangevraagd door het bedrijf. Goedkeuren kan ook zonder aanvraag.</p>
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
            <form method="POST" action="{{ route('admin.compliance.kyb', $company) }}" x-show="reject" x-cloak class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-4">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <label for="kyb-note" class="block text-sm font-bold">Reden voor het bedrijf</label>
                <textarea id="kyb-note" name="note" rows="2" required maxlength="500" class="mt-2 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm"></textarea>
                <div class="mt-3 flex gap-2">
                    <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Bedrijf afwijzen</button>
                    <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="reject = false">Annuleren</button>
                </div>
            </form>
        </section>
    @endif

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Bedrijfsgegevens">
            <h2 class="text-sm font-extrabold">Bedrijfsgegevens</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">KVK-nummer</dt><dd class="tabular font-semibold">{{ $company->kvk_number }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Btw-nummer</dt><dd class="tabular font-semibold">{{ $company->vat_number ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Plan</dt><dd class="font-semibold">{{ $company->plan?->label() ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Succesfee</dt><dd class="tabular font-semibold">{{ number_format((float) $company->fee_percentage, 1, ',', '.') }}%</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Geverifieerd</dt><dd class="font-semibold">{{ $company->verified_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') ?? 'nee' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">UBO geverifieerd</dt><dd class="font-semibold">{{ $company->ubo_verified_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') ?? 'nee' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Uitbetaalrekening</dt>
                    <dd class="font-semibold">
                        @if ($company->payout_iban_last4)
                            **{{ $company->payout_iban_last4 }} ({{ $company->payout_iban_name }})
                        @else
                            nog niet gekoppeld
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Gekoppelde accounts">
            <h2 class="text-sm font-extrabold">Accounts</h2>
            @if ($users->isEmpty())
                <p class="mt-2 text-sm text-aj-muted">Geen gekoppelde accounts.</p>
            @else
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($users as $user)
                        <li class="flex items-baseline justify-between gap-3">
                            <a href="{{ route('admin.users.show', $user) }}" class="font-semibold text-aj-blue-text underline">{{ $user->name }}</a>
                            <span class="whitespace-nowrap text-xs text-aj-muted">{{ $user->lots_count }} kavels · {{ $user->bids_count }} biedingen</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    @if ($lots->isNotEmpty())
        <section class="mt-8" aria-label="Kavels van dit bedrijf">
            <h2 class="text-lg font-extrabold">Kavels</h2>
            <x-admin.table>
                <x-slot:head>
                    <th>Nr</th><th>Titel</th><th>Categorie</th><th>Status</th>
                </x-slot:head>
                @foreach ($lots as $lot)
                    <tr>
                        <td class="tabular text-aj-muted">{{ $lot->public_id }}</td>
                        <td class="max-w-[30ch] truncate font-semibold">
                            <a href="{{ route('admin.lots.show', $lot) }}" class="text-aj-blue-text underline">{{ $lot->title }}</a>
                        </td>
                        <td>{{ $lot->category?->name }}</td>
                        <td>{{ $lot->status->label() }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </section>
    @endif

    @if ($reviews->isNotEmpty())
        <section class="mt-8" aria-label="Reviews">
            <h2 class="text-lg font-extrabold">Reviews</h2>
            <x-admin.table>
                <x-slot:head>
                    <th class="text-right">Sterren</th><th>Reviewer</th><th>Toelichting</th><th>Gepubliceerd</th>
                </x-slot:head>
                @foreach ($reviews as $review)
                    <tr>
                        <td class="tabular text-right font-extrabold">{{ $review->rating }}</td>
                        <td><a href="{{ route('admin.users.show', $review->reviewer) }}" class="underline">{{ $review->reviewer->name }}</a></td>
                        <td class="max-w-[40ch] truncate text-aj-muted">{{ $review->body ?? '-' }}</td>
                        <td class="whitespace-nowrap text-aj-muted">{{ $review->published_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') ?? 'nog niet' }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </section>
    @endif
</x-layouts.admin>
