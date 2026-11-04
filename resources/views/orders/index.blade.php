@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $toPay = $buying->where('status', OrderStatus::AwaitingPayment);
    $running = $buying->concat($selling)->filter(fn ($order): bool => ! in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true));
    $completed = $buying->concat($selling)->where('status', OrderStatus::Completed)->count();
    $toneOf = fn (OrderStatus $status): string => match ($status) {
        OrderStatus::AwaitingPayment => 'tone-warn',
        OrderStatus::Disputed => 'tone-live',
        OrderStatus::Completed, OrderStatus::Delivered => 'tone-ok',
        OrderStatus::Cancelled => 'tone-grey',
        default => 'tone-atlas',
    };
@endphp

<x-layouts.dashboard title="Bestellingen" eyebrow="Dashboard" intro="Alles wat je kocht of verkocht, van betaling tot uitbetaling.">
    <x-slot:tiles>
        <x-dashboard.tile tone="orange" label="Te betalen" :value="Money::format((int) $toPay->sum('total_cents'))" :sub="$toPay->count().' '.($toPay->count() === 1 ? 'bestelling wacht op betaling' : 'bestellingen wachten op betaling')" />
        <x-dashboard.tile tone="blue" label="Gekocht" :value="$buying->count()" sub="bestellingen als koper" />
        <x-dashboard.tile tone="green" label="Verkocht" :value="$selling->count()" sub="bestellingen als verkoper" :href="auth()->user()->canSeeCompanyFinance() ? route('dashboard.payouts') : null" />
        <x-dashboard.tile tone="purple" label="Lopend" :value="$running->count()" :sub="$completed.' afgerond'" />
    </x-slot:tiles>

    <div class="d-stack" style="gap:18px">
        @foreach ([['Gekocht', $buying, false], ['Verkocht', $selling, true]] as [$heading, $orders, $asSeller])
            <section aria-label="{{ $heading }}">
                <h2 class="d-section-title">{{ $heading }}</h2>

                @if ($orders->isEmpty())
                    <p class="feed-empty" style="margin:0">{{ $asSeller ? 'Nog niets verkocht.' : 'Nog niets gekocht.' }}</p>
                @else
                    <div class="d-stack-tight">
                        @foreach ($orders as $order)
                            @php
                                $lot = $order->lot;
                                $meta = ['Bestelling '.$order->order_number];
                                if ($order->status === OrderStatus::AwaitingPayment && $order->payment_deadline_at) {
                                    $meta[] = 'betalen vóór '.$order->payment_deadline_at->timezone($displayTimezone)->isoFormat('D MMM, HH:mm');
                                } elseif ($order->delivered_at) {
                                    $meta[] = 'geleverd op '.$order->delivered_at->timezone($displayTimezone)->isoFormat('D MMM');
                                } elseif ($order->paid_at) {
                                    $meta[] = 'betaald op '.$order->paid_at->timezone($displayTimezone)->isoFormat('D MMM');
                                }
                            @endphp
                            <article class="d-listrow">
                                @if ($lot?->media->first())
                                    <img class="d-listrow-thumb is-sm" src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                                @else
                                    <span class="d-listrow-thumb is-sm" aria-hidden="true"></span>
                                @endif
                                <div class="d-listrow-body">
                                    <strong><a href="{{ route('orders.show', $order) }}">{{ $lot?->title ?? 'Bestelling '.$order->order_number }}</a></strong>
                                    <span class="d-listrow-meta">{{ implode(' · ', $meta) }}</span>
                                    <div class="d-listrow-tags"><span class="d-tag {{ $toneOf($order->status) }}">{{ $order->status->label() }}</span></div>
                                </div>
                                <div class="d-listrow-right">
                                    <b>{{ Money::format($asSeller ? $order->hammer_cents : $order->total_cents) }}</b>
                                    <span>{{ $asSeller ? 'hamerprijs' : 'totaal' }}</span>
                                    <a href="{{ route('orders.show', $order) }}" class="d-btn {{ ! $asSeller && $order->status === OrderStatus::AwaitingPayment ? '' : 'd-btn-secondary' }} d-btn-sm d-btn-sq">{{ ! $asSeller && $order->status === OrderStatus::AwaitingPayment ? 'Betalen' : 'Bekijk' }}</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.dashboard>
