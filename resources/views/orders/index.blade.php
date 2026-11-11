@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $user = auth()->user();
    $seesFinance = $user->canSeeCompanyFinance();
    $isOpen = fn ($order): bool => ! in_array($order->status, [OrderStatus::Completed, OrderStatus::Cancelled], true);
    $toPay = $buying->where('status', OrderStatus::AwaitingPayment)->values();
    $running = $buying->concat($selling)->filter($isOpen);
    $completed = $buying->concat($selling)->where('status', OrderStatus::Completed)->count();
    $toneOf = fn (OrderStatus $status): string => match ($status) {
        OrderStatus::AwaitingPayment => 'tone-warn',
        OrderStatus::Disputed => 'tone-live',
        OrderStatus::Completed, OrderStatus::Delivered => 'tone-ok',
        OrderStatus::Cancelled => 'tone-grey',
        default => 'tone-atlas',
    };

    // Per stand een eigen tabel, en wat je kocht apart van wat je verkocht:
    // een bod onder de waarde is voor een koper goed nieuws, voor een verkoper niet.
    $buyerColumns = ['hammer', 'total', 'value', 'verdict'];
    $sellerColumns = $seesFinance ? ['hammer', 'payout', 'value', 'verdict'] : ['hammer', 'value', 'verdict'];
    $tableSpecs = [
        'betalen' => ['Te betalen', 'Wat je won of direct kocht en nog moet betalen, met de betaaltermijn. Na betaling plan je het ophalen of de levering.', false, $buyerColumns, $toPay],
        'gekocht-lopend' => ['Gekocht, lopend', 'Betaald en nog niet afgerond: onderweg, klaar om op te halen of geleverd. Je bod staat naast de waarde-indicatie van Atlas.', false, $buyerColumns, $buying->filter(fn ($order): bool => $isOpen($order) && $order->status !== OrderStatus::AwaitingPayment)->values()],
        'verkocht-lopend' => ['Verkocht, lopend', 'Kavels met een koper die nog niet zijn afgerond, met de hamerprijs naast de waarde-indicatie van Atlas.', true, $sellerColumns, $selling->filter($isOpen)->values()],
        'gekocht-klaar' => ['Gekocht, afgerond', 'Aankopen die klaar zijn, met de factuur erbij.', false, $buyerColumns, $buying->reject($isOpen)->values()],
        'verkocht-klaar' => ['Verkocht, afgerond', 'Verkopen die klaar zijn, met de factuur erbij.', true, $sellerColumns, $selling->reject($isOpen)->values()],
    ];

    // De tegels zijn het filter (?toon=). Zonder filter staan alle tabellen onder elkaar.
    $filters = [
        'betalen' => ['betalen'],
        'gekocht' => ['betalen', 'gekocht-lopend', 'gekocht-klaar'],
        'verkocht' => ['verkocht-lopend', 'verkocht-klaar'],
        'lopend' => ['betalen', 'gekocht-lopend', 'verkocht-lopend'],
    ];
    $filter = array_key_exists((string) request('toon'), $filters) ? (string) request('toon') : 'alle';
    $filterUrl = fn (string $key): string => route('orders.index', $filter === $key ? [] : ['toon' => $key]);
    $tables = collect($tableSpecs)
        ->filter(fn (array $spec, string $key): bool => $filter === 'alle' || in_array($key, $filters[$filter], true))
        ->map(fn (array $spec): array => ['title' => $spec[0], 'help' => $spec[1], 'asSeller' => $spec[2], 'columns' => $spec[3], 'orders' => $spec[4]])
        ->filter(fn (array $table): bool => $table['orders']->isNotEmpty());

    $widths = ['hammer' => '110px', 'total' => '120px', 'payout' => '150px', 'value' => '180px', 'verdict' => '240px'];
    $euro = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.');
    // Hele euro's zonder ",00"; centen alleen als ze er zijn.
    $short = fn (int $cents): string => $cents % 100 === 0 ? $euro($cents) : Money::format($cents);
    // Eén tabel in beeld toont tien rijen, meerdere tabellen elk vijf.
    $listLimit = $tables->count() === 1 ? 10 : 5;
    // Eén donkere knop: de eerste bestelling die op betaling wacht.
    $firstUnpaid = $toPay->first()?->id;
    $payoutsLinkShown = false;
