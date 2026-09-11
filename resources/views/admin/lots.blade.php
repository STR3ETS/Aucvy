@use('App\Support\Money\Money')

<x-layouts.admin title="Kavels">
    <x-admin.table :items="$lots" searchable placeholder="Titel of kavelnummer">
        <x-slot:head>
            <th>Nr</th><th>Titel</th><th>Verkoper</th><th>Categorie</th><th>Status</th><th class="text-right">Stand</th><th></th>
        </x-slot:head>
        @foreach ($lots as $lot)
            <tr>
                <td class="tabular text-aj-muted">{{ $lot->public_id }}</td>
                <td class="max-w-[30ch] truncate font-semibold">{{ $lot->title }}</td>
                <td>{{ $lot->seller->name }}</td>
                <td>{{ $lot->category?->name }}</td>
                <td>{{ $lot->status->label() }}</td>
                <td class="tabular text-right font-semibold">{{ $lot->auction ? Money::format($lot->currentPriceCents()) : '-' }}</td>
                <td class="text-right">
                    @if ($lot->status->isPublic())
                        <a href="{{ route('lots.show', $lot) }}" class="font-bold text-aj-blue-text underline">Bekijk</a>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
