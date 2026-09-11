@use('App\Support\Money\Money')

<x-layouts.admin title="Veilingen">
    <x-admin.table :items="$auctions" searchable placeholder="Kaveltitel">
        <x-slot:head>
            <th>Id</th><th>Kavel</th><th>Vorm</th><th class="text-right">Stand</th><th class="text-right">Biedingen</th><th>Eindigt</th><th>Status</th>
        </x-slot:head>
        @foreach ($auctions as $auction)
            <tr>
                <td class="tabular text-aj-muted">{{ $auction->id }}</td>
                <td class="max-w-[30ch] truncate font-semibold">{{ $auction->lot->title }}</td>
                <td>{{ $auction->lot->sale_type->label() }}</td>
                <td class="tabular text-right font-semibold">{{ Money::format($auction->current_price_cents) }}</td>
                <td class="tabular text-right">{{ $auction->bid_count }}</td>
                <td class="whitespace-nowrap">{{ $auction->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                <td>{{ $auction->isClosed() ? 'Gesloten' : 'Loopt' }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
