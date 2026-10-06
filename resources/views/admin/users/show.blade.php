@use('App\Support\Money\Money')

<x-layouts.admin :title="$user->name" :subtitle="$user->email.($user->trashed() ? ' · geanonimiseerd' : '')">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.users') }}" class="underline">Gebruikers</a> · #{{ $user->id }}
    </p>

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
