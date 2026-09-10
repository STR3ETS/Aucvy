<x-layouts.public title="Bestellingen">
    <div class="mx-auto max-w-4xl">
        <h1 class="text-3xl font-extrabold tracking-[-0.03em]">Bestellingen</h1>
        <p class="mt-1 text-aj-muted">Alles wat je kocht of verkocht, van betaling tot uitbetaling.</p>

        @foreach ([['Gekocht', $buying, false], ['Verkocht', $selling, true]] as [$heading, $orders, $asSeller])
            <section class="mt-8" aria-label="{{ $heading }}">
                <h2 class="text-lg font-extrabold">{{ $heading }}</h2>

                @if ($orders->isEmpty())
                    <p class="mt-2 text-sm text-aj-muted">
                        {{ $asSeller ? 'Nog niets verkocht.' : 'Nog niets gekocht.' }}
                    </p>
                @else
                    <ul class="mt-3 space-y-2">
                        @foreach ($orders as $order)
                            <li>
                                <a href="{{ route('orders.show', $order) }}"
                                   class="flex flex-wrap items-center justify-between gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-4 hover:border-aj-border-strong">
                                    <span>
                                        <span class="block font-bold">{{ $order->lot->title }}</span>
                                        <span class="text-xs text-aj-muted">{{ $order->order_number }}</span>
                                    </span>
                                    <span class="text-right">
                                        <span class="tabular block font-extrabold">
                                            {{ \App\Support\Money\Money::format($order->total_cents) }}
                                        </span>
                                        <span class="text-xs text-aj-muted">{{ $order->status->label() }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.public>
