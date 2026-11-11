@use('App\Enums\SaleType')
@use('App\Support\Charts\SmoothLine')
@use('App\Support\Money\Money')

{{--
    Het verkopersoverzicht uit het ontwerp van november: de opbrengst per
    maand, de biedingen per uur, de interesse per kavel en de verdieping
    (wie kijkt er écht, wat doet de vraag, wat Ivy deze week zag).
--}}
@php
    $displayTimezone = config('aucvy.display_timezone');
    $euro = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.');
    $sourceLabels = ['alert' => 'Zoekalerts', 'google' => 'Google', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'shared' => 'Gedeelde links', 'direct' => 'Direct', 'other' => 'Overig'];

    // De lijn van de omzetgrafiek: 11 of 12 punten op een vlak van 100 bij 100.
    $months = $revenue['months'];
    $peak = $revenue['peak_cents'];
    $points = collect($months)->values()->map(fn (array $month, int $index): array => [count($months) > 1 ? $index / (count($months) - 1) * 100 : 50, 100 - $month['revenue_cents'] / $peak * 100])->all();
    // Een vloeiende lijn door de maanden; het vlak eronder sluit op de onderrand.
    $linePath = SmoothLine::through($points);
    $areaPath = $points === [] ? '' : $linePath.' L'.round(end($points)[0], 2).',100 L'.round($points[0][0], 2).',100 Z';
    $axis = collect([1, 2 / 3, 1 / 3, 0])->map(fn (float $share): array => ['top' => (1 - $share) * 200 + 10, 'label' => $share === 0.0 ? '€ 0' : $euro((int) round($peak * $share))]);
@endphp

