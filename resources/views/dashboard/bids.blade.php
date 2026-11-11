@use('App\Enums\BidStatus')
@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $open = $bids->filter(fn ($bid): bool => ! $bid->auction->isClosed());
    $closed = $bids->filter(fn ($bid): bool => $bid->auction->isClosed());
    $guards = app(\App\Auctions\Support\BidGuards::class);
    // Wat het eerst sluit staat bovenaan.
    $bySoonest = fn ($group) => $group->sortBy(fn ($bid): int => $bid->auction->ends_at->getTimestamp())->values();
    $groupsOfBids = [
        'overboden' => $bySoonest($open->where('status', BidStatus::Outbid)),
        'bovenaan' => $bySoonest($open->where('status', BidStatus::Winning)),
        'gewonnen' => $closed->where('status', BidStatus::Winning)->values(),
        'verloren' => $closed->where('status', '!=', BidStatus::Winning)->values(),
    ];

    // De tegels zijn het filter (?toon=). Zonder filter staan alle tabellen onder elkaar; "Lopend" toont de twee lopende.
    $filters = ['lopend' => ['overboden', 'bovenaan'], 'overboden' => ['overboden'], 'bovenaan' => ['bovenaan'], 'gewonnen' => ['gewonnen'], 'verloren' => ['verloren']];
    $filter = array_key_exists((string) request('toon'), $filters) ? (string) request('toon') : 'alle';
    $filterUrl = fn (string $key): string => route('dashboard.bids', $filter === $key ? [] : ['toon' => $key]);

    // Per stand een eigen tabel. Welke cellen een tabel toont hangt van de stand af:
    // wie bovenaan staat hoeft niet te weten wat het kost om weer op kop te komen.
    $tableSpecs = [
        'overboden' => ['Overboden', 'Hier bood iemand hoger dan jij. Je ziet wat het kost om weer op kop te komen, naast de waarde-indicatie van Atlas. Dat is een schatting, geen belofte.', ['current', 'own', 'next', 'value', 'verdict']],
        'bovenaan' => ['Hoogste bieder', 'Hier sta je nu bovenaan. De ring toont je bod als deel van de bovenkant van de waarde-indicatie van Atlas.', ['own', 'value', 'verdict']],
        'gewonnen' => ['Gewonnen', 'Elke gewonnen kavel heeft een eigen betaalverzoek bij je bestellingen. Na betaling plan je het ophalen of de levering.', ['own', 'value', 'verdict']],
        'verloren' => ['Niet gewonnen', 'Veilingen waar een ander het hoogste bod had, met jouw laatste bod naast het eindbod.', ['own', 'final', 'value']],
    ];
    $tables = collect($tableSpecs)
        ->filter(fn (array $spec, string $key): bool => $filter === 'alle' || in_array($key, $filters[$filter], true))
        ->map(fn (array $spec, string $key): array => ['title' => $spec[0], 'help' => $spec[1], 'columns' => $spec[2], 'bids' => $groupsOfBids[$key]])
        ->filter(fn (array $table): bool => $table['bids']->isNotEmpty());

    $widths = ['current' => '110px', 'own' => '110px', 'next' => '150px', 'final' => '110px', 'value' => '180px', 'verdict' => '240px'];
    $labels = ['current' => 'Huidig bod', 'own' => 'Jouw bod', 'next' => 'Weer op kop vanaf', 'final' => 'Eindbod', 'value' => 'Waarde volgens Atlas', 'verdict' => 'Uitkomst'];
    $euro = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.');
    // Hele euro's zonder ",00"; centen alleen als ze er zijn.
    $short = fn (int $cents): string => $cents % 100 === 0 ? $euro($cents) : Money::format($cents);
    // Eén tabel in beeld toont tien rijen, meerdere tabellen elk vijf.
    $listLimit = $tables->count() === 1 ? 10 : 5;
@endphp

