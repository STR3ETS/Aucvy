@use('App\Support\Money\Money')

<x-layouts.admin title="Orders">
    <x-admin.table :items="$orders" searchable placeholder="Ordernummer of kavel">
        <x-slot:head>
            <th>Nummer</th><th>Kavel</th><th>Koper</th><th>Verkoper</th><th>Status</th><th class="text-right">Totaal</th><th>Aangemaakt</th>
        </x-slot:head>
        @foreach ($orders as $order)
            <tr>
                <td class="tabular font-semibold">{{ $order->order_number }}</td>
                <td class="max-w-[24ch] truncate">{{ $order->lot->title }}</td>
                <td>{{ $order->buyer->name }}</td>
                <td>{{ $order->seller->name }}</td>
                <td>{{ $order->status->label() }}</td>
                <td class="tabular text-right font-semibold">{{ Money::format($order->total_cents) }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $order->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM') }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
