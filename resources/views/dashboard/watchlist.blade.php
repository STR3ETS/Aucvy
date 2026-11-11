@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    // In welke tabel een gevolgde kavel hoort: wat binnen een dag sluit eerst, dan wat loopt, wat nog moet starten en wat voorbij is.
    $groupOf = function ($item): string {
        $auction = $item->lot->auction;

        return match (true) {
            $auction === null => 'gepland',
            $auction->isClosed() => 'gesloten',
            ! $auction->isPaused() && $auction->ends_at->lessThanOrEqualTo(now()->addDay()) => 'bijna',
            default => 'loopt',
        };
    };
    $grouped = $items->groupBy($groupOf);
    $bySoonest = fn ($group) => $group->sortBy(fn ($item): int => $item->lot->auction->ends_at->getTimestamp())->values();

    // Per stand een eigen tabel. Welke cellen een tabel toont hangt van de stand af:
    // een kavel zonder veiling heeft nog geen bod, een gesloten veiling geen kijkers meer.
    $tableSpecs = [
        'bijna' => ['Sluit binnen een dag', 'Nu beslissen: deze veilingen sluiten binnen 24 uur. Het bod staat naast de waarde-indicatie van Atlas. Dat is een schatting, geen belofte.', ['interest', 'amount', 'value', 'verdict']],
        'loopt' => ['Loopt', 'Veilingen die nog even doorlopen. Je krijgt een seintje als ze bijna sluiten.', ['interest', 'amount', 'value', 'verdict']],
        'gepland' => ['Nog niet gestart', 'Kavels waar nog geen veiling voor loopt.', ['value']],
        'gesloten' => ['Gesloten', 'Veilingen die voorbij zijn, met het eindbod naast de waarde-indicatie van Atlas.', ['amount', 'value', 'verdict']],
    ];

    // De tegels zijn het filter (?toon=). Zonder filter staan alle tabellen onder elkaar.
    $filters = ['bijna' => 'die binnen een dag sluiten', 'gesloten' => 'gesloten'];
    $filter = array_key_exists((string) request('toon'), $filters) ? (string) request('toon') : 'alle';
    $filterUrl = fn (string $key): string => route('dashboard.watchlist', $filter === $key ? [] : ['toon' => $key]);
    $tables = collect($tableSpecs)
        ->filter(fn (array $spec, string $key): bool => $filter === 'alle' || $filter === $key)
        ->map(fn (array $spec, string $key): array => [
            'title' => $spec[0],
            'help' => $spec[1],
            'columns' => $spec[2],
            'items' => in_array($key, ['bijna', 'loopt'], true) ? $bySoonest($grouped->get($key, collect())) : $grouped->get($key, collect())->values(),
        ])
        ->filter(fn (array $table): bool => $table['items']->isNotEmpty());

    $widths = ['interest' => '170px', 'amount' => '130px', 'value' => '180px', 'verdict' => '240px'];
    $labels = ['interest' => 'Interesse', 'amount' => 'Bedrag', 'value' => 'Waarde volgens Atlas', 'verdict' => 'Uitkomst'];
    $euro = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.');
    // Eén tabel in beeld toont tien rijen, meerdere tabellen elk vijf.
    $listLimit = $tables->count() === 1 ? 10 : 5;
@endphp

