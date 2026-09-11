@use('App\Support\Money\Money')

<x-layouts.admin title="Biedingen">
    <x-admin.table :items="$bids" searchable placeholder="Bieder of kavel">
        <x-slot:head>
            <th>Id</th><th>Kavel</th><th>Bieder</th><th class="text-right">Bedrag</th><th>Soort</th><th>Status</th><th>Geplaatst</th>
        </x-slot:head>
        @foreach ($bids as $bid)
            <tr>
                <td class="tabular text-aj-muted">{{ $bid->id }}</td>
                <td class="max-w-[26ch] truncate font-semibold">{{ $bid->auction->lot->title }}</td>
                <td>{{ $bid->user->name }}</td>
                <td class="tabular text-right font-semibold">{{ Money::format($bid->amount_cents) }}</td>
                <td>{{ $bid->type }}</td>
                <td>{{ $bid->status->value }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $bid->placed_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm:ss') }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
