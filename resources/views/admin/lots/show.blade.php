@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

<x-layouts.admin :title="$lot->title" :subtitle="$lot->public_id.' · '.$lot->status->label().' · '.$lot->sale_type->label()">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.lots') }}" class="underline">Kavels</a> · {{ $lot->public_id }}
    </p>

    @if ($lot->media->isNotEmpty())
        <div class="mt-4 flex gap-2 overflow-x-auto">
            @foreach ($lot->media as $item)
                <img src="{{ $item->path }}" alt="{{ $item->alt }}"
                     class="h-28 w-40 flex-none rounded-aj-sm border border-aj-border object-cover">
            @endforeach
        </div>
    @endif

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Kerngegevens">
            <h2 class="text-sm font-extrabold">Kerngegevens</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Status</dt><dd class="font-semibold">{{ $lot->status->label() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Verkoopvorm</dt><dd class="font-semibold">{{ $lot->sale_type->label() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Categorie</dt><dd class="font-semibold">{{ $lot->category?->name ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Conditie</dt><dd class="font-semibold">{{ $lot->condition?->label() ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Merk / model</dt><dd class="font-semibold">{{ trim(($lot->brand ?? '').' '.($lot->model ?? '')) ?: '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Bouwjaar</dt><dd class="tabular font-semibold">{{ $lot->year ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Aantal</dt><dd class="tabular font-semibold">{{ $lot->quantity }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Btw</dt><dd class="font-semibold">{{ $lot->vat_regime?->label() ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Geplaatst</dt><dd class="font-semibold">{{ $lot->published_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY, HH:mm') ?? 'nog niet' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Waarde en logistiek">
            <h2 class="text-sm font-extrabold">Waarde en logistiek</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Waarde-indicatie</dt>
                    <dd class="tabular font-semibold">
                        @if ($lot->value_min_cents !== null && $lot->value_max_cents !== null)
                            {{ Money::format($lot->value_min_cents) }} - {{ Money::format($lot->value_max_cents) }}
                        @else
                            -
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Zekerheid</dt><dd class="font-semibold">{{ $lot->value_confidence?->label() ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Transport vanaf</dt><dd class="tabular font-semibold">{{ $lot->transport_from_cents !== null ? Money::format($lot->transport_from_cents) : '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Alleen ophalen</dt><dd class="font-semibold">{{ $lot->pickup_only ? 'ja' : 'nee' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Bezichtiging</dt><dd class="font-semibold">{{ $lot->viewing_available ? 'mogelijk' : 'niet mogelijk' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Compleetheid</dt><dd class="tabular font-semibold">{{ $lot->completeness_score !== null ? $lot->completeness_score.'%' : '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Documenten</dt><dd class="tabular font-semibold">{{ $lot->documents->count() }}</dd></div>
            </dl>
        </section>

        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Verkoop en verkoper">
            <h2 class="text-sm font-extrabold">Verkoop</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Verkoper</dt>
                    <dd class="font-semibold"><a href="{{ route('admin.users.show', $lot->seller) }}" class="text-aj-blue-text underline">{{ $lot->seller->name }}</a></dd>
                </div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Bedrijf</dt><dd class="font-semibold">{{ $lot->company?->name ?? '-' }}</dd></div>
                @if ($lot->auction)
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Huidige stand</dt><dd class="tabular font-semibold">{{ Money::format($lot->currentPriceCents()) }}</dd></div>
                    @if ($lot->sale_type === SaleType::Auction)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Startprijs</dt><dd class="tabular font-semibold">{{ Money::format($lot->auction->start_price_cents ?? 0) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Biedingen / bieders</dt><dd class="tabular font-semibold">{{ $lot->auction->bid_count }} / {{ $lot->auction->bidder_count }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Verlengingen</dt><dd class="tabular font-semibold">{{ $lot->auction->extension_count }}</dd></div>
                    @endif
                    @if ($lot->auction->buy_now_price_cents !== null)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Koop direct-prijs</dt><dd class="tabular font-semibold">{{ Money::format($lot->auction->buy_now_price_cents) }}</dd></div>
                    @endif
                    @if ($lot->sale_type === SaleType::Dutch)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Afslag</dt><dd class="tabular font-semibold">{{ Money::format($lot->auction->dutch_start_cents ?? 0) }} → {{ Money::format($lot->auction->dutch_floor_cents ?? 0) }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Eindigt</dt><dd class="font-semibold">{{ $lot->auction->ends_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Gesloten</dt><dd class="font-semibold">{{ $lot->auction->closed_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? 'loopt nog' }}</dd></div>
                @endif
                @if ($lot->order)
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Order</dt>
                        <dd class="font-semibold"><a href="{{ route('admin.orders.show', $lot->order) }}" class="text-aj-blue-text underline">{{ $lot->order->order_number }}</a></dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>

    @if ($lot->description)
        <section class="mt-4 rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Omschrijving">
            <h2 class="text-sm font-extrabold">Omschrijving</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-aj-muted">{{ $lot->description }}</p>
        </section>
    @endif

    <section class="mt-8" aria-label="Biedingen">
        <h2 class="text-lg font-extrabold">Biedingen</h2>
        @if ($bids->isEmpty())
            <p class="mt-2 text-sm text-aj-muted">Nog geen biedingen.</p>
        @else
            <x-admin.table>
                <x-slot:head>
                    <th>Bieder</th><th class="text-right">Bedrag</th><th>Soort</th><th>Status</th><th>Geplaatst</th>
                </x-slot:head>
                @foreach ($bids as $bid)
                    <tr>
                        <td><a href="{{ route('admin.users.show', $bid->user) }}" class="font-semibold text-aj-blue-text underline">{{ $bid->user->name }}</a></td>
                        <td class="tabular text-right font-semibold">{{ Money::format($bid->amount_cents) }}</td>
                        <td>{{ $bid->type->label() }}</td>
                        <td>{{ $bid->status->label() }}</td>
                        <td class="whitespace-nowrap text-aj-muted">{{ $bid->placed_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm:ss') }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        @endif
    </section>

    @if ($lot->viewings->isNotEmpty())
        <section class="mt-8" aria-label="Bezichtigingen">
            <h2 class="text-lg font-extrabold">Bezichtigingen</h2>
            <x-admin.table>
                <x-slot:head>
                    <th>Aanvrager</th><th>Moment</th><th>Status</th>
                </x-slot:head>
                @foreach ($lot->viewings as $viewing)
                    <tr>
                        <td><a href="{{ route('admin.users.show', $viewing->user) }}" class="font-semibold text-aj-blue-text underline">{{ $viewing->user->name }}</a></td>
                        <td class="whitespace-nowrap">{{ $viewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                        <td>{{ $viewing->status->label() }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </section>
    @endif
</x-layouts.admin>
