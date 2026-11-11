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
    // De rijen voor de lijst in de browser: de groep en de zoektekst (titel en status).
    $listRows = $lots->map(fn ($lot): array => ['id' => $lot->id, 'g' => $groupOf($lot->status), 't' => Str::lower($lot->title.' '.$lot->status->label())])->values();
    $listLimit = 10;
    // Wat bij het laden al open staat, zodat er niets verspringt.
    $inFilter = $listRows->filter(fn (array $row): bool => $filter === 'alle' || $row['g'] === $filter);
    $firstShown = $inFilter->take($listLimit)->pluck('id')->flip();
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
            Zoeken gebeurt in de browser: de lijst is al geladen. De eerste tien
            rijen staan open, de rest achter "Meer tonen". Selecteren voor
            dupliceren of verwijderen staat achter één knop; bulk gaat via één
            formulier om de lijst heen.
        --}}
        <form method="POST" action="{{ route('dashboard.lots.bulk') }}" x-data="{ selected: [], selecting: false }">
            @csrf
            <div class="d-card" x-data="shortList({ rows: {{ Js::from($listRows) }}, filter: '{{ $filter }}', limit: {{ $listLimit }} })">
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
                    <input type="search" x-model="q" placeholder="Zoek in je kavels" autocomplete="off" class="d-input" style="margin:0">
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

            {{--
                Eén tabel in één kaart: de koppen staan er één keer boven en
                elke kavel is één regel. Links wat het is en de status, dan de
                interesse (bij een concept hoe compleet het is, als ring), het
                bedrag, wanneer er iets gebeurt en rechts de acties als
                pictogram. Op een smal scherm staat een regel onder elkaar.
            --}}
            <div class="d-lottable">
                <div class="d-lottable-head" aria-hidden="true">
                    <span>Kavel</span>
                    <span>Status</span>
                    <span>Interesse</span>
                    <span class="num">Bedrag</span>
                    <span>Wanneer</span>
                    <span></span>
                </div>
                @foreach ($lots as $lot)
                    @php
                        $group = $groupOf($lot->status);
                        $isDraft = $lot->status === LotStatus::Draft;
                        $siteUrl = $lot->status->isPublic() ? route('lots.show', $lot) : null;
                        $dossierUrl = $isDraft ? route('sell', ['concept' => $lot->id]) : route('dashboard.lots.show', $lot);
                        $auction = $lot->auction;
                        $interest = $isDraft ? null : \App\Lots\Interest\Interest::forBuyer($lot);
                        // Wat het is: het kavelnummer en de verkoopvorm; een concept heeft nog geen nummer dat ertoe doet.
                        $kind = $isDraft ? 'Nog niet gepubliceerd' : 'Kavel #'.$lot->public_id.($lot->sale_type !== null ? ' · '.$lot->sale_type->label() : '');
                        // Wanneer er iets gebeurt: de sluittijd, de uitbetaling of wanneer je er het laatst aan werkte.
                        $moment = match (true) {
                            $isDraft => 'bewerkt '.$lot->updated_at->timezone($displayTimezone)->diffForHumans(),
                            $auction !== null && ! $auction->isClosed() => $auction->closesText(),
                            $auction !== null && $lot->order?->payout !== null => 'uitbetaling: '.mb_strtolower($lot->order->payout->status->label()),
                            $auction !== null => 'gesloten op '.rtrim($auction->ends_at->timezone($displayTimezone)->isoFormat('D MMM'), '.'),
                            default => 'nog geen veiling gepland',
                        };
                        [$amountLabel, $amount] = match (true) {
                            $isDraft => ['', ''],
                            $lot->status === LotStatus::Sold && $lot->order !== null => ['Verkocht voor', Money::format($lot->order->hammer_cents)],
                            $auction !== null && $lot->sale_type === SaleType::Dutch => ['Prijs nu', Money::format($lot->currentPriceCents())],
                            $auction !== null && $lot->sale_type === SaleType::BuyNow => ['Vaste prijs', Money::format($lot->currentPriceCents())],
                            $auction !== null => ['Huidig bod', Money::format($lot->currentPriceCents())],
                            default => ['', ''],
                        };
                        $complete = (int) $lot->completeness_score;
                    @endphp
                    <article class="d-lotrow" x-show="ids[{{ $lot->id }}]" @unless ($firstShown->has($lot->id)) x-cloak @endunless>
                        <div class="d-lotrow-lot">
                            <label class="pick" x-show="selecting" x-cloak>
                                <input type="checkbox" name="lots[]" value="{{ $lot->id }}" x-model="selected" aria-label="Selecteer {{ $lot->title }}">
                            </label>
                            <a class="thumb" href="{{ $dossierUrl }}" tabindex="-1" aria-hidden="true">
                                @if ($lot->cover_path)
                                    <img src="{{ $lot->cover_path }}" alt="" loading="lazy">
                                @else
                                    <x-v4.icon name="image" :size="20" stroke="1.6" />
                                @endif
                            </a>
                            <div class="text">
                                <strong><a href="{{ $dossierUrl }}">{{ $lot->title ?: 'Concept zonder titel' }}</a></strong>
                                <span>{{ $kind }}</span>
                            </div>
                        </div>

                        <div class="d-lotrow-facts">
                            <span class="cell is-status">
                                <span class="d-tag {{ $tagTone[$group] }}">{{ $lot->status->label() }}</span>
                                @if ($lot->is_hot && ! $isDraft)
                                    <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                @endif
                            </span>

                            <span class="cell is-interest">
                                @if ($isDraft)
                                    <span class="d-ring is-sm is-{{ $complete >= 80 ? 'below' : 'within' }}" role="img" aria-label="{{ $complete }}% compleet">
                                        <svg viewBox="0 0 100 100" aria-hidden="true">
                                            <circle class="track" cx="50" cy="50" r="38"></circle>
                                            <circle class="value" cx="50" cy="50" r="38" pathLength="100" stroke-dasharray="{{ min(100, max(4, $complete)) }} 100"></circle>
                                        </svg>
                                    </span>
                                    <span><b class="tabular">{{ $complete }}%</b> compleet</span>
                                @else
                                    <span class="d-counts">
                                        <span title="kijkers"><x-v4.icon name="eye" :size="14" stroke="2" />{{ number_format($interest['viewers'], 0, ',', '.') }}</span>
                                        <span title="keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" />{{ $interest['saved'] }}</span>
                                        <span title="bieders"><x-v4.icon name="gavel" :size="14" stroke="2" />{{ (int) ($auction?->bidder_count ?? 0) }}</span>
                                    </span>
                                @endif
                            </span>

                            <span class="cell is-amount">
                                @if ($amount !== '')
                                    <b class="tabular">{{ $amount }}</b>
                                    <small>{{ $amountLabel }}</small>
                                @endif
                            </span>

                            <span class="cell is-moment">{{ ucfirst($moment) }}</span>
                        </div>

                        {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                        <div class="d-lotrow-actions">
                            @if ($siteUrl !== null)
                                <a href="{{ $siteUrl }}" class="d-iconbtn" data-tip="Bekijk op de site" aria-label="{{ $lot->title }} bekijken op de site"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                            @endif
                            @if ($isDraft)
                                <a href="{{ $dossierUrl }}" class="d-iconbtn" data-tip="{{ __('dashboard.actions.continue_draft') }}" aria-label="{{ __('dashboard.actions.continue_draft') }}: {{ $lot->title ?: 'concept zonder titel' }}"><x-v4.icon name="pencil" :size="17" stroke="2" /></a>
                            @elseif (in_array($lot->status, [LotStatus::Unsold, LotStatus::Cancelled], true))
                                <button type="submit" class="d-iconbtn" data-tip="Opnieuw plaatsen" aria-label="{{ $lot->title }} opnieuw plaatsen" formaction="{{ route('dashboard.lots.duplicate', ['lot' => $lot->id, 'relist' => 1]) }}" formmethod="POST"><x-v4.icon name="refresh" :size="17" stroke="2" /></button>
                            @elseif (in_array($lot->status, [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing, LotStatus::Paused], true))
                                <a href="{{ route('dashboard.lots.edit', $lot) }}" class="d-iconbtn" data-tip="{{ $lot->status === LotStatus::Paused ? 'Hervatten' : 'Bewerken' }}" aria-label="{{ $lot->title }} {{ $lot->status === LotStatus::Paused ? 'hervatten' : 'bewerken' }}"><x-v4.icon :name="$lot->status === LotStatus::Paused ? 'play' : 'pencil'" :size="17" stroke="2" /></a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <p class="feed-empty" x-show="total === 0" x-cloak>Geen kavels gevonden. Pas je zoekwoord aan of <a class="d-link" href="{{ route('dashboard.lots') }}">toon alle kavels</a>.</p>
            <div class="d-more" x-show="hidden > 0" @if ($inFilter->count() <= $listLimit) x-cloak @endif>
                <button type="button" class="d-btn d-btn-secondary d-btn-sm" @click="all = true">Meer tonen <span class="tabular" x-text="'(' + hidden + ')'"></span></button>
            </div>
            </div>
        </form>
    @endif
</x-layouts.dashboard>
