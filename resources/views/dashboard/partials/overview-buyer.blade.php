@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

{{--
    Het koperoverzicht uit het ontwerp van november: de lopende biedingen
    naast de waarde-indicatie van Atlas, wat je volgt, hoe je kocht en de
    alerts. Een range, geen belofte.
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
    $bought = $purchases->filter(fn ($order) => $order->lot !== null && $order->lot->mayShowValueComparison());
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
            Twee kaarten naast elkaar. Per kavel links de ring (waar het bod
            zit ten opzichte van de waarde-indicatie van Atlas), daarnaast
            wat het is en hoe je ervoor staat, de bedragen als rij cijfers
            en rechts één knop. Op een smalle kaart staat alles onder elkaar.
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
                                <span class="d-bid-meta">Sluit {{ $auction->ends_at->timezone($displayTimezone)->diffForHumans() }}</span>
                            </div>
                        </div>
                    </div>

                    <dl class="d-bid-stats">
                        <div class="is-main"><dt>Huidig bod</dt><dd class="tabular">{{ $short($row['current_cents']) }}</dd></div>
                        <div><dt>Jouw bod</dt><dd class="tabular">{{ $short($bid->amount_cents) }}</dd></div>
                        @unless ($row['winning'])
                            <div><dt>Weer op kop vanaf</dt><dd class="tabular">{{ $short($row['minimum_cents']) }}</dd></div>
                        @endunless
                        @if ($bar !== null)
                            <div class="is-wide"><dt>Waarde volgens Atlas</dt><dd class="tabular">{{ $euro($lot->value_min_cents) }} – {{ $euro($lot->value_max_cents) }}</dd><dd class="d-bid-note is-{{ $ringTone }}">{{ ucfirst($bar['note']) }}</dd></div>
                        @endif
                    </dl>

                    <a class="d-btn d-btn-sm d-bid-action {{ $row['winning'] ? 'd-btn-secondary' : 'd-btn-dark' }}" href="{{ route('lots.show', $lot) }}">{{ $row['winning'] ? 'Bekijk kavel' : 'Bied opnieuw' }}</a>
                </article>
                </div>
            @endforeach
        </div>
        </div>
    @endif
</section>

<section class="d-card" aria-labelledby="volgen-title">
    <div class="d-card-head" style="align-items:flex-end">
        <div style="flex:1 1 240px">
            <div class="d-titleline">
                <h2 id="volgen-title" class="d-h2">Wat je volgt</h2>
                <x-dashboard.help text="Zie meteen wat hot is en wat bijna sluit." />
            </div>
        </div>
    </div>
    @if ($watching->isEmpty())
        <p class="feed-empty" style="margin-top:12px">Je volgt nog niets. Zet een kavel op je volglijst en je ziet hier wanneer hij sluit.</p>
    @else
        <div class="d-stack-tight" style="margin-top:12px">
            @foreach ($watching as $item)
                @php
                    $lot = $item->lot;
                    $auction = $lot->auction;
                    $stats = \App\Lots\Interest\Interest::forBuyer($lot);
                @endphp
                <article class="d-task" style="padding:10px 12px">
                    <div class="d-lot-row" style="gap:10px">
                        @if ($lot->media->first())
                            <img class="d-lot-thumb" src="{{ $lot->media->first()->path }}" alt="">
                        @else
                            <span class="d-lot-thumb" aria-hidden="true"></span>
                        @endif
                        <div class="d-lot-body" style="flex-basis:200px">
                            <div class="d-lot-title">
                                <strong style="font-size:14px"><a href="{{ route('lots.show', $lot) }}">{{ $lot->title }}</a></strong>
                                @if ($lot->is_hot)
                                    <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                @endif
                            </div>
                            <div class="d-counts" style="margin-top:2px">
                                <span title="kijkers"><x-v4.icon name="eye" :size="14" stroke="2" />{{ number_format($stats['viewers'], 0, ',', '.') }}</span>
                                <span title="keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" />{{ $stats['saved'] }}</span>
                                <span title="keer gedeeld"><x-v4.icon name="share" :size="14" stroke="2" />{{ $stats['shared'] }}</span>
                            </div>
                        </div>
                        <div class="d-lot-price">
                            <div style="font-size:18px;font-weight:800;color:inherit">{{ $short($lot->currentPriceCents()) }}</div>
                            <div style="font-size:13px;font-weight:400;color:#4d5566">
                                @if ($lot->sale_type === SaleType::Dutch)
                                    Afslag, daalt
                                @elseif ($lot->sale_type === SaleType::BuyNow)
                                    Koop direct
                                @else
                                    Sluit {{ $auction->ends_at->timezone($displayTimezone)->diffForHumans(short: true) }}
                                @endif
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
    <a class="d-link" style="display:inline-block;margin-top:12px;min-height:44px;line-height:44px" href="{{ route('dashboard.watchlist') }}">Naar je volglijst</a>
</section>

@if ($bought->isNotEmpty())
    <section class="d-card" aria-labelledby="kocht-title">
        <div class="d-titleline">
            <h2 id="kocht-title" class="d-h2">Hoe je kocht</h2>
            <x-dashboard.help text="Je winnende bod naast de waarde-indicatie van Atlas. Een range, geen belofte." />
        </div>
        <div class="d-stack-tight" style="margin-top:12px">
            @foreach ($bought as $order)
                @php $bar = $range($order->hammer_cents, $order->lot->value_min_cents, $order->lot->value_max_cents); @endphp
                <div>
                    <div class="d-range"><strong style="color:#0e1526">{{ $order->lot->title }}</strong><span>Bod {{ $euro($order->hammer_cents) }} · range {{ $euro($order->lot->value_min_cents) }} – {{ $euro($order->lot->value_max_cents) }}</span></div>
                    <div class="d-range-bar" role="img" aria-label="Bod {{ Money::format($order->hammer_cents) }}, {{ $bar['note'] }}">
                        <span class="d-range-band" style="left:{{ $bar['left'] }}%;width:{{ $bar['width'] }}%"></span>
                        <span class="d-range-mark" style="left:{{ $bar['mark'] }}%"></span>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="d-legend"><span><i style="background:#bcd0ff;height:8px;border-radius:4px"></i>Waarde-indicatie</span><span><i style="background:#0c1326;width:3px;height:12px"></i>Jouw bod</span></div>
    </section>
@endif

<section class="d-card" aria-labelledby="alerts-title">
    <h2 id="alerts-title" class="d-h2">Nieuw voor jou</h2>
    @if ($alerts > 0)
        <p class="d-sub">Je hebt {{ $alerts }} {{ $alerts === 1 ? 'zoekalert' : 'zoekalerts' }}. Nieuwe kavels die passen, krijg je per mail en staan bij je meldingen.</p>
    @else
        <p class="d-sub">Zet een zoekalert en Ivy meldt je nieuwe kavels die passen bij wat je zoekt.</p>
    @endif
    <a class="d-btn d-btn-secondary d-btn-sm" style="margin-top:12px" href="{{ route('dashboard.alerts') }}">{{ $alerts > 0 ? 'Bekijk je alerts' : 'Zoekalert instellen' }}</a>
</section>