<section class="d-card" aria-labelledby="omzet-title">
    <div class="d-card-head">
        <div>
            <div class="d-label">Opbrengst</div>
            <div class="flex items-center gap-2" style="margin-top:2px">
                <strong id="omzet-title" class="d-big">{{ Money::format($revenue['revenue_cents']) }}</strong>
                @if ($revenue['trend'] !== null)
                    <span class="d-trend{{ $revenue['trend'] < 0 ? ' is-down' : '' }}"><x-v4.icon :name="$revenue['trend'] < 0 ? 'arrow-down-right' : 'arrow-up-right'" :size="12" stroke="2.8" /> {{ abs($revenue['trend']) }}%</span>
                @endif
            </div>
            <div class="d-muted" style="margin-top:2px">afgeronde verkopen van de laatste twaalf maanden</div>
        </div>
        <a class="d-btn d-btn-secondary d-btn-sm" href="{{ $user->canSeeCompanyFinance() ? route('dashboard.finance') : route('orders.index') }}">Naar financiën</a>
    </div>
    <div class="d-legend"><span><i></i>Opbrengst</span></div>

    @if ($revenue['revenue_cents'] === 0)
        <p class="feed-empty" style="margin-top:12px">Nog geen afgeronde verkopen. Na je eerste afgeronde verkoop tekent de grafiek hier je opbrengst per maand.</p>
    @else
        {{-- Aanwijzen of aanraken toont de maand en het bedrag; de tabel eronder is er voor wie de grafiek niet ziet. --}}
        <div class="d-line" role="img"
             x-data="{ i: null, months: {{ Js::from(collect($months)->map(fn (array $month): array => ['label' => $month['label'], 'amount' => $euro($month['revenue_cents']), 'y' => 100 - $month['revenue_cents'] / $peak * 100])->values()) }},
                 at(event) { const box = this.$refs.hit.getBoundingClientRect(); const x = (event.touches ? event.touches[0].clientX : event.clientX) - box.left; this.i = Math.max(0, Math.min(this.months.length - 1, Math.round(x / box.width * (this.months.length - 1)))); },
                 get x() { return this.months.length > 1 ? this.i / (this.months.length - 1) * 100 : 50; } }"
             aria-label="Opbrengst per maand, {{ collect($months)->map(fn (array $month): string => $month['label'].' '.$euro($month['revenue_cents']))->implode(', ') }}">
            @foreach ($axis as $line)
                <div class="d-line-grid" aria-hidden="true" style="top:{{ $line['top'] }}px"><span>{{ $line['label'] }}</span></div>
            @endforeach
            <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" style="top:10px">
                <path d="{{ $areaPath }}" fill="rgba(47,107,255,.12)"></path>
                <path d="{{ $linePath }}" fill="none" stroke="#2f6bff" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"></path>
            </svg>
            <div class="d-line-hit" x-ref="hit" aria-hidden="true"
                 @mousemove="at($event)" @mouseleave="i = null" @touchstart.passive="at($event)" @touchmove.passive="at($event)">
                <template x-if="i !== null">
                    <div style="display:contents">
                        <span class="d-line-guide" :style="'left:' + x + '%'"></span>
                        <span class="d-line-dot" :style="'left:' + x + '%;top:' + months[i].y + '%'"></span>
                        <div class="d-line-tip" :class="i > (months.length - 1) / 2 ? 'is-left' : 'is-right'" :style="'left:' + x + '%'">
                            <strong x-text="months[i].label"></strong>
                            <span x-text="'Opbrengst ' + months[i].amount"></span>
                        </div>
                    </div>
                </template>
            </div>
            <div class="d-line-x" aria-hidden="true">
                @foreach ($months as $month)
                    <span>{{ $month['label'] }}</span>
                @endforeach
            </div>
        </div>
        <details class="d-details" style="margin-top:8px">
            <summary>Bekijk als tabel</summary>
            <table class="d-table">
                <thead><tr><th>Maand</th><th class="num">Opbrengst</th></tr></thead>
                <tbody>
                    @foreach ($months as $month)
                        <tr><td>{{ $month['label'] }}</td><td class="num">{{ Money::format($month['revenue_cents']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</section>

<section class="d-card" aria-labelledby="bieden-title">
    <div class="d-card-head">
        <div>
            <div class="d-label">Biedingen</div>
            <div style="margin-top:2px">
                <strong id="bieden-title" class="d-big">{{ $hours['total'] }}</strong>
                <span class="d-muted">laatste 24 uur</span>
            </div>
        </div>
        <div class="d-muted" style="text-align:right">
            @if ($hours['peak_count'] > 0)
                Drukste moment: {{ $hours['peak_label'] }} uur met {{ $hours['peak_count'] }} {{ $hours['peak_count'] === 1 ? 'bod' : 'biedingen' }}
            @else
                Nog geen biedingen in de laatste 24 uur
            @endif
        </div>
    </div>
    <div class="d-hours{{ $hours['total'] === 0 ? ' is-empty' : '' }}" role="img" aria-label="Biedingen per uur, {{ $hours['total'] }} in 24 uur{{ $hours['peak_count'] > 0 ? ', drukste moment '.$hours['peak_label'].' met '.$hours['peak_count'] : '' }}">
        @foreach ($hours['hours'] as $index => $hour)
            <div><i class="{{ $index === $hours['peak_index'] && $hour['count'] > 0 ? 'is-peak' : '' }}" style="height:{{ $hours['peak_count'] > 0 ? round($hour['count'] / $hours['peak_count'] * 100, 1) : 0 }}%"></i></div>
        @endforeach
    </div>
    <div class="d-hours-x" aria-hidden="true">
        <span>{{ $hours['hours'][0]['label'] }}</span>
        <span>{{ $hours['hours'][6]['label'] }}</span>
        <span>{{ $hours['hours'][12]['label'] }}</span>
        <span>{{ $hours['hours'][18]['label'] }}</span>
        <span>nu</span>
    </div>
</section>

<section class="d-card" aria-labelledby="interesse-title">
    <div class="d-card-head">
        <div style="flex:1 1 240px">
            <div class="d-titleline">
                <h2 id="interesse-title" class="d-h2">Interesse per kavel</h2>
                <x-dashboard.help text="Hoeveel mensen je kavel bekeken, opgeslagen en gedeeld hebben. Je ziet aantallen, geen namen. Bots en je eigen team tellen niet mee. Opgeslagen betekent: op de volglijst van een koper gezet." />
            </div>
        </div>
        <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('dashboard.lots') }}">Alle kavels</a>
    </div>

    @if ($interest === [])
        <p class="feed-empty" style="margin-top:12px">Zodra een kavel live staat, zie je hier per kavel de kijkers, weergaven, opgeslagen, gedeeld, documenten en bieders.</p>
    @else
        {{--
            Eén tabel: de kolomkoppen staan er één keer boven, elke kavel is één
            regel met de vier stappen van kijken naar bieden. Een klik op de
            regel klapt de details open, met de weergaven en hoe vaak hij is
            gedeeld. Op een smal scherm staan de getallen in een raster onder de
            titel, elk met zijn eigen label.
        --}}
        @php $columns = [['Kijkers', 'eye', 'viewers'], ['Opgeslagen', 'heart', 'saved'], ['Documenten', 'file', 'documents'], ['Bieders', 'gavel', 'bidders']]; @endphp
        <div class="d-intable" style="margin-top:14px">
            <div class="d-intable-head" aria-hidden="true">
                <span>Kavel</span>
                @foreach ($columns as [$label, $icon])
                    <span class="num">{{ $label }}</span>
                @endforeach
                <span></span>
            </div>
            @foreach ($interest as $row)
                @php
                    $lot = $row['lot'];
                    $stats = $row['stats'];
                    $auction = $lot->auction;
                    $totalSources = max(1, array_sum($stats['sources']));
                    $funnelMax = max(1, $stats['viewers'], $stats['saved'], $stats['documents'], $stats['bidders']);
                    $priceLabel = match ($lot->sale_type) {
                        SaleType::Dutch => 'Prijs nu',
                        SaleType::BuyNow => 'Vaste prijs',
                        default => 'Huidig bod',
                    };
                    $closing = $auction !== null && ! $auction->isClosed() ? 'sluit '.$auction->ends_at->timezone($displayTimezone)->diffForHumans() : null;
                @endphp
                <article class="d-intable-item" x-data="{ open: false }" :class="open && 'is-open'">
                    <div class="d-intable-row" @click="if (! $event.target.closest('a')) open = ! open">
                        <div class="d-intable-lot">
                            @if ($lot->media->first())
                                <img class="d-lot-thumb d-lot-thumb-sm" src="{{ $lot->media->first()->path }}" alt="">
                            @else
                                <span class="d-lot-thumb d-lot-thumb-sm" aria-hidden="true"></span>
                            @endif
                            <div class="text">
                                <strong>
                                    <a href="{{ route('dashboard.lots.show', $lot) }}">{{ $lot->title }}</a>
                                    @if ($stats['hot'])
                                        <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                    @elseif ($stats['bidders'] > 0)
                                        <span class="d-tag tone-atlas">Loopt op</span>
                                    @endif
                                </strong>
                                <span class="d-muted">{{ $priceLabel }} {{ Money::format($lot->currentPriceCents()) }}@if ($closing) · {{ $closing }}@endif</span>
                            </div>
                        </div>
                        @foreach ($columns as [$label, $icon, $key])
                            <div class="d-intable-num{{ $key === 'bidders' ? ' is-key' : '' }}">
                                <span class="label">{{ $label }}</span>
                                <b class="tabular">{{ number_format($stats[$key], 0, ',', '.') }}</b>
                            </div>
                        @endforeach
                        <button type="button" class="d-intable-toggle" :aria-expanded="open" aria-label="Details van {{ $lot->title }}" @click.stop="open = ! open">
                            <x-v4.icon name="chevron-down" :size="18" stroke="2.2" />
                        </button>
                    </div>
                    <div class="d-intable-detail" x-show="open" x-cloak>
                        <div class="d-detail">
                            <div>
                                <h3>Van kijken naar bieden</h3>
                                @foreach ([['Kijkers', $stats['viewers']], ['Opgeslagen', $stats['saved']], ['Documenten geopend', $stats['documents']], ['Bieders', $stats['bidders']]] as [$label, $value])
                                    <div class="d-funnel-row">
                                        <div><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                                        <div class="d-bar" aria-hidden="true" style="width:{{ round($value / $funnelMax * 100, 1) }}%"></div>
                                    </div>
                                @endforeach
                            </div>
                            <div>
                                <h3>Waar kijkers vandaan komen</h3>
                                @forelse (collect($stats['sources'])->sortDesc() as $source => $count)
                                    @php $share = (int) round($count / $totalSources * 100); @endphp
                                    <div class="d-source">
                                        <span>{{ $sourceLabels[$source] ?? ucfirst($source) }}</span>
                                        <span class="d-bar" aria-hidden="true" style="width:{{ $share }}%"></span>
                                        <strong>{{ $share }}%</strong>
                                    </div>
                                @empty
                                    <p class="d-muted">Nog geen herkomst gemeten.</p>
                                @endforelse
                            </div>
                        </div>
                        <p class="d-intable-extra"><b class="tabular">{{ number_format($stats['views'], 0, ',', '.') }}</b> weergaven · <b class="tabular">{{ number_format($stats['shared'], 0, ',', '.') }}</b> keer gedeeld</p>
                        @if ($row['tip'])
                            <div class="d-ivy-note">
                                <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
                                <span>{{ $row['tip'] }}</span>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>

{{-- Verdieping: drie tabs, zonder knoppen om te doen. --}}
<div x-data="{ depth: 'kijkers' }">
    <div class="d-chips" style="align-items:center;margin-bottom:10px">
        <span class="d-aside-label" style="margin-right:4px">Verdieping</span>
        <button type="button" class="d-chip" :class="depth === 'kijkers' && 'is-on'" :aria-pressed="depth === 'kijkers'" @click="depth = 'kijkers'">Wie kijkt er écht?</button>
        <button type="button" class="d-chip" :class="depth === 'vraag' && 'is-on'" :aria-pressed="depth === 'vraag'" @click="depth = 'vraag'">Wat doet de vraag?</button>
        <button type="button" class="d-chip" :class="depth === 'ivy' && 'is-on'" :aria-pressed="depth === 'ivy'" @click="depth = 'ivy'">Wat Ivy deze week zag</button>
    </div>

    <section class="d-card" x-show="depth === 'kijkers'" aria-label="{{ __('dashboard.intent.title') }}">
        <div class="d-titleline">
            <h2 class="d-h2">Wie kijkt er écht?</h2>
            <x-dashboard.help text="Bezoekersaantallen zeggen weinig. Dit zijn gedragssignalen van de laatste 30 dagen: wie volgt, wie een bezichtiging vroeg, wie bood. {{ __('dashboard.intent.privacy') }}" />
        </div>
        @php $intentMax = max(1, $intent['watchers'], $intent['viewings'], $intent['bidders'], $intent['business']); @endphp
        <div class="d-depth">
            @foreach ([['watchers', 'Volgers'], ['viewings', 'Bezichtigingen'], ['bidders', 'Bieders'], ['business', 'Zakelijk']] as [$key, $label])
                <div class="d-depth-item{{ $key === 'bidders' ? ' is-on' : '' }}">
                    <span class="d-label">{{ $label }}</span>
                    <div class="d-depth-value">{{ $intent[$key] }}</div>
                    <div class="d-depth-bar" aria-hidden="true"><i style="width:{{ round($intent[$key] / $intentMax * 100) }}%"></i></div>
                    <span class="d-muted" style="display:block;margin-top:6px;font-size:12px">{{ __('dashboard.intent.'.$key) }}</span>
                </div>
            @endforeach
        </div>
        @if ($intent['bidders'] > 0)
            <div class="d-callout">
                <span><b>{{ $intent['bidders'] }} × hoge intentie:</b> {{ $intent['bidders'] === 1 ? 'iemand bracht' : 'kopers brachten' }} al een bod uit op je kavels. Een bericht via de kavel bereikt ze zonder dat je hun gegevens ziet.</span>
                <a class="d-btn d-btn-secondary d-btn-sm d-btn-sq" href="{{ route('messages.index') }}">Persoonlijk benaderen</a>
            </div>
        @endif
    </section>

    <section class="d-card" x-show="depth === 'vraag'" x-cloak aria-label="{{ __('dashboard.forecast.title') }}">
        <h2 class="d-h2">{{ __('dashboard.forecast.title') }}</h2>
        @if (! $forecast['has_history'])
            <p class="d-sub">{{ __('dashboard.forecast.empty') }}</p>
            <div class="forecast-empty">
                <div class="forecast-ghost" aria-hidden="true">
                    @foreach ($forecast['weeks'] as $week)
                        <div class="col">
                            <div class="bar {{ $week['measured'] ? '' : 'is-predicted' }}" style="height:{{ 24 + (($loop->index * 37) % 56) }}%"></div>
                            <span>{{ $week['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="forecast-note">
                    <span class="stat-icon tone-blue"><x-v4.icon name="trending-up" :size="18" stroke="2" /></span>
                    <div>
                        <b>Zo gaat het eruitzien</b>
                        <p>Acht gemeten weken, drie voorspelde. Na je eerste afgeronde verkoop tekent Ivy hier je weekomzet en een bandbreedte voor de weken erna.</p>
                        <a class="d-btn d-btn-sm d-btn-sq" href="{{ route('sell') }}">Kavel plaatsen</a>
                    </div>
                </div>
            </div>
        @else
            @php $weekPeak = max(1, max(array_column($forecast['weeks'], 'high_cents'))); @endphp
            <p class="d-sub">{{ __('dashboard.forecast.disclaimer') }}</p>
            <div class="mt-4 flex h-32 items-end gap-1" role="img"
                 aria-label="Weekomzet, {{ __('dashboard.forecast.measured') }} en {{ __('dashboard.forecast.predicted') }}">
                @foreach ($forecast['weeks'] as $week)
                    <div class="flex flex-1 flex-col items-center gap-1" @if (! $week['measured']) style="opacity:.55" @endif>
                        <div class="w-full rounded-t-aj-sm {{ $week['measured'] ? 'bg-aj-blue' : 'border-2 border-dashed border-aj-blue bg-aj-blue/15' }}"
                             style="height:{{ max(2, intdiv($week['high_cents'] * 112, $weekPeak)) }}px"
                             title="{{ $week['label'] }}: {{ $week['measured'] ? Money::format($week['high_cents']) : Money::format($week['low_cents']).' – '.Money::format($week['high_cents']) }}"></div>
                        <span class="text-[10px] text-aj-muted">{{ $week['label'] }}</span>
                    </div>
                    @if ($week['measured'] && ! ($forecast['weeks'][$loop->index + 1]['measured'] ?? true))
                        <div class="h-28 w-px shrink-0 bg-aj-border-strong" aria-hidden="true"></div>
                    @endif
                @endforeach
            </div>
            <p class="d-footnote">
                {{ count(array_filter($forecast['weeks'], fn ($week) => $week['measured'])) }} {{ __('dashboard.forecast.measured') }}
                · {{ count(array_filter($forecast['weeks'], fn ($week) => ! $week['measured'])) }} {{ __('dashboard.forecast.predicted') }}
            </p>
        @endif
    </section>

    <section class="d-card" x-show="depth === 'ivy'" x-cloak aria-label="Wat Ivy deze week zag">
        <div class="d-titleline">
            <h2 class="d-h2">Wat Ivy deze week zag</h2>
            <x-dashboard.help text="Observaties uit je kavels en biedingen. Dit is om te weten, niet om te doen." />
        </div>
        @if ($weekly->isEmpty())
            <p class="feed-empty" style="margin-top:12px">Nog niets deze week. Zodra er iets opvalt in je kavels of biedingen, staat het hier.</p>
        @else
            <ul class="mt-3 grid gap-3 sm:grid-cols-2" style="list-style:none;padding:0;margin-top:12px">
                @foreach ($weekly as $insight)
                    <li class="d-task" x-show="agent === null || agent === '{{ $insight->agent->value }}'">
                        <span class="d-tag tone-{{ $insight->agent->value }}">{{ $insight->agent->label() }}</span>
                        <p>{{ $insight->title }}</p>
                        @if ($insight->body)
                            <span class="d-muted">{{ $insight->body }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
