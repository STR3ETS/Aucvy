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

    // De tegels zijn het filter (?toon=): één lijst tegelijk in plaats van twee onder elkaar.
    $isOpen = fn ($order): bool => ! in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true);
    $views = [
        'betalen' => ['Te betalen', $toPay->map(fn ($order): array => [$order, false])],
        'gekocht' => ['Gekocht', $buying->map(fn ($order): array => [$order, false])],
        'verkocht' => ['Verkocht', $selling->map(fn ($order): array => [$order, true])],
        'lopend' => ['Lopend', $buying->filter($isOpen)->map(fn ($order): array => [$order, false])->concat($selling->filter($isOpen)->map(fn ($order): array => [$order, true]))],
    ];
    // Zonder keuze: wat je kocht, of wat je verkocht als je niets kocht.
    $view = array_key_exists((string) request('toon'), $views) ? (string) request('toon') : ($buying->isEmpty() && $selling->isNotEmpty() ? 'verkocht' : 'gekocht');
    [$heading, $orders] = $views[$view];
    $orders = $orders->values();
    $viewUrl = fn (string $key): string => route('orders.index', ['toon' => $key]);
    $listLimit = 10;
    // Eén blauwe knop: de eerste bestelling die op betaling wacht.
    $firstUnpaid = $orders->first(fn (array $row): bool => ! $row[1] && $row[0]->status === OrderStatus::AwaitingPayment)[0]->id ?? null;
@endphp

<x-layouts.dashboard title="Bestellingen" eyebrow="Dashboard" intro="Alles wat je kocht of verkocht, van betaling tot uitbetaling.">
    <x-slot:tiles>
        <x-dashboard.tile tone="orange" label="Te betalen" :value="Money::format((int) $toPay->sum('total_cents'))" :sub="$toPay->count().' '.($toPay->count() === 1 ? 'bestelling wacht op betaling' : 'bestellingen wachten op betaling')" :href="$viewUrl('betalen')" :active="$view === 'betalen'" />
        <x-dashboard.tile tone="blue" label="Gekocht" :value="$buying->count()" sub="bestellingen als koper" :href="$viewUrl('gekocht')" :active="$view === 'gekocht'" />
        <x-dashboard.tile tone="green" label="Verkocht" :value="$selling->count()" sub="bestellingen als verkoper" :href="$viewUrl('verkocht')" :active="$view === 'verkocht'" />
        <x-dashboard.tile tone="purple" label="Lopend" :value="$running->count()" :sub="$completed.' afgerond'" :href="$viewUrl('lopend')" :active="$view === 'lopend'" />
    </x-slot:tiles>

    <div class="d-stack d-sections">
            {{-- De eerste tien staan open, de rest achter "Meer tonen". --}}
            <section aria-label="{{ $heading }}" x-data="{ all: false }">
                <div class="d-listbar">
                    <h2 class="d-section-title" style="margin:0;flex:1 1 auto">{{ $heading }}</h2>
                    @if ($view === 'verkocht' && auth()->user()->canSeeCompanyFinance())
                        <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('dashboard.payouts') }}">Naar uitbetalingen</a>
                    @endif
                </div>

                @if ($orders->isEmpty())
                    <p class="feed-empty" style="margin:0">{{ match ($view) {
                        'betalen' => 'Niets te betalen. Wat je wint of direct koopt, staat hier met de betaaltermijn.',
                        'verkocht' => 'Nog niets verkocht. Zodra een kavel een koper heeft, staat de bestelling hier.',
                        'lopend' => 'Er loopt nu niets. Alles is afgerond.',
                        default => 'Nog niets gekocht. Wat je wint of direct koopt, staat hier met factuur en levering.',
                    } }}</p>
                    @if (in_array($view, ['gekocht', 'betalen'], true))
                        <div style="margin-top:12px"><a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('lots.index') }}">Bekijk de veilingen</a></div>
                    @elseif ($view === 'verkocht')
                        <div style="margin-top:12px"><a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('sell') }}">Kavel plaatsen</a></div>
                    @endif
                @else
                    <div class="d-stack-tight">
                        @foreach ($orders as [$order, $asSeller])
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
                            <article class="d-listrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
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
                                    <a href="{{ route('orders.show', $order) }}" class="d-btn {{ $order->id === $firstUnpaid && ! $asSeller ? '' : 'd-btn-secondary' }} d-btn-sm d-btn-sq">{{ ! $asSeller && $order->status === OrderStatus::AwaitingPayment ? 'Betalen' : 'Bekijk' }}</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <x-dashboard.more :total="$orders->count()" :limit="$listLimit" />
                @endif
            </section>
    </div>
</x-layouts.dashboard>
