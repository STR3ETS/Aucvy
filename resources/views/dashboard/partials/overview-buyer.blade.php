@use('App\Enums\OrderStatus')
@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

{{--
    Het koperoverzicht: de lopende biedingen naast de waarde-indicatie van
    Atlas en hoe je kocht. Een range, geen belofte. Wat je volgt en je
    alerts staan op hun eigen pagina in het menu.
--}}
@php
    $displayTimezone = config('aucvy.display_timezone');
    $euro = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.');
    // Zoals in het ontwerp: hele euro's zonder ",00"; centen alleen als ze er zijn.
    $short = fn (int $cents): string => $cents % 100 === 0 ? $euro($cents) : Money::format($cents);
    // De waardebalk: de band van Atlas en het bod als streep, op een as tot 110% van de bovengrens.
    $range = function (int $price, int $min, int $max): array {
        $scale = max(1, (int) round(max($max, $price) * 1.1));

        return [
            'left' => round($min / $scale * 100, 1),
            'width' => round(max(0, $max - $min) / $scale * 100, 1),
            'mark' => min(100, round($price / $scale * 100, 1)),
            'note' => $price < $min ? (int) round(100 - $price / $min * 100).'% onder de waarde-indicatie' : ($price > $max ? 'boven de waarde-indicatie' : 'binnen de waarde-indicatie'),
        ];
    };
@endphp

<section class="d-card" aria-labelledby="bieden-title">
    <div class="d-card-head" style="align-items:center">
        <div class="d-titleline">
            <h2 id="bieden-title" class="d-h2">Lopende biedingen</h2>
            <x-dashboard.help text="Waar je staat en wat de kavel ongeveer waard is. De ring toont het huidige bod als deel van de bovenkant van de waarde-indicatie van Atlas. Dat is een schatting, geen belofte." />
        </div>
        @if ($openBids !== [])
            <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('dashboard.bids') }}">Alle biedingen</a>
        @endif
    </div>

    @if ($openBids === [])
        <p class="feed-empty" style="margin-top:12px">Je doet nu nergens aan mee. <a class="d-link" href="{{ route('lots.index') }}">Bekijk de veilingen</a> en bied op wat je zoekt.</p>
    @else
        {{--
            Twee kaarten naast elkaar. Bovenin over de volle breedte wat het
            is, hoe je ervoor staat en de knop. Daaronder de ring precies in
            het midden (waar het bod zit ten opzichte van de waarde-indicatie
            van Atlas), met links je bod en rechts wat het kost om weer op kop
            te komen en de waarde. Op een smalle kaart staat alles onder elkaar.
        --}}
        <div class="d-bids-wrap">
        <div class="d-bids">
            @foreach ($openBids as $row)
                @php
                    $bid = $row['bid'];
                    $lot = $bid->auction->lot;
                    $auction = $bid->auction;
                    $showRange = $lot->mayShowValueComparison();
                    $bar = $showRange ? $range($row['current_cents'], $lot->value_min_cents, $lot->value_max_cents) : null;
                    // De ring: het bod als deel van de bovenkant van de waarde-indicatie. Boven de 100% is hij vol.
                    $ringPercent = $showRange && $lot->value_max_cents > 0 ? (int) round($row['current_cents'] / $lot->value_max_cents * 100) : null;
                    $ringTone = match (true) {
                        ! $showRange => 'none',
                        $row['current_cents'] > $lot->value_max_cents => 'above',
                        $row['current_cents'] < $lot->value_min_cents => 'below',
                        default => 'within',
                    };
                @endphp
                <div class="d-bid-slot">
                <article class="d-bid">
                    <div class="d-bid-top">
                        <div class="d-bid-head">
                            @if ($lot->media->first())
                                <img src="{{ $lot->media->first()->path }}" alt="">
                            @else
                                <span class="thumb" aria-hidden="true"></span>
                            @endif
                            <div class="text">
                                <strong><a href="{{ route('lots.show', $lot) }}">{{ $lot->title }}</a></strong>
                                <div class="d-bid-tags">
                                    <span class="d-tag {{ $row['winning'] ? 'tone-ok' : 'tone-warn' }}">{{ $row['winning'] ? 'Hoogste bieder' : 'Overboden' }}</span>
                                    @if ($row['interest']['hot'])
                                        <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                    @endif
                                    <span class="d-bid-meta">{{ ucfirst($auction->closesText()) }}</span>
                                </div>
                            </div>
                        </div>
                        {{-- De actie als pictogram: de hamer om opnieuw te bieden, het oog om de kavel te bekijken. --}}
                        @if ($row['winning'])
                            <a class="d-iconbtn d-bid-action" href="{{ route('lots.show', $lot) }}" data-tip="Bekijk kavel" aria-label="Kavel {{ $lot->title }} bekijken"><x-v4.icon name="eye" :size="18" stroke="2" /></a>
                        @else
                            <a class="d-iconbtn is-dark d-bid-action" href="{{ route('lots.show', $lot) }}" data-tip="Bied opnieuw" aria-label="Opnieuw bieden op {{ $lot->title }}"><x-v4.icon name="gavel" :size="18" stroke="2" /></a>
                        @endif
                    </div>

                    <dl class="d-bid-stats is-left">
                        <div class="is-main"><dt>Huidig bod</dt><dd class="tabular">{{ $short($row['current_cents']) }}</dd></div>
                        <div><dt>Jouw bod</dt><dd class="tabular">{{ $short($bid->amount_cents) }}</dd></div>
                    </dl>

                    <div class="d-ring is-{{ $ringTone }}" role="img"
                         aria-label="{{ $bar !== null ? 'Huidig bod is '.$ringPercent.'% van de bovenkant van de waarde-indicatie, '.$bar['note'] : 'Geen waarde-indicatie voor deze kavel' }}">
                        <svg viewBox="0 0 100 100" aria-hidden="true">
                            <circle class="track" cx="50" cy="50" r="41"></circle>
                            @if ($ringPercent !== null)
                                <circle class="value" cx="50" cy="50" r="41" pathLength="100" stroke-dasharray="{{ min(100, max(2, $ringPercent)) }} 100"></circle>
                            @endif
                        </svg>
                        <div class="d-ring-text">
                            @if ($ringPercent !== null)
                                <b class="tabular">{{ $ringPercent }}%</b>
                                <span>van de waarde</span>
                            @else
                                <x-v4.icon name="gavel" :size="22" stroke="2" />
                            @endif
                        </div>
                    </div>

                    <dl class="d-bid-stats is-right">
                        @unless ($row['winning'])
                            <div><dt>Weer op kop vanaf</dt><dd class="tabular">{{ $short($row['minimum_cents']) }}</dd></div>
                        @endunless
                        @if ($bar !== null)
                            <div><dt>Waarde volgens Atlas</dt><dd class="tabular"><span>{{ $euro($lot->value_min_cents) }} –</span> <span>{{ $euro($lot->value_max_cents) }}</span></dd><dd class="d-bid-note is-{{ $ringTone }}">{{ ucfirst($bar['note']) }}</dd></div>
                        @endif
                    </dl>
                </article>
                </div>
            @endforeach
        </div>
        </div>
    @endif
