@use('App\Support\Money\Money')

<x-layouts.admin title="Overzicht">
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($stats as [$label, $value, $url])
            <a href="{{ $url }}" class="rounded-aj-s border border-aj-border bg-aj-surface p-4 hover:border-aj-border-strong">
                <p class="text-xs font-bold text-aj-muted">{{ $label }}</p>
                <p class="tabular mt-1 text-2xl font-extrabold">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <section class="mt-8" aria-label="Recente orders">
        <h2 class="text-lg font-extrabold">Recente orders</h2>
        <x-admin.table>
            <x-slot:head>
                <th>Nummer</th><th>Kavel</th><th>Koper</th><th>Status</th><th class="text-right">Totaal</th>
            </x-slot:head>
            @foreach ($recentOrders as $order)
                <tr>
                    <td class="tabular font-semibold">{{ $order->order_number }}</td>
                    <td class="max-w-[26ch] truncate">{{ $order->lot->title }}</td>
                    <td>{{ $order->buyer->name }}</td>
                    <td>{{ $order->status->label() }}</td>
                    <td class="tabular text-right font-semibold">{{ Money::format($order->total_cents) }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </section>
</x-layouts.admin>
