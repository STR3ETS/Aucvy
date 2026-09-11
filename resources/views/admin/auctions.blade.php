@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

<x-layouts.admin title="Veilingen" subtitle="Elke verkooproute, lopend en gesloten.">
    <x-admin.table :items="$auctions" searchable placeholder="Kaveltitel">
        <x-slot:filters>
            <label><span class="sr-only">Verkoopvorm</span>
                <select name="vorm" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                    <option value="">Alle vormen</option>
                    @foreach (SaleType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(request('vorm') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label><span class="sr-only">Status</span>
                <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                    <option value="">Lopend en gesloten</option>
                    <option value="loopt" @selected(request('status') === 'loopt')>Loopt</option>
                    <option value="gesloten" @selected(request('status') === 'gesloten')>Gesloten</option>
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Id</th><th>Kavel</th><th>Vorm</th><th class="text-right">Stand</th><th class="text-right">Biedingen</th><th>Eindigt</th><th>Status</th>
        </x-slot:head>
        @foreach ($auctions as $auction)
            <tr>
                <td class="tabular text-aj-muted">{{ $auction->id }}</td>
                <td class="max-w-[30ch] truncate font-semibold">
                    <a href="{{ route('admin.lots.show', $auction->lot) }}" class="text-aj-blue-text underline">{{ $auction->lot->title }}</a>
                </td>
                <td>{{ $auction->lot->sale_type->label() }}</td>
                <td class="tabular text-right font-semibold">{{ Money::format($auction->current_price_cents) }}</td>
                <td class="tabular text-right">{{ $auction->bid_count }}</td>
                <td class="whitespace-nowrap">{{ $auction->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                <td>{{ $auction->isClosed() ? 'Gesloten' : 'Loopt' }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
