@use('App\Enums\LotStatus')
@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

@php
    // Vijf groepen voor de filterchips; de status zelf blijft op de rij staan.
    $groupOf = fn (LotStatus $status): string => match (true) {
        in_array($status, [LotStatus::Live, LotStatus::Closing], true) => 'live',
        $status === LotStatus::Draft => 'concept',
        in_array($status, [LotStatus::PendingReview, LotStatus::Scheduled], true) => 'gepland',
        $status === LotStatus::Paused => 'gepauzeerd',
        $status === LotStatus::Sold => 'verkocht',
        default => 'overig',
    };
    $groups = ['live' => 'Live', 'concept' => 'Concept', 'gepland' => 'Gepland', 'gepauzeerd' => 'Gepauzeerd', 'verkocht' => 'Verkocht', 'overig' => 'Overig'];
    $tagTone = ['live' => 'tone-live', 'concept' => 'tone-warn', 'gepland' => 'tone-atlas', 'gepauzeerd' => 'tone-grey', 'verkocht' => 'tone-ok', 'overig' => 'tone-grey'];
    $counts = $lots->countBy(fn ($lot): string => $groupOf($lot->status));
    $bidders = (int) $lots
        ->filter(fn ($lot): bool => $groupOf($lot->status) === 'live')
        ->sum(fn ($lot): int => (int) ($lot->auction?->bidder_count ?? 0));
    $displayTimezone = config('aucvy.display_timezone');

    // De tegels zijn het filter (?status=): geen rij chips met dezelfde tellers eronder.
    $filter = array_key_exists((string) request('status'), $groups) ? (string) request('status') : 'alle';
    $filterUrl = fn (string $key): string => route('dashboard.lots', $filter === $key ? [] : ['status' => $key]);
    // Per status een eigen tabel, in de volgorde van wat nu het meest speelt. Welke cellen een tabel
    // toont hangt van de status af: een concept heeft geen bod, een verkochte kavel geen kijkers meer.
    $tableSpecs = [
        'live' => ['Live', 'Kavels waar nu op geboden kan worden, met het bod naast de waarde-indicatie van Atlas.', ['interest', 'amount', 'value', 'verdict']],
        'gepland' => ['Ingepland', 'Goedgekeurd en klaar om te starten. De waarde-indicatie staat erbij zodra Atlas hem heeft.', ['interest', 'value']],
        'concept' => ['Concepten', 'Nog niet gepubliceerd. De ring toont hoe compleet het concept is.', ['complete']],
        'gepauzeerd' => ['Gepauzeerd', 'Stilgezet: de klok staat stil en er wordt niet geboden.', ['interest', 'amount', 'value']],
        'verkocht' => ['Verkocht', 'Kavels met een koper, met de verkoopprijs naast de waarde-indicatie en de stand van de uitbetaling.', ['amount', 'value', 'verdict']],
        'overig' => ['Niet verkocht of gestopt', 'Kavels zonder koper of die zijn ingetrokken. Je kunt ze opnieuw plaatsen.', ['interest']],
    ];
    $tables = collect($tableSpecs)
        ->filter(fn (array $spec, string $key): bool => $filter === 'alle' || $filter === $key)
        ->map(fn (array $spec, string $key): array => [
            'title' => $spec[0],
            'help' => $spec[1],
            'columns' => $spec[2],
            'lots' => $lots->filter(fn ($lot): bool => $groupOf($lot->status) === $key)->values(),
        ])
        ->filter(fn (array $table): bool => $table['lots']->isNotEmpty());
@endphp