</section>

@if ($purchases->isNotEmpty())
    {{--
        Hoe je kocht: je laatste aankopen in één tabel. Per aankoop de stand
        van de bestelling, wat je bood, wat Atlas de kavel waard vond, de
        uitkomst met dezelfde ring als bij de lopende biedingen (groen
        eronder, blauw erbinnen, oranje erboven) en de weg naar de factuur
        en de bestelling. Een aparte lijst "Gekocht" is daardoor niet nodig.
        Op een smal scherm staat het onder elkaar.
    --}}
    @php
        $toneOf = fn ($order): ?string => match (true) {
            $order->lot === null || ! $order->lot->mayShowValueComparison() => null,
            $order->hammer_cents < $order->lot->value_min_cents => 'below',
            $order->hammer_cents > $order->lot->value_max_cents => 'above',
            default => 'within',
        };
        $verdicts = $purchases->map($toneOf)->filter()->countBy();
        $verdictParts = array_filter([
            $verdicts->get('below', 0) > 0 ? $verdicts->get('below').' eronder' : null,
            $verdicts->get('within', 0) > 0 ? $verdicts->get('within').' erbinnen' : null,
            $verdicts->get('above', 0) > 0 ? $verdicts->get('above').' erboven' : null,
        ]);
    @endphp
    <section class="d-card" aria-labelledby="kocht-title">
        <div class="d-card-head" style="align-items:center">
            <div class="d-titleline">
                <h2 id="kocht-title" class="d-h2">Hoe je kocht</h2>
                <x-dashboard.help text="Je laatste aankopen, met je winnende bod naast de waarde-indicatie van Atlas. De ring toont je bod als deel van de bovenkant van die indicatie. Het is een schatting, geen belofte." />
            </div>
            <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('orders.index') }}">Alle bestellingen</a>
        </div>
        @if ($verdictParts !== [])
            <p class="d-lead" style="margin-top:12px">
                Van je {{ $verdicts->sum() === 1 ? 'laatste aankoop met een waarde-indicatie' : 'laatste '.$verdicts->sum().' aankopen met een waarde-indicatie' }} zat het bod: <b>{{ \Illuminate\Support\Arr::join($verdictParts, ', ', ' en ') }}</b>.
            </p>
        @endif

        <div class="d-bought">
            <div class="d-bought-head" aria-hidden="true">
                <span>Kavel</span>
                <span class="num">Je bod</span>
                <span class="num">Waarde volgens Atlas</span>
                <span>Uitkomst</span>
                <span></span>
            </div>
            @foreach ($purchases as $order)
                @php
                    $lot = $order->lot;
                    $tone = $toneOf($order);
                    $bar = $tone !== null ? $range($order->hammer_cents, $lot->value_min_cents, $lot->value_max_cents) : null;
                    $percent = $tone !== null && $lot->value_max_cents > 0 ? (int) round($order->hammer_cents / $lot->value_max_cents * 100) : 0;
                    $invoice = $order->invoices->first(fn ($invoice) => $invoice->isVisibleTo($user));
                    [$tagTone, $tagLabel] = match ($order->status) {
                        OrderStatus::AwaitingPayment => ['tone-warn', 'Wacht op betaling'],
                        OrderStatus::Disputed => ['tone-warn', 'Geschil in behandeling'],
                        OrderStatus::Completed => ['tone-ok', 'Afgerond'],
                        OrderStatus::Delivered => ['tone-ok', 'Geleverd'],
                        default => ['tone-atlas', $order->status->label()],
                    };
                @endphp
                <article class="d-bought-row">
                    <div class="d-bought-lot">
                        @if ($lot?->media->first())
                            <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                        @else
                            <span class="thumb" aria-hidden="true"></span>
                        @endif
                        <div class="text">
                            <strong><a href="{{ route('orders.show', $order) }}">{{ $lot?->title ?? 'Bestelling '.$order->order_number }}</a></strong>
                            <span class="d-tag {{ $tagTone }}">{{ $tagLabel }}</span>
                        </div>
                    </div>
                    <span class="d-bought-num is-bid"><span class="label">Je bod</span><b class="tabular">{{ $euro($order->hammer_cents) }}</b></span>
                    <span class="d-bought-num is-value"><span class="label">Waarde volgens Atlas</span>
                        @if ($tone !== null)
                            <b class="tabular">{{ $euro($lot->value_min_cents) }} – {{ $euro($lot->value_max_cents) }}</b>
                        @else
                            <span class="d-muted">geen indicatie</span>
                        @endif
                    </span>
                    <span class="d-bought-verdict{{ $tone !== null ? ' is-'.$tone : '' }}">
                        @if ($tone !== null)
                            <span class="d-ring is-sm is-{{ $tone }}" role="img" aria-label="{{ $percent }}% van de bovenkant van de waarde-indicatie">
                                <svg viewBox="0 0 100 100" aria-hidden="true">
                                    <circle class="track" cx="50" cy="50" r="38"></circle>
                                    <circle class="value" cx="50" cy="50" r="38" pathLength="100" stroke-dasharray="{{ min(100, max(4, $percent)) }} 100"></circle>
                                </svg>
                            </span>
                            <span>{{ ucfirst($bar['note']) }}</span>
                        @endif
                    </span>
                    {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                    <span class="d-bought-actions">
                        @if ($invoice)
                            <a class="d-iconbtn" href="{{ route('invoices.show', $invoice) }}" data-tip="Factuur" aria-label="Factuur van {{ $lot?->title ?? $order->order_number }}"><x-v4.icon name="file-text" :size="17" stroke="2" /></a>
                        @endif
                        @if ($order->status === OrderStatus::AwaitingPayment)
                            <a class="d-iconbtn is-dark" href="{{ route('orders.show', $order) }}" data-tip="Betalen" aria-label="{{ $lot?->title ?? $order->order_number }} betalen"><x-v4.icon name="banknote" :size="17" stroke="2" /></a>
                        @elseif ($order->status === OrderStatus::Disputed)
                            <a class="d-iconbtn" href="{{ route('orders.show', $order) }}" data-tip="Bekijk geschil" aria-label="Geschil over {{ $lot?->title ?? $order->order_number }} bekijken"><x-v4.icon name="shield-check" :size="17" stroke="2" /></a>
                        @else
                            <a class="d-iconbtn" href="{{ route('orders.show', $order) }}" data-tip="Bekijk bestelling" aria-label="Bestelling {{ $lot?->title ?? $order->order_number }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                        @endif
                    </span>
                </article>
            @endforeach
        </div>
    </section>
@endif

