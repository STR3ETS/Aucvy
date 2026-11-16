@use('App\Enums\PayoutStatus')
@use('App\Support\Money\Money')

<x-layouts.admin title="Uitbetalingen" subtitle="Wat er wanneer naar verkopers gaat.">
    <x-admin.table :items="$payouts" searchable placeholder="Verkoper">
        <x-slot:filters>
            <label><span class="sr-only">Status</span>
                <select name="status" class="d-select" style="min-height:44px">
                    <option value="">Alle statussen</option>
                    @foreach (PayoutStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Order</th><th>Kavel</th><th>Verkoper</th><th class="text-right">Bedrag</th><th class="text-right">Fee</th><th>Status</th><th>Vrijgave</th>
        </x-slot:head>
        @foreach ($payouts as $payout)
            <tr>
                <td class="tabular font-semibold">
                    <a href="{{ route('admin.orders.show', $payout->order) }}" class="d-tablelink">{{ $payout->order->order_number }}</a>
                </td>
                <td class="max-w-[22ch] truncate">
                    <a href="{{ route('admin.lots.show', $payout->order->lot) }}" class="d-tablelink">{{ $payout->order->lot->title }}</a>
                </td>
                <td><a href="{{ route('admin.users.show', $payout->seller) }}" class="d-tablelink">{{ $payout->seller->name }}</a></td>
                <td class="tabular text-right font-semibold">{{ Money::format($payout->amount_cents) }}</td>
                <td class="tabular text-right text-aj-muted">{{ Money::format($payout->fee_cents) }}</td>
                <td>{{ $payout->status->label() }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $payout->hold_until?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