<x-layouts.dashboard title="Mijn kavels" eyebrow="Dashboard" intro="Alles wat je hebt geplaatst, met de stand van elke veiling en wat er nog in de la ligt.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('sell') }}">Kavel plaatsen</a>
        <a class="btn btn-outline-white" href="{{ route('dashboard.import') }}">Importeren</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" label="Live" :value="$counts->get('live', 0)" :sub="$bidders.' '.($bidders === 1 ? 'bieder' : 'bieders').' op je veilingen'" :href="$filterUrl('live')" :active="$filter === 'live'" />
        <x-dashboard.tile tone="green" label="Gepland" :value="$counts->get('gepland', 0)" sub="staat klaar om te starten" :href="$filterUrl('gepland')" :active="$filter === 'gepland'" />
        <x-dashboard.tile tone="orange" label="Concepten" :value="$counts->get('concept', 0)" sub="nog niet gepubliceerd" :href="$filterUrl('concept')" :active="$filter === 'concept'" />
        <x-dashboard.tile tone="purple" label="Verkocht" :value="$counts->get('verkocht', 0)" sub="met een koper" :href="$filterUrl('verkocht')" :active="$filter === 'verkocht'" />
    </x-slot:tiles>

    @if ($lots->isEmpty())
        <x-ui.empty-state
            title="Nog geen kavels"
            body="Plaats je eerste kavel; Ivy helpt met herkennen, schrijven en waarderen.">
            <x-slot:action>
                <x-ui.button :href="route('sell')">Kavel plaatsen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        @if (session('status'))
            <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="d-soft is-err" style="margin:0 0 14px">{{ $errors->first() }}</div>
        @endif

        {{--
            Per status een eigen tabel in een eigen kaart: wat live staat,
            wat ingepland is, de concepten, en daarna wat verkocht of
            gestopt is. Zo loopt een concept niet tussen de lopende veilingen
            door. Elke tabel toont de eerste rijen, de rest zit achter "Meer
            tonen". Zoeken gebeurt in de browser en werkt door alle tabellen
            heen; een tabel zonder treffer verdwijnt. Selecteren voor
            dupliceren of verwijderen staat achter één knop; bulk gaat via
            één formulier om de tabellen heen.
        --}}
        <form method="POST" action="{{ route('dashboard.lots.bulk') }}" x-data="{ selected: [], selecting: false, search: '', found: {} }">
            @csrf
            <div class="d-listbar">
                <p class="d-listbar-count">
                    @if ($filter === 'alle')
                        <b class="tabular">{{ $lots->count() }}</b> {{ $lots->count() === 1 ? 'kavel' : 'kavels' }}
                    @else
                        <b class="tabular">{{ $counts->get($filter, 0) }}</b> {{ $filter === 'concept' ? ($counts->get($filter, 0) === 1 ? 'concept' : 'concepten') : mb_strtolower($groups[$filter]) }}
                        <a class="d-link" href="{{ route('dashboard.lots') }}">Alle {{ $lots->count() }} tonen</a>
                    @endif
                </p>
                <label class="d-listbar-search">
                    <span class="sr-only">Zoek in je kavels</span>
                    <input type="search" x-model="search" placeholder="Zoek in je kavels" autocomplete="off" class="d-input" style="margin:0">
                </label>
                <button type="button" class="d-btn d-btn-secondary d-btn-sm" :aria-pressed="selecting" @click="selecting = ! selecting; if (! selecting) selected = []" x-text="selecting ? 'Klaar' : 'Selecteren'">Selecteren</button>
            </div>

            <div class="bulk-bar" x-show="selected.length" x-cloak>
                <span><b class="tabular" x-text="selected.length"></b> geselecteerd</span>
                <button type="submit" name="action" value="duplicate" class="btn btn-ghost" style="height:38px;font-size:13px">Dupliceren</button>
                <button type="submit" name="action" value="delete_drafts" class="btn btn-ghost" style="height:38px;font-size:13px;color:var(--color-aj-error)"
                        @click="if (! confirm('Alleen concepten worden verwijderd. Doorgaan?')) $event.preventDefault()">Concepten verwijderen</button>
                <button type="button" class="link" @click="selected = []">Niets selecteren</button>
            </div>

            <div class="d-sections d-stack">
                @foreach ($tables as $key => $table)
                    @php
                        $rows = $table['lots'];
                        $columns = $table['columns'];
                        // Eén tabel in beeld toont tien rijen, meerdere tabellen elk vijf.
                        $limit = $filter === 'alle' ? 5 : 10;
                        $shown = $rows->take($limit)->pluck('id')->flip();
                        $listRows = $rows->map(fn ($lot): array => ['id' => $lot->id, 'g' => 'alle', 't' => Str::lower($lot->title.' '.$lot->status->label())])->values();
                        $widths = ['interest' => '170px', 'complete' => '170px', 'amount' => '130px', 'value' => '180px', 'verdict' => '240px'];
                        $template = 'minmax(0,1fr) '.collect($columns)->map(fn (string $column): string => $widths[$column])->implode(' ').' 190px 86px';
                    @endphp
                    <section class="d-card" aria-labelledby="kavels-{{ $key }}"
                             x-data="shortList({ rows: {{ Js::from($listRows) }}, limit: {{ $limit }} })"
                             x-effect="q = search; found['{{ $key }}'] = total"
                             x-show="total > 0">
                        <div class="d-titleline" style="margin-bottom:12px">
                            <h2 id="kavels-{{ $key }}" class="d-h2">{{ $table['title'] }}</h2>
                            <span class="d-count tabular" x-text="total">{{ $rows->count() }}</span>
                            <x-dashboard.help :text="$table['help']" />
                        </div>

                        <div @class(['d-lottable', 'is-wide' => in_array('verdict', $columns, true)]) style="--cols:{{ $template }}">
                            <div class="d-lottable-head" aria-hidden="true">
                                <span>Kavel</span>
                                @foreach ($columns as $column)
                                    <span @class(['num' => $column === 'amount' || $column === 'value'])>{{ ['interest' => 'Interesse', 'complete' => 'Compleet', 'amount' => 'Bedrag', 'value' => 'Waarde volgens Atlas', 'verdict' => 'Uitkomst'][$column] }}</span>
                                @endforeach
                                <span>Wanneer</span>
                                <span></span>
                            </div>
                            @foreach ($rows as $lot)
                                @include('dashboard.partials.lot-row')
                            @endforeach
                        </div>

                        <div class="d-more" x-show="hidden > 0" @if ($rows->count() <= $limit) x-cloak @endif>
                            <button type="button" class="d-btn d-btn-secondary d-btn-sm" @click="all = true">Meer tonen <span class="tabular" x-text="'(' + hidden + ')'"></span></button>
                        </div>
                    </section>
                @endforeach
            </div>

            <p class="feed-empty" x-show="search !== '' && Object.values(found).every((count) => count === 0)" x-cloak>Geen kavels gevonden voor dit zoekwoord. Pas het aan of <button type="button" class="d-link" @click="search = ''">toon alles</button>.</p>
        </form>
    @endif
</x-layouts.dashboard>