{{--
    De volglijst, net als Mijn kavels: per stand een eigen tabel in een eigen
    kaart, de koppen één keer, de waarde volgens Atlas erbij en de acties als
    pictogram. Voor een koper is een bod onder de waarde goed nieuws: groen.
    Erbinnen is blauw, erboven oranje.
--}}
<x-layouts.dashboard title="Volglijst" eyebrow="Dashboard" intro="De kavels die je volgt, met de stand en wanneer ze sluiten. Je hoort het als een gevolgde veiling bijna sluit.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Naar de veilingen</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" icon="heart" label="Gevolgd" :value="$items->count()" sub="kavels op je lijst" :href="route('dashboard.watchlist')" />
        <x-dashboard.tile tone="orange" icon="clock" label="Sluit binnen een dag" :value="$grouped->get('bijna', collect())->count()" sub="nu beslissen" :href="$filterUrl('bijna')" :active="$filter === 'bijna'" />
        <x-dashboard.tile tone="purple" icon="gavel" label="Gesloten" :value="$grouped->get('gesloten', collect())->count()" sub="veilingen voorbij" :href="$filterUrl('gesloten')" :active="$filter === 'gesloten'" />
    </x-slot:tiles>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif

    @if ($items->isEmpty())
        <x-ui.empty-state title="Je volgt nog geen kavels"
            body="Op elke kavelpagina staat een knop Volgen. Je krijgt dan een seintje als de veiling bijna sluit.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        @if ($filter !== 'alle')
            <div class="d-listbar">
                <p class="d-listbar-count">
                    <b class="tabular">{{ $tables->sum(fn (array $table): int => $table['items']->count()) }}</b> {{ $filters[$filter] }}
                    <a class="d-link" href="{{ route('dashboard.watchlist') }}">Alle {{ $items->count() }} tonen</a>
                </p>
            </div>
        @endif

        @if ($tables->isEmpty())
            <p class="feed-empty" style="margin:0">{{ $filter === 'bijna' ? 'Geen gevolgde veiling sluit binnen een dag.' : 'Geen gesloten veilingen op je lijst.' }}</p>
        @endif

        <div class="d-sections d-stack">
            @foreach ($tables as $key => $table)
                @php
                    $columns = $table['columns'];
                    $template = 'minmax(0,1fr) '.collect($columns)->map(fn (string $column): string => $widths[$column])->implode(' ').' 86px';
                @endphp
                <section class="d-card" aria-labelledby="volglijst-{{ $key }}" x-data="{ all: false }">
                    <div class="d-titleline" style="margin-bottom:12px">
                        <h2 id="volglijst-{{ $key }}" class="d-h2">{{ $table['title'] }}</h2>
                        <span class="d-count tabular">{{ $table['items']->count() }}</span>
                        <x-dashboard.help :text="$table['help']" />
                    </div>

                    <div @class(['d-lottable', 'is-wide' => count($columns) > 3]) style="--cols:{{ $template }}">
                        <div class="d-lottable-head" aria-hidden="true">
                            <span>Kavel</span>
                            @foreach ($columns as $column)
                                <span @class(['num' => $column === 'amount' || $column === 'value'])>{{ $labels[$column] }}</span>
                            @endforeach
                            <span></span>
                        </div>

                        @foreach ($table['items'] as $item)
                            @php
                                $lot = $item->lot;
                                $auction = $lot->auction;
                                $interest = in_array('interest', $columns, true) ? \App\Lots\Interest\Interest::forBuyer($lot) : null;
                                [$tagTone, $tagLabel] = match (true) {
                                    $key === 'bijna' => ['tone-warn', 'Sluit bijna'],
                                    $key === 'loopt' && $auction->isPaused() => ['tone-grey', 'Gepauzeerd'],
                                    $key === 'loopt' => ['tone-ok', 'Loopt'],
                                    $key === 'gepland' => ['tone-atlas', 'Nog niet gestart'],
                                    default => ['tone-grey', 'Gesloten'],
                                };
                                $moment = match (true) {
                                    $auction === null => 'nog geen veiling gepland',
                                    $auction->isClosed() => 'gesloten op '.rtrim($auction->ends_at->timezone($displayTimezone)->isoFormat('D MMM'), '.'),
                                    $auction->isPaused() => 'de klok staat stil',
                                    default => $auction->closesText(),
                                };
                                $amountCents = $auction !== null ? $lot->currentPriceCents() : null;
                                $amountLabel = match (true) {
                                    $auction === null => '',
                                    $auction->isClosed() => 'Eindbod',
                                    $lot->sale_type === SaleType::Dutch => 'Prijs nu',
                                    $lot->sale_type === SaleType::BuyNow => 'Vaste prijs',
                                    default => 'Huidig bod',
                                };
                                // De uitkomst: het bedrag naast de waarde-indicatie, alleen bij een scherpe schatting.
                                $verdict = null;
                                if ($amountCents !== null && $lot->mayShowValueComparison() && $lot->value_max_cents > 0) {
                                    $verdict = match (true) {
                                        $amountCents < $lot->value_min_cents => ['below', (int) round(100 - $amountCents / max(1, $lot->value_min_cents) * 100).'% onder de waarde-indicatie'],
                                        $amountCents > $lot->value_max_cents => ['above', 'Boven de waarde-indicatie'],
                                        default => ['within', 'Binnen de waarde-indicatie'],
                                    };
                                    $verdictPercent = (int) round($amountCents / $lot->value_max_cents * 100);
                                }
                            @endphp
                            <article class="d-lotrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                <div class="d-lotrow-lot">
                                    <a class="thumb" href="{{ route('lots.show', $lot) }}" tabindex="-1" aria-hidden="true">
                                        @if ($lot->media->isNotEmpty())
                                            <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                                        @else
                                            <x-v4.icon name="image" :size="20" stroke="1.6" />
                                        @endif
                                    </a>
                                    <div class="text">
                                        <strong><a href="{{ route('lots.show', $lot) }}">{{ $lot->title }}</a></strong>
                                        <span class="d-lotrow-tags">
                                            <span class="d-tag {{ $tagTone }}">{{ $tagLabel }}</span>
                                            @if ($lot->is_hot && in_array($key, ['bijna', 'loopt'], true))
                                                <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                            @endif
                                            <span class="d-bid-meta">Kavel #{{ $lot->public_id }} · {{ ucfirst($moment) }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="d-lotrow-facts">
                                    @foreach ($columns as $column)
                                        @if ($column === 'interest')
                                            <span class="cell is-interest">
                                                <span class="d-counts">
                                                    <span title="kijkers"><x-v4.icon name="eye" :size="14" stroke="2" />{{ number_format($interest['viewers'], 0, ',', '.') }}</span>
                                                    <span title="keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" />{{ $interest['saved'] }}</span>
                                                    <span title="bieders"><x-v4.icon name="gavel" :size="14" stroke="2" />{{ (int) $auction->bidder_count }}</span>
                                                </span>
                                            </span>
                                        @elseif ($column === 'amount')
                                            <span class="cell is-amount">
                                                <b class="tabular">{{ Money::format($amountCents) }}</b>
                                                <small>{{ $amountLabel }}</small>
                                            </span>
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
                                    <a href="{{ route('lots.show', $lot) }}" class="d-iconbtn" data-tip="Bekijk kavel" aria-label="Kavel {{ $lot->title }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                    <form method="POST" action="{{ route('dashboard.watchlist.remove', $lot) }}" style="display:flex">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="d-iconbtn is-on" data-tip="Ontvolgen" aria-label="Stop met volgen van {{ $lot->title }}"><x-v4.icon name="heart" :size="17" stroke="2" /></button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <x-dashboard.more :total="$table['items']->count()" :limit="$listLimit" />
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
