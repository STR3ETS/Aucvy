@use('App\Support\Money\Money')

<x-layouts.admin title="Transacties" :subtitle="$orders->total().' bestellingen, met status, fee en dossier'">
    <form method="GET" class="mt-6 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek op bestelling of kavel" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:40px">
        <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:40px">
            <option value="">Alle statussen</option>
            @foreach ($statuses as $option)<option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>@endforeach
        </select>
        <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Filter</button>
    </form>

    @if ($orders->isEmpty())
        <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center text-sm text-aj-muted">Geen transacties gevonden.</p>
    @else
        <div class="mt-4 overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface p-5">
            <table class="admin-table">
                <thead><tr><th>Bestelling</th><th>Kavel</th><th>Partijen</th><th>Status</th><th>Hamerbedrag</th><th>Fee</th><th>Totaal koper</th><th></th></tr></thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="whitespace-nowrap"><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold underline">{{ $order->order_number }}</a><span class="block text-xs text-aj-muted">{{ $order->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') }}</span></td>
                            <td>{{ $order->lot?->title }}</td>
                            <td class="text-xs">{{ $order->seller?->company?->name ?? $order->seller?->name }} aan {{ $order->buyer?->publicName() }}</td>
                            <td>{{ $order->status->label() }}@if ($order->disputes->isNotEmpty()) <span class="rounded-full bg-aj-error/10 px-2 py-0.5 text-xs font-bold text-aj-error">geschil</span>@endif</td>
                            <td class="tabular">{{ Money::format($order->hammer_cents) }}</td>
                            <td class="tabular">{{ $order->payout ? Money::format($order->payout->fee_cents) : '-' }}</td>
                            <td class="tabular">{{ Money::format($order->total_cents) }}</td>
                            <td><a href="{{ route('admin.lots.dossier', $order->lot) }}" class="btn btn-ghost" style="height:30px;font-size:12px">Dossier</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.admin>
