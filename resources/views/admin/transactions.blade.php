@use('App\Support\Money\Money')

<x-layouts.admin title="Transacties" :subtitle="$orders->total().' bestellingen, met status, fee en dossier'">
    <form method="GET" class="mt-6 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek op bestelling of kavel" class="d-input" style="min-height:40px">
        <select name="status" class="d-select" style="min-height:40px">
            <option value="">Alle statussen</option>
            @foreach ($statuses as $option)<option value="{{ $option->value }}" @selected($status === $option->value)>{{ $option->label() }}</option>@endforeach
        </select>
        <button type="submit" class="d-btn d-btn-dark d-btn-sq" style="height:40px;font-size:13px">Filter</button>
    </form>

    @if ($orders->isEmpty())
        <p class="mt-4 d-card d-admin-empty">Geen transacties gevonden.</p>
    @else
        <div class="mt-4 overflow-x-auto d-card">
            <table class="admin-table">
                <thead><tr><th>Bestelling</th><th>Kavel</th><th>Partijen</th><th>Status</th><th>Hamerbedrag</th><th>Fee</th><th>Totaal koper</th><th></th></tr></thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="whitespace-nowrap"><a href="{{ route('admin.orders.show', $order) }}" class="d-tablelink">{{ $order->order_number }}</a><span class="block text-xs text-aj-muted">{{ $order->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') }}</span></td>
                            <td>{{ $order->lot?->title }}</td>
                            <td class="text-xs">{{ $order->seller?->company?->name ?? $order->seller?->name }} aan {{ $order->buyer?->publicName() }}</td>
                            <td>{{ $order->status->label() }}@if ($order->disputes->isNotEmpty()) <span class="rounded-full bg-aj-error/10 px-2 py-0.5 text-xs font-bold text-aj-error">geschil</span>@endif</td>
                            <td class="tabular">{{ Money::format($order->hammer_cents) }}</td>
                            <td class="tabular">{{ $order->payout ? Money::format($order->payout->fee_cents) : '-' }}</td>
                            <td class="tabular">{{ Money::format($order->total_cents) }}</td>
                            <td><a href="{{ route('admin.lots.dossier', $order->lot) }}" class="d-iconbtn" data-tip="Open dossier" aria-label="Open dossier"><x-v4.icon name="file-text" :size="17" stroke="2" /></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
</x-layouts.admin>
