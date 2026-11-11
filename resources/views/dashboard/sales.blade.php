@use('App\Enums\SaleState')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    // De groepen: wat nu loopt eerst, dan gepland, concept en afgerond.
    $groupOf = fn (SaleState $state): string => match ($state) {
        SaleState::Live => 'lopend',
        SaleState::Scheduled => 'gepland',
        SaleState::Draft => 'concept',
        default => 'afgerond',
    };
    // Per groep een eigen tabel. Welke cellen een tabel toont hangt van de stand af:
    // een concept heeft geen biedingen, een afgeronde veiling geen sluittijd meer.
    $tableSpecs = [
        'lopend' => ['Lopend', 'Veilingen waar nu op geboden kan worden, met het totaal van de biedingen naast de waarde-indicatie van Atlas.', ['interest', 'amount', 'value', 'verdict']],
        'gepland' => ['Gepland', 'Gepubliceerd en klaar om te starten.', ['interest', 'value']],
        'concept' => ['Concepten', 'Nog niet gepubliceerd. Maak ze af en zet ze live.', []],
        'afgerond' => ['Afgerond', 'Gesloten of geannuleerde veilingen, met het eindtotaal naast de waarde-indicatie.', ['interest', 'amount', 'value', 'verdict']],
    ];
    $tables = collect($tableSpecs)
        ->map(fn (array $spec, string $key): array => [
            'title' => $spec[0],
            'help' => $spec[1],
            'columns' => $spec[2],
            'sales' => $sales->filter(fn ($sale): bool => $groupOf($sale->state()) === $key)->values(),
        ])
        ->filter(fn (array $table): bool => $table['sales']->isNotEmpty());
    $widths = ['interest' => '170px', 'amount' => '140px', 'value' => '190px', 'verdict' => '240px'];
    $labels = ['interest' => 'Interesse', 'amount' => 'Bedrag', 'value' => 'Waarde volgens Atlas', 'verdict' => 'Uitkomst'];
    $whole = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.');
    $listLimit = 5;
@endphp

