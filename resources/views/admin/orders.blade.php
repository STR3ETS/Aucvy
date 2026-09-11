@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

<x-layouts.admin title="Orders" subtitle="Van hamerslag tot afronding.">
    <x-admin.table :items="$orders" searchable placeholder="Ordernummer of kavel">
        <x-slot:filters>
            <label><span class="sr-only">Status</span>
                <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                    <option value="">Alle statussen</option>
                    @foreach (OrderStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Nummer</th><th>Kavel</th><th>Koper</th><th>Verkoper</th><th>Status</th><th class="text-right">Totaal</th><th>Aangemaakt</th>
        </x-slot:head>
        @foreach ($orders as $order)
            <tr>
                <td class="tabular font-semibold">
                    <a href="{{ route('admin.orders.show', $order) }}" class="text-aj-blue-text underline">{{ $order->order_number }}</a>
                </td>
                <td class="max-w-[24ch] truncate">
                    <a href="{{ route('admin.lots.show', $order->lot) }}" class="underline">{{ $order->lot->title }}</a>
                </td>
                <td><a href="{{ route('admin.users.show', $order->buyer) }}" class="underline">{{ $order->buyer->name }}</a></td>
                <td><a href="{{ route('admin.users.show', $order->seller) }}" class="underline">{{ $order->seller->name }}</a></td>
                <td>{{ $order->status->label() }}</td>
                <td class="tabular text-right font-semibold">{{ Money::format($order->total_cents) }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $order->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM') }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