{{--
    Mijn biedingen, net als Mijn kavels: per stand een eigen tabel in een
    eigen kaart, de koppen één keer, de waarde volgens Atlas erbij en de
    acties als pictogram. Voor een koper is een bod onder de waarde goed
    nieuws: groen. Erbinnen is blauw, erboven oranje.
--}}
<x-layouts.dashboard title="Mijn biedingen" eyebrow="Dashboard" intro="Per veiling hoe je ervoor staat: hoogste bieder, overboden of gewonnen.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Naar de veilingen</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" label="Lopend" :value="$open->count()" sub="veilingen waarin je meedoet" :href="$filterUrl('lopend')" :active="$filter === 'lopend'" />
        <x-dashboard.tile tone="orange" label="Overboden" :value="$groupsOfBids['overboden']->count()" sub="nog te verhogen" :href="$filterUrl('overboden')" :active="$filter === 'overboden'" />
        <x-dashboard.tile tone="green" label="Hoogste bieder" :value="$groupsOfBids['bovenaan']->count()" sub="in lopende veilingen" :href="$filterUrl('bovenaan')" :active="$filter === 'bovenaan'" />
        <x-dashboard.tile tone="purple" label="Gewonnen" :value="$groupsOfBids['gewonnen']->count()" sub="afgelopen veilingen" :href="$filterUrl('gewonnen')" :active="$filter === 'gewonnen'" />
    </x-slot:tiles>

    @if ($bids->isEmpty())
        <x-ui.empty-state
            title="Nog geen biedingen"
            body="Zodra je ergens op biedt, volg je hier per veiling hoe je ervoor staat.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        @if ($filter !== 'alle')
            <div class="d-listbar">
                <p class="d-listbar-count">
                    <b class="tabular">{{ $tables->sum(fn (array $table): int => $table['bids']->count()) }}</b> {{ ['lopend' => 'lopend', 'overboden' => 'overboden', 'bovenaan' => 'als hoogste bieder', 'gewonnen' => 'gewonnen', 'verloren' => 'niet gewonnen'][$filter] }}
                    <a class="d-link" href="{{ route('dashboard.bids') }}">Alle {{ $bids->count() }} tonen</a>
                </p>
            </div>
        @endif

        @if ($tables->isEmpty())
            <p class="feed-empty" style="margin:0">{{ match ($filter) {
                'overboden' => 'Je bent nergens overboden.',
                'bovenaan' => 'Je staat nu nergens bovenaan.',
                'gewonnen' => 'Nog niets gewonnen. Wat je wint, staat hier met de bestelling.',
                'verloren' => 'Geen veilingen die je niet won.',
                default => 'Je doet nu nergens aan mee.',
            } }}</p>
            <div style="margin-top:12px"><a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('lots.index') }}">Naar de veilingen</a></div>
        @endif

        <div class="d-sections d-stack">
            @foreach ($tables as $key => $table)
                @php
                    $columns = $table['columns'];
                    $template = 'minmax(0,1fr) '.collect($columns)->map(fn (string $column): string => $widths[$column])->implode(' ').' 86px';
                @endphp
                <section class="d-card" aria-labelledby="biedingen-{{ $key }}" x-data="{ all: false }">
                    <div class="d-titleline" style="margin-bottom:12px">
                        <h2 id="biedingen-{{ $key }}" class="d-h2">{{ $table['title'] }}</h2>
                        <span class="d-count tabular">{{ $table['bids']->count() }}</span>
                        <x-dashboard.help :text="$table['help']" />
                    </div>

                    <div @class(['d-lottable', 'is-wide' => count($columns) > 3]) style="--cols:{{ $template }}">
                        <div class="d-lottable-head" aria-hidden="true">
                            <span>Kavel</span>
                            @foreach ($columns as $column)
                                <span @class(['num' => $column !== 'verdict'])>{{ $key === 'gewonnen' && $column === 'own' ? 'Winnend bod' : $labels[$column] }}</span>
                            @endforeach
                            <span></span>
                        </div>

                        @foreach ($table['bids'] as $bid)
                            @php
                                $auction = $bid->auction;
                                $lot = $auction->lot;
                                $isOpen = ! $auction->isClosed();
                                $order = $lot->order;
                                $currentCents = $lot->currentPriceCents();
                                [$tagTone, $tagLabel] = match ($key) {
                                    'overboden' => ['tone-warn', 'Overboden'],
                                    'bovenaan' => ['tone-ok', 'Hoogste bieder'],
                                    'gewonnen' => $order?->status === OrderStatus::AwaitingPayment ? ['tone-warn', 'Wacht op betaling'] : ['tone-atlas', 'Gewonnen'],
                                    default => ['tone-grey', 'Niet gewonnen'],
                                };
                                $moment = $isOpen
                                    ? ucfirst($auction->closesText())
                                    : 'Gesloten op '.rtrim($auction->ends_at->timezone($displayTimezone)->isoFormat('D MMM'), '.');
                                // De uitkomst: het bod naast de waarde-indicatie, alleen bij een scherpe schatting.
                                // Bij overboden telt het huidige bod, anders je eigen bod.
                                $compareCents = $key === 'overboden' ? $currentCents : $bid->amount_cents;
                                $verdict = null;
                                if ($lot->mayShowValueComparison() && $lot->value_max_cents > 0) {
                                    $verdict = match (true) {
                                        $compareCents < $lot->value_min_cents => ['below', (int) round(100 - $compareCents / max(1, $lot->value_min_cents) * 100).'% onder de waarde-indicatie'],
                                        $compareCents > $lot->value_max_cents => ['above', 'Boven de waarde-indicatie'],
                                        default => ['within', 'Binnen de waarde-indicatie'],
                                    };
                                    $verdictPercent = (int) round($compareCents / $lot->value_max_cents * 100);
                                }
                            @endphp
                            <article class="d-lotrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                <div class="d-lotrow-lot">
                                    <a class="thumb" href="{{ route('lots.show', $lot) }}" tabindex="-1" aria-hidden="true">
                                        @if ($lot->media->first())
                                            <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                                        @else
                                            <x-v4.icon name="image" :size="20" stroke="1.6" />
                                        @endif
                                    </a>
                                    <div class="text">
                                        <strong><a href="{{ route('lots.show', $lot) }}">{{ $lot->title }}</a></strong>
                                        <span class="d-lotrow-tags">
                                            <span class="d-tag {{ $tagTone }}">{{ $tagLabel }}</span>
                                            @if ($lot->is_hot && $isOpen)
                                                <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                            @endif
                                            <span class="d-bid-meta">Kavel #{{ $lot->public_id }} · {{ $moment }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="d-lotrow-facts">
                                    @foreach ($columns as $column)
                                        @if ($column === 'current')
                                            <span class="cell is-value"><span class="label">Huidig bod</span><b class="tabular">{{ $short($currentCents) }}</b></span>
                                        @elseif ($column === 'own')
                                            <span class="cell is-value"><span class="label">{{ $key === 'gewonnen' ? 'Winnend bod' : 'Jouw bod' }}</span><b class="tabular">{{ $short($bid->amount_cents) }}</b></span>
                                        @elseif ($column === 'next')
                                            <span class="cell is-value"><span class="label">Weer op kop vanaf</span><b class="tabular">{{ $short($guards->minimumBid($auction)) }}</b></span>
                                        @elseif ($column === 'final')
                                            <span class="cell is-value"><span class="label">Eindbod</span><b class="tabular">{{ $short($currentCents) }}</b></span>
                                        @elseif ($column === 'value')
                                            <span class="cell is-value">
                                                <span class="label">Waarde volgens Atlas</span>
                                                @if ($lot->hasValueEstimate())
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
                                    @if ($key === 'overboden')
                                        <a href="{{ route('lots.show', $lot) }}" class="d-iconbtn is-dark" data-tip="Bied opnieuw" aria-label="Opnieuw bieden op {{ $lot->title }}"><x-v4.icon name="gavel" :size="17" stroke="2" /></a>
                                    @elseif ($key === 'gewonnen' && $order !== null && $order->status === OrderStatus::AwaitingPayment)
                                        <a href="{{ route('orders.show', $order) }}" class="d-iconbtn is-dark" data-tip="Betalen" aria-label="{{ $lot->title }} betalen"><x-v4.icon name="banknote" :size="17" stroke="2" /></a>
                                    @elseif ($key === 'gewonnen' && $order !== null)
                                        <a href="{{ route('orders.show', $order) }}" class="d-iconbtn" data-tip="Bekijk bestelling" aria-label="Bestelling van {{ $lot->title }} bekijken"><x-v4.icon name="shopping-bag" :size="17" stroke="2" /></a>
                                    @else
                                        <a href="{{ route('lots.show', $lot) }}" class="d-iconbtn" data-tip="Bekijk kavel" aria-label="Kavel {{ $lot->title }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <x-dashboard.more :total="$table['bids']->count()" :limit="$listLimit" />
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