@endphp

{{--
    Bestellingen, net als Mijn kavels: per stand een eigen tabel in een eigen
    kaart, de koppen één keer, de waarde volgens Atlas erbij en de acties als
    pictogram. Voor een koper is een bod onder de waarde groen, voor een
    verkoper juist een bod erboven.
--}}
<x-layouts.dashboard title="Bestellingen" eyebrow="Dashboard" intro="Alles wat je kocht of verkocht, van betaling tot uitbetaling.">
    <x-slot:tiles>
        <x-dashboard.tile tone="orange" label="Te betalen" :value="Money::format((int) $toPay->sum('total_cents'))" :sub="$toPay->count().' '.($toPay->count() === 1 ? 'bestelling wacht op betaling' : 'bestellingen wachten op betaling')" :href="$filterUrl('betalen')" :active="$filter === 'betalen'" />
        <x-dashboard.tile tone="blue" label="Gekocht" :value="$buying->count()" sub="bestellingen als koper" :href="$filterUrl('gekocht')" :active="$filter === 'gekocht'" />
        <x-dashboard.tile tone="green" label="Verkocht" :value="$selling->count()" sub="bestellingen als verkoper" :href="$filterUrl('verkocht')" :active="$filter === 'verkocht'" />
        <x-dashboard.tile tone="purple" label="Lopend" :value="$running->count()" :sub="$completed.' afgerond'" :href="$filterUrl('lopend')" :active="$filter === 'lopend'" />
    </x-slot:tiles>

    @if ($filter !== 'alle')
        <div class="d-listbar">
            <p class="d-listbar-count">
                <b class="tabular">{{ $tables->sum(fn (array $table): int => $table['orders']->count()) }}</b> {{ ['betalen' => 'te betalen', 'gekocht' => 'gekocht', 'verkocht' => 'verkocht', 'lopend' => 'lopend'][$filter] }}
                <a class="d-link" href="{{ route('orders.index') }}">Alle {{ $buying->count() + $selling->count() }} tonen</a>
            </p>
        </div>
    @endif

    @if ($tables->isEmpty())
        <p class="feed-empty" style="margin:0">{{ match ($filter) {
            'betalen' => 'Niets te betalen. Wat je wint of direct koopt, staat hier met de betaaltermijn.',
            'verkocht' => 'Nog niets verkocht. Zodra een kavel een koper heeft, staat de bestelling hier.',
            'lopend' => 'Er loopt nu niets. Alles is afgerond.',
            'gekocht' => 'Nog niets gekocht. Wat je wint of direct koopt, staat hier met factuur en levering.',
            default => 'Nog geen bestellingen. Wat je koopt of verkoopt, staat hier met factuur en levering.',
        } }}</p>
        @if ($filter === 'verkocht')
            <div style="margin-top:12px"><a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('sell') }}">Kavel plaatsen</a></div>
        @elseif ($filter !== 'lopend')
            <div style="margin-top:12px"><a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('lots.index') }}">Bekijk de veilingen</a></div>
        @endif
    @endif

    <div class="d-sections d-stack">
        @foreach ($tables as $key => $table)
            @php
                $columns = $table['columns'];
                $asSeller = $table['asSeller'];
                $labels = ['hammer' => $asSeller ? 'Hamerprijs' : 'Je bod', 'total' => 'Totaal', 'payout' => 'Uitbetaling', 'value' => 'Waarde volgens Atlas', 'verdict' => 'Uitkomst'];
                $template = 'minmax(0,1fr) '.collect($columns)->map(fn (string $column): string => $widths[$column])->implode(' ').' 86px';
                $showPayoutsLink = $asSeller && $seesFinance && ! $payoutsLinkShown;
                $payoutsLinkShown = $payoutsLinkShown || $showPayoutsLink;
            @endphp
            <section class="d-card" aria-labelledby="bestellingen-{{ $key }}" x-data="{ all: false }">
                <div class="d-card-head" style="align-items:center;margin-bottom:12px">
                    <div class="d-titleline">
                        <h2 id="bestellingen-{{ $key }}" class="d-h2">{{ $table['title'] }}</h2>
                        <span class="d-count tabular">{{ $table['orders']->count() }}</span>
                        <x-dashboard.help :text="$table['help']" />
                    </div>
                    @if ($showPayoutsLink)
                        <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('dashboard.payouts') }}">Naar uitbetalingen</a>
                    @endif
                </div>

                <div @class(['d-lottable', 'is-wide' => count($columns) > 3]) style="--cols:{{ $template }}">
                    <div class="d-lottable-head" aria-hidden="true">
                        <span>Bestelling</span>
                        @foreach ($columns as $column)
                            <span @class(['num' => $column !== 'verdict'])>{{ $labels[$column] }}</span>
                        @endforeach
                        <span></span>
                    </div>

                    @foreach ($table['orders'] as $order)
                        @php
                            $lot = $order->lot;
                            $name = $lot?->title ?? 'Bestelling '.$order->order_number;
                            $meta = ['Bestelling '.$order->order_number];
                            if ($order->status === OrderStatus::AwaitingPayment && $order->payment_deadline_at) {
                                $meta[] = 'betalen vóór '.$order->payment_deadline_at->timezone($displayTimezone)->isoFormat('D MMM, HH:mm');
                            } elseif ($order->delivered_at) {
                                $meta[] = 'geleverd op '.rtrim($order->delivered_at->timezone($displayTimezone)->isoFormat('D MMM'), '.');
                            } elseif ($order->paid_at) {
                                $meta[] = 'betaald op '.rtrim($order->paid_at->timezone($displayTimezone)->isoFormat('D MMM'), '.');
                            }
                            // De factuur hoort bij deze bestelling; zo wordt de bestelling niet per factuur opnieuw opgehaald.
                            $order->invoices->each(fn ($invoice) => $invoice->setRelation("order", $order));
                            $invoice = $order->invoices->first(fn ($invoice) => $invoice->isVisibleTo($user));
                            // De uitkomst: de hamerprijs naast de waarde-indicatie, alleen bij een scherpe schatting.
                            // De kleur volgt wie je bent: koper groen eronder, verkoper groen erboven.
                            $verdict = null;
                            if ($lot !== null && $lot->mayShowValueComparison() && $lot->value_max_cents > 0) {
                                $under = (int) round(100 - $order->hammer_cents / max(1, $lot->value_min_cents) * 100).'% onder de waarde-indicatie';
                                $verdict = match (true) {
                                    $order->hammer_cents < $lot->value_min_cents => [$asSeller ? 'above' : 'below', $under],
                                    $order->hammer_cents > $lot->value_max_cents => [$asSeller ? 'below' : 'above', 'Boven de waarde-indicatie'],
                                    default => ['within', 'Binnen de waarde-indicatie'],
                                };
                                $verdictPercent = (int) round($order->hammer_cents / $lot->value_max_cents * 100);
                            }
                        @endphp
                        <article class="d-lotrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                            <div class="d-lotrow-lot">
                                <a class="thumb" href="{{ route('orders.show', $order) }}" tabindex="-1" aria-hidden="true">
                                    @if ($lot?->media->first())
                                        <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                                    @else
                                        <x-v4.icon name="image" :size="20" stroke="1.6" />
                                    @endif
                                </a>
                                <div class="text">
                                    <strong><a href="{{ route('orders.show', $order) }}">{{ $name }}</a></strong>
                                    <span class="d-lotrow-tags">
                                        <span class="d-tag {{ $toneOf($order->status) }}">{{ $order->status->label() }}</span>
                                        <span class="d-bid-meta">{{ implode(' · ', $meta) }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="d-lotrow-facts">
                                @foreach ($columns as $column)
                                    @if ($column === 'hammer')
                                        <span class="cell is-value"><span class="label">{{ $labels['hammer'] }}</span><b class="tabular">{{ $short($order->hammer_cents) }}</b></span>
                                    @elseif ($column === 'total')
                                        <span class="cell is-value"><span class="label">Totaal</span><b class="tabular">{{ Money::format($order->total_cents) }}</b></span>
                                    @elseif ($column === 'payout')
                                        <span class="cell is-value">
                                            <span class="label">Uitbetaling</span>
                                            @if ($order->payout !== null)
                                                <b class="tabular">{{ Money::format($order->payout->amount_cents) }}</b>
                                                <span class="d-muted" style="display:block;font-size:12px">{{ mb_strtolower($order->payout->status->label()) }}</span>
                                            @else
                                                <span class="d-muted">nog niet</span>
                                            @endif
                                        </span>
                                    @elseif ($column === 'value')
                                        <span class="cell is-value">
                                            <span class="label">Waarde volgens Atlas</span>
                                            @if ($lot?->hasValueEstimate())
                                                <b class="tabular">{{ $euro($lot->value_min_cents) }} – {{ $euro($lot->value_max_cents) }}</b>
                                            @else
                                                <span class="d-muted">geen indicatie</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="cell d-bought-verdict{{ $verdict !== null ? ' is-'.$verdict[0] : '' }}">
                                            @if ($verdict !== null)
                                                <span class="d-ring is-sm is-{{ $verdict[0] }}" role="img" aria-label="{{ $verdictPercent }}% van de bovenkant van de waarde-indicatie">
                                                    <svg viewBox="0 0 100 100" aria-hidden="true">
                                                        <circle class="track" cx="50" cy="50" r="38"></circle>
                                                        <circle class="value" cx="50" cy="50" r="38" pathLength="100" stroke-dasharray="{{ min(100, max(4, $verdictPercent)) }} 100"></circle>
                                                    </svg>
                                                </span>
                                                <span>{{ $verdict[1] }}</span>
                                            @endif
                                        </span>
                                    @endif
                                @endforeach
                            </div>

                            {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                            <div class="d-lotrow-actions">
                                @if ($invoice)
                                    <a href="{{ route('invoices.show', $invoice) }}" class="d-iconbtn" data-tip="Factuur" aria-label="Factuur van {{ $name }}"><x-v4.icon name="file-text" :size="17" stroke="2" /></a>
                                @endif
                                @if (! $asSeller && $order->status === OrderStatus::AwaitingPayment)
                                    <a href="{{ route('orders.show', $order) }}" class="d-iconbtn{{ $order->id === $firstUnpaid ? ' is-dark' : '' }}" data-tip="Betalen" aria-label="{{ $name }} betalen"><x-v4.icon name="banknote" :size="17" stroke="2" /></a>
                                @elseif ($order->status === OrderStatus::Disputed)
                                    <a href="{{ route('orders.show', $order) }}" class="d-iconbtn" data-tip="Bekijk geschil" aria-label="Geschil over {{ $name }} bekijken"><x-v4.icon name="shield-check" :size="17" stroke="2" /></a>
                                @else
                                    <a href="{{ route('orders.show', $order) }}" class="d-iconbtn" data-tip="Bekijk bestelling" aria-label="Bestelling {{ $name }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <x-dashboard.more :total="$table['orders']->count()" :limit="$listLimit" />
            </section>
        @endforeach
    </div>
</x-layouts.dashboard>
