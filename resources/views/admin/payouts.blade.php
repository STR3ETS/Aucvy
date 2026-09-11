@use('App\Support\Money\Money')

<x-layouts.admin title="Uitbetalingen">
    <x-admin.table :items="$payouts" searchable placeholder="Verkoper">
        <x-slot:head>
            <th>Order</th><th>Kavel</th><th>Verkoper</th><th class="text-right">Bedrag</th><th class="text-right">Fee</th><th>Status</th><th>Vrijgave</th>
        </x-slot:head>
        @foreach ($payouts as $payout)
            <tr>
                <td class="tabular font-semibold">{{ $payout->order->order_number }}</td>
                <td class="max-w-[22ch] truncate">{{ $payout->order->lot->title }}</td>
                <td>{{ $payout->seller->name }}</td>
                <td class="tabular text-right font-semibold">{{ Money::format($payout->amount_cents) }}</td>
                <td class="tabular text-right text-aj-muted">{{ Money::format($payout->fee_cents) }}</td>
                <td>{{ $payout->status->label() }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $payout->hold_until?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
