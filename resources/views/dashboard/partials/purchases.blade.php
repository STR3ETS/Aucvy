@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

{{--
    Gekocht: de laatste bestellingen van de koper met factuur en de
    volgende stap (betalen, bekijken of het geschil volgen).
--}}
@php $displayTimezone = config('aucvy.display_timezone'); @endphp

<section class="dash-section" aria-labelledby="gekocht-title">
    <h2 id="gekocht-title" class="d-h2" style="margin-bottom:10px">Gekocht</h2>
    @if ($purchases->isEmpty())
        <p class="feed-empty" style="margin:0">Nog niets gekocht. Wat je wint of direct koopt, staat hier met factuur en levering.</p>
    @else
        <div class="d-stack-tight">
            @foreach ($purchases as $order)
                @php
                    $lot = $order->lot;
                    $invoice = $order->invoices->first(fn ($invoice) => $invoice->isVisibleTo($user));
                    [$tagTone, $tagLabel] = match ($order->status) {
                        OrderStatus::AwaitingPayment => ['tone-warn', 'Wacht op betaling'],
                        OrderStatus::Disputed => ['tone-warn', 'Geschil in behandeling'],
                        OrderStatus::Completed => ['tone-ok', 'Afgerond'],
                        OrderStatus::Delivered => ['tone-ok', 'Geleverd'],
                        default => ['tone-atlas', $order->status->label()],
                    };
                @endphp
                <article class="d-lot">
                    <div class="d-lot-row">
                        @if ($lot?->media->first())
                            <img class="d-lot-thumb" src="{{ $lot->media->first()->path }}" alt="">
                        @else
                            <span class="d-lot-thumb" aria-hidden="true"></span>
                        @endif
                        <div class="d-lot-body">
                            <div class="d-lot-title">
                                <strong><a href="{{ route('orders.show', $order) }}">{{ $lot?->title ?? 'Bestelling '.$order->order_number }}</a></strong>
                                <span class="d-tag {{ $tagTone }}">{{ $tagLabel }}</span>
                            </div>
                            <div class="d-lot-meta">Bestelling {{ $order->order_number }}{{ $order->delivered_at ? ' · geleverd op '.$order->delivered_at->timezone($displayTimezone)->isoFormat('D MMM') : ($order->payment_deadline_at && $order->status === OrderStatus::AwaitingPayment ? ' · betalen vóór '.$order->payment_deadline_at->timezone($displayTimezone)->isoFormat('D MMM, HH:mm') : '') }}</div>
                        </div>
                        <div class="d-lot-price">
                            <div style="font-size:18px;font-weight:800">{{ Money::format($order->total_cents) }}</div>
                        </div>
                        @if ($invoice)
                            <a class="d-link" href="{{ route('invoices.show', $invoice) }}">Factuur</a>
                        @endif
                        @if ($order->status === OrderStatus::AwaitingPayment)
                            <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('orders.show', $order) }}">Betalen</a>
                        @elseif ($order->status === OrderStatus::Disputed)
                            <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('orders.show', $order) }}">Bekijk geschil</a>
                        @else
                            <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('orders.show', $order) }}">Bekijk</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <a class="d-link" style="display:inline-block;margin-top:12px;min-height:44px;line-height:44px" href="{{ route('orders.index') }}">Alle bestellingen</a>
    @endif
</section>
