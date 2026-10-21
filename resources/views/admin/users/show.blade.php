@use('App\Support\Money\Money')

<x-layouts.admin :title="$user->name" :subtitle="$user->email.($user->trashed() ? ' · geanonimiseerd' : '')">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.users') }}" class="underline">Gebruikers</a> · #{{ $user->id }}
    </p>

    {{-- Compliance: identiteitsbesluit en blokkade, met reden en spoor. --}}
    @unless ($user->trashed())
        <section class="mt-4 rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Compliance" x-data="{ reject: false, block: false }">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-extrabold">Compliance</h2>
                    @if ($user->isBlocked())
                        <p class="mt-1 text-sm text-aj-error">Geblokkeerd sinds {{ $user->blocked_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') }}: {{ $user->blocked_reason }}</p>
                    @elseif ($user->kyc_status === \App\Enums\KycStatus::Pending)
                        <p class="mt-1 text-sm text-aj-warn-text">Identiteitscontrole aangevraagd {{ $user->kyc_requested_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '' }}; wacht op een besluit.</p>
                    @elseif ($user->kyc_status === \App\Enums\KycStatus::Rejected)
                        <p class="mt-1 text-sm text-aj-muted">Identiteit afgewezen{{ $user->kyc_note ? ': '.$user->kyc_note : '' }}.</p>
                    @else
                        <p class="mt-1 text-sm text-aj-muted">Identiteit: {{ $user->kyc_status->label() }}{{ $user->kyc_verified_at ? ' sinds '.$user->kyc_verified_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') : '' }}.</p>
                    @endif
                </div>
                @if ($user->kyc_status === \App\Enums\KycStatus::Pending)
                    <form method="POST" action="{{ route('admin.compliance.kyc', $user) }}">
                        @csrf
                        <input type="hidden" name="decision" value="approve">
                        <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">Identiteit goedkeuren</button>
                    </form>
                    <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px;color:var(--color-aj-error)" @click="reject = ! reject; block = false">Afwijzen</button>
                @endif
                @if ($user->isBlocked())
                    <form method="POST" action="{{ route('admin.compliance.unblock', $user) }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="height:40px;font-size:13px">Vrijgeven</button>
                    </form>
                @elseif (! $user->is_staff)
                    <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px;color:var(--color-aj-error)" @click="block = ! block; reject = false">Blokkeren</button>
                @endif
            </div>
            <form method="POST" action="{{ route('admin.compliance.kyc', $user) }}" x-show="reject" x-cloak class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-4">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <label for="kyc-note" class="block text-sm font-bold">Reden voor de aanvrager</label>
                <textarea id="kyc-note" name="note" rows="2" required maxlength="500" class="mt-2 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm"></textarea>
                <div class="mt-3 flex gap-2">
                    <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Identiteit afwijzen</button>
                    <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="reject = false">Annuleren</button>
                </div>
            </form>
            <form method="POST" action="{{ route('admin.compliance.block', $user) }}" x-show="block" x-cloak class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-4">
                @csrf
                <label for="block-reason" class="block text-sm font-bold">Reden voor de blokkade</label>
                <p class="text-xs text-aj-muted">Bieden, publiceren en uitbetalen gaat dicht. Inloggen en inzien blijft mogelijk. De reden staat in het auditlog.</p>
                <textarea id="block-reason" name="reason" rows="2" required minlength="5" maxlength="500" class="mt-2 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm"></textarea>
                <div class="mt-3 flex gap-2">
                    <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Account blokkeren</button>
                    <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="block = false">Annuleren</button>
                </div>
            </form>
        </section>
    @endunless

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Account">
            <h2 class="text-sm font-extrabold">Account</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Type</dt><dd class="font-semibold">{{ $user->type?->label() ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">KYC</dt><dd class="font-semibold">{{ $user->kyc_status->label() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Kan bieden</dt><dd class="font-semibold">{{ $user->can_bid ? 'ja' : 'nee' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Rol</dt><dd class="font-semibold">{{ $user->is_staff ? 'beheer' : 'klant' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Aangemeld</dt><dd class="font-semibold">{{ $user->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">E-mail bevestigd</dt><dd class="font-semibold">{{ $user->email_verified_at ? 'ja' : 'nee' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Bedrijf en cijfers">
            <h2 class="text-sm font-extrabold">Bedrijf en cijfers</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Bedrijf</dt>
                    <dd class="font-semibold">
                        @if ($user->company)
                            <a href="{{ route('admin.companies.show', $user->company) }}" class="text-aj-blue-text underline">{{ $user->company->name }}</a>
                        @else
                            -
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">KvK</dt><dd class="tabular font-semibold">{{ $user->company?->kvk_number ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Kavels</dt><dd class="tabular font-semibold">{{ $user->lots_count }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Biedingen</dt><dd class="tabular font-semibold">{{ $user->bids_count }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Aankopen</dt><dd class="tabular font-semibold">{{ $purchases->count() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Verkopen</dt><dd class="tabular font-semibold">{{ $sales->count() }}</dd></div>
            </dl>
        </section>
    </div>

    @if ($bids->isNotEmpty())
        <section class="mt-8" aria-label="Laatste biedingen">
            <h2 class="text-lg font-extrabold">Laatste biedingen</h2>
            <x-admin.table>
                <x-slot:head>
                    <th>Kavel</th><th class="text-right">Bedrag</th><th>Status</th><th>Geplaatst</th>
                </x-slot:head>
                @foreach ($bids as $bid)
                    <tr>
                        <td class="max-w-[30ch] truncate font-semibold">
                            <a href="{{ route('admin.lots.show', $bid->auction->lot) }}" class="text-aj-blue-text underline">{{ $bid->auction->lot->title }}</a>
                        </td>
                        <td class="tabular text-right font-semibold">{{ Money::format($bid->amount_cents) }}</td>
                        <td>{{ $bid->status->label() }}</td>
                        <td class="whitespace-nowrap text-aj-muted">{{ $bid->placed_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </section>
    @endif

    @if ($lots->isNotEmpty())
        <section class="mt-8" aria-label="Kavels van deze gebruiker">
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

    @foreach ([['Aankopen', $purchases], ['Verkopen', $sales]] as [$heading, $orders])
        @if ($orders->isNotEmpty())
            <section class="mt-8" aria-label="{{ $heading }}">
                <h2 class="text-lg font-extrabold">{{ $heading }}</h2>
                <x-admin.table>
                    <x-slot:head>
                        <th>Nummer</th><th>Kavel</th><th>Status</th><th class="text-right">Totaal</th>
                    </x-slot:head>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="tabular font-semibold">
                                <a href="{{ route('admin.orders.show', $order) }}" class="text-aj-blue-text underline">{{ $order->order_number }}</a>
                            </td>
                            <td class="max-w-[30ch] truncate">{{ $order->lot->title }}</td>
                            <td>{{ $order->status->label() }}</td>
                            <td class="tabular text-right font-semibold">{{ Money::format($order->total_cents) }}</td>
                        </tr>
                    @endforeach
                </x-admin.table>
            </section>
        @endif
    @endforeach
</x-layouts.admin>
