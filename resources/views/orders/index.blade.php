@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

@php
    $toPay = $buying->where('status', OrderStatus::AwaitingPayment);
    $running = $buying->concat($selling)->filter(fn ($order): bool => ! in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true));
    $completed = $buying->concat($selling)->where('status', OrderStatus::Completed)->count();
@endphp

<x-layouts.dashboard title="Bestellingen" eyebrow="Dashboard" intro="Alles wat je kocht of verkocht, van betaling tot uitbetaling.">
    <x-slot:tiles>
        <x-dashboard.tile tone="orange" icon="clock" label="Te betalen" :value="Money::format((int) $toPay->sum('total_cents'))" :sub="$toPay->count().' '.($toPay->count() === 1 ? 'bestelling wacht op betaling' : 'bestellingen wachten op betaling')" />
        <x-dashboard.tile tone="blue" icon="shopping-bag" label="Gekocht" :value="$buying->count()" sub="bestellingen als koper" />
        <x-dashboard.tile tone="green" icon="banknote" label="Verkocht" :value="$selling->count()" sub="bestellingen als verkoper" :href="auth()->user()->canSeeCompanyFinance() ? route('dashboard.payouts') : null" />
        <x-dashboard.tile tone="purple" icon="badge-check" label="Lopend" :value="$running->count()" :sub="$completed.' afgerond'" />
    </x-slot:tiles>
    <div>

        @foreach ([['Gekocht', $buying, false], ['Verkocht', $selling, true]] as [$heading, $orders, $asSeller])
            <section class="{{ $loop->first ? '' : 'mt-8' }}" aria-label="{{ $heading }}">
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
                                   class="flex flex-wrap items-center justify-between gap-3 rounded-aj-s border border-aj-border bg-aj-surface p-4 hover:border-aj-border-strong">
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
</x-layouts.dashboard>