{{--
    De veilingen van de verkoper, net als Mijn kavels: per stand een eigen
    tabel in een eigen kaart, de koppen één keer, de acties als pictogram.
    De waarde volgens Atlas is de som van de kavels in de veiling, zodra elke
    kavel een schatting heeft.
--}}
<x-layouts.dashboard title="Veilingen" eyebrow="Dashboard" intro="Zet kavels bij elkaar in één veiling met een sluitschema, een bezichtiging en je eigen voorwaarden.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('dashboard.sales.create') }}">Nieuwe veiling</a>
    </x-slot:actions>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif

    @if ($sales->isEmpty())
        <x-ui.empty-state
            title="Nog geen veilingen"
            body="Een veiling bundelt kavels: één sluitschema, één bezichtiging, één set voorwaarden. Handig bij een faillissement, een verhuizing of een partij.">
            <x-slot:action>
                <x-ui.button :href="route('dashboard.sales.create')">Nieuwe veiling</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="d-sections d-stack">
            @foreach ($tables as $key => $table)
                @php
                    $columns = $table['columns'];
                    $template = 'minmax(0,1fr) '.collect($columns)->map(fn (string $column): string => $widths[$column])->implode(' ').' 190px 86px';
                @endphp
                <section class="d-card" aria-labelledby="veilingen-{{ $key }}" x-data="{ all: false }">
                    <div class="d-titleline" style="margin-bottom:12px">
                        <h2 id="veilingen-{{ $key }}" class="d-h2">{{ $table['title'] }}</h2>
                        <span class="d-count tabular">{{ $table['sales']->count() }}</span>
                        <x-dashboard.help :text="$table['help']" />
                    </div>

                    <div @class(['d-lottable', 'is-wide' => in_array('verdict', $columns, true)]) style="--cols:{{ $template }}">
                        <div class="d-lottable-head" aria-hidden="true">
                            <span>Veiling</span>
                            @foreach ($columns as $column)
                                <span @class(['num' => $column === 'amount' || $column === 'value'])>{{ $labels[$column] }}</span>
                            @endforeach
                            <span>Wanneer</span>
                            <span></span>
                        </div>

                        @foreach ($table['sales'] as $sale)
                            @php
                                $state = $sale->state();
                                $summary = $summaries[$sale->id];
                                $kavels = $sale->lots_count.' '.($sale->lots_count === 1 ? 'kavel' : 'kavels');

                                [$tagTone, $tagLabel] = match ($state) {
                                    SaleState::Live => ['tone-ok', 'Lopend'],
                                    SaleState::Scheduled => ['tone-atlas', 'Gepland'],
                                    SaleState::Draft => ['tone-warn', 'Concept'],
                                    SaleState::Cancelled => ['tone-live', 'Geannuleerd'],
                                    default => ['tone-grey', 'Afgerond'],
                                };
                                $moment = match ($state) {
                                    SaleState::Live => $sale->ends_at->isPast() ? 'wordt afgesloten' : 'sluit '.$sale->ends_at->timezone($displayTimezone)->diffForHumans(),
                                    SaleState::Scheduled => 'start '.rtrim($sale->ends_at->timezone($displayTimezone)->isoFormat('D MMM YYYY'), '.'),
                                    SaleState::Draft => 'bewerkt '.$sale->updated_at->timezone($displayTimezone)->diffForHumans(),
                                    SaleState::Cancelled => 'geannuleerd',
                                    default => 'gesloten op '.rtrim($sale->ends_at->timezone($displayTimezone)->isoFormat('D MMM YYYY'), '.'),
                                };
                                $amountLabel = match ($state) {
                                    SaleState::Live => $summary['bids'] > 0 ? 'Huidig totaal' : 'Nog geen biedingen',
                                    SaleState::Cancelled => 'Geannuleerd',
                                    default => 'Eindtotaal',
                                };
                                // De waarde van de veiling: de som van de kavels, alleen als elke kavel een schatting heeft.
                                $valued = $sale->lots->isNotEmpty() && $sale->lots->every(fn ($lot): bool => $lot->hasValueEstimate());
                                $valueMin = $valued ? (int) $sale->lots->sum('value_min_cents') : 0;
                                $valueMax = $valued ? (int) $sale->lots->sum('value_max_cents') : 0;
                                // De uitkomst: alleen bij scherpe schattingen en als er geboden is. Boven de waarde is voor de verkoper goed: groen.
                                $verdict = null;
                                if ($valued && $valueMax > 0 && $summary['bids'] > 0 && $sale->lots->every(fn ($lot): bool => $lot->mayShowValueComparison())) {
                                    $verdict = match (true) {
                                        $summary['total_cents'] > $valueMax => ['below', 'Boven de waarde-indicatie'],
                                        $summary['total_cents'] < $valueMin => ['above', (int) round(100 - $summary['total_cents'] / max(1, $valueMin) * 100).'% onder de waarde-indicatie'],
                                        default => ['within', 'Binnen de waarde-indicatie'],
                                    };
                                    $verdictPercent = (int) round($summary['total_cents'] / $valueMax * 100);
                                }
                                $siteUrl = $sale->isPublished() && $state !== SaleState::Draft ? route('sales.show', $sale) : null;
                                $editUrl = route('dashboard.sales.edit', $sale);
                            @endphp
                            <article class="d-lotrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                <div class="d-lotrow-lot">
                                    <a class="thumb" href="{{ $editUrl }}" tabindex="-1" aria-hidden="true">
                                        @if ($summary['cover'])
                                            <img src="{{ $summary['cover'] }}" alt="" loading="lazy">
                                        @else
                                            <x-v4.icon name="image" :size="20" stroke="1.6" />
                                        @endif
                                    </a>
                                    <div class="text">
                                        <strong><a href="{{ $editUrl }}">{{ $sale->title }}</a></strong>
                                        <span class="d-lotrow-tags">
                                            <span class="d-tag {{ $tagTone }}">{{ $tagLabel }}</span>
                                            @if ($summary['hot'] && $state === SaleState::Live)
                                                <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                            @endif
                                            <span class="d-bid-meta">{{ $kavels }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="d-lotrow-facts">
                                    @if (in_array('interest', $columns, true))
                                        <span class="cell is-interest">
                                            <span class="d-counts">
                                                <span title="{{ $summary['viewers'] }} kijkers"><x-v4.icon name="eye" :size="14" stroke="2" />{{ number_format($summary['viewers'], 0, ',', '.') }}</span>
                                                <span title="{{ $summary['saved'] }} keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" />{{ $summary['saved'] }}</span>
                                                <span title="{{ $summary['bids'] }} biedingen"><x-v4.icon name="gavel" :size="14" stroke="2" />{{ $summary['bids'] }}</span>
                                            </span>
                                        </span>
                                    @endif

                                    @if (in_array('amount', $columns, true))
                                        <span class="cell is-amount">
                                            <b class="tabular">{{ Money::format($summary['total_cents']) }}</b>
                                            <small>{{ $amountLabel }}</small>
                                        </span>
                                    @endif

                                    @if (in_array('value', $columns, true))
                                        <span class="cell is-value">
                                            <span class="label">Waarde volgens Atlas</span>
                                            @if ($valued)
                                                <b class="tabular">{{ $whole($valueMin) }} – {{ $whole($valueMax) }}</b>
                                            @else
                                                <span class="d-muted">nog geen indicatie</span>
                                            @endif
                                        </span>
                                    @endif

                                    @if (in_array('verdict', $columns, true))
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

                                    <span class="cell is-moment">{{ ucfirst($moment) }}</span>
                                </div>

                                {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                                <div class="d-lotrow-actions">
                                    @if ($siteUrl !== null)
                                        <a href="{{ $siteUrl }}" class="d-iconbtn" data-tip="Bekijk op de site" aria-label="{{ $sale->title }} bekijken op de site"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                    @endif
                                    @if (in_array($state, [SaleState::Live, SaleState::Scheduled, SaleState::Draft], true))
                                        @php $editTip = match ($state) { SaleState::Live => 'Beheren', SaleState::Scheduled => 'Bewerken', default => 'Verder met je concept' }; @endphp
                                        <a href="{{ $editUrl }}" class="d-iconbtn" data-tip="{{ $editTip }}" aria-label="{{ $editTip }}: {{ $sale->title }}"><x-v4.icon name="pencil" :size="17" stroke="2" /></a>
                                    @elseif ($siteUrl === null)
                                        <a href="{{ $editUrl }}" class="d-iconbtn" data-tip="Bekijk veiling" aria-label="{{ $sale->title }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <x-dashboard.more :total="$table['sales']->count()" :limit="$listLimit" />
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
