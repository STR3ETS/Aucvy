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
            <div x-data="shortList({ rows: {{ Js::from($listRows) }}, filter: '{{ $filter }}', limit: {{ $listLimit }} })">
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

            <div class="d-stack-tight">
                @foreach ($lots as $lot)
                    @php
                        $group = $groupOf($lot->status);
                        $url = match (true) {
                            $lot->status === LotStatus::Draft => route('sell', ['concept' => $lot->id]),
                            $lot->status->isPublic() => route('lots.show', $lot),
                            default => null,
                        };
                        $dossierUrl = $lot->status === LotStatus::Draft ? $url : route('dashboard.lots.show', $lot);
                        $auction = $lot->auction;
                        $interest = $lot->status === LotStatus::Draft ? null : \App\Lots\Interest\Interest::forBuyer($lot);
                        $metaParts = ['Kavel #'.$lot->public_id];
                        if ($auction !== null) {
                            if ($lot->sale_type === SaleType::Dutch) {
                                $metaParts[] = 'afslag';
                            }
                            $metaParts[] = $auction->bidder_count.' '.($auction->bidder_count === 1 ? 'bieder' : 'bieders');
                            if (! $auction->isClosed()) {
                                $metaParts[] = 'sluit '.$auction->ends_at->timezone($displayTimezone)->diffForHumans();
                            } elseif ($lot->order?->payout !== null) {
                                $metaParts[] = mb_strtolower($lot->order->payout->status->label());
                            }
                        } elseif ($lot->status === LotStatus::Draft) {
                            $metaParts = [$lot->completeness_score.'% compleet', 'bewerkt '.$lot->updated_at->timezone($displayTimezone)->diffForHumans()];
                        } else {
                            $metaParts[] = 'nog geen veiling gepland';
                        }
                        [$amountLabel, $amount] = match (true) {
                            $lot->status === LotStatus::Draft => ['', ''],
                            $lot->status === LotStatus::Sold && $lot->order !== null => ['Verkocht voor', Money::format($lot->order->hammer_cents)],
                            $auction !== null && $lot->sale_type === SaleType::Dutch => ['Prijs nu', Money::format($lot->currentPriceCents())],
                            $auction !== null && $lot->sale_type === SaleType::BuyNow => ['Vaste prijs', Money::format($lot->currentPriceCents())],
                            $auction !== null => ['Huidig bod', Money::format($lot->currentPriceCents())],
                            default => ['', ''],
                        };
                    @endphp
                    <article class="d-listrow" x-show="ids[{{ $lot->id }}]" @unless ($firstShown->has($lot->id)) x-cloak @endunless>
                        <label class="pick" x-show="selecting" x-cloak>
                            <input type="checkbox" name="lots[]" value="{{ $lot->id }}" x-model="selected" aria-label="Selecteer {{ $lot->title }}">
                        </label>
                        <a href="{{ $dossierUrl ?? route('dashboard.lots') }}" aria-label="{{ $lot->title }}" style="flex:0 0 auto">
                            @if ($lot->cover_path)
                                <img class="d-listrow-thumb" src="{{ $lot->cover_path }}" alt="" loading="lazy">
                            @else
                                <span class="d-listrow-thumb" style="display:flex;align-items:center;justify-content:center;color:#9aa3b5" aria-hidden="true"><x-v4.icon name="image" :size="24" stroke="1.6" /></span>
                            @endif
                        </a>
                        <div class="d-listrow-body">
                            <strong><a href="{{ $dossierUrl }}">{{ $lot->title ?: 'Concept zonder titel' }}</a></strong>
                            <span class="d-listrow-meta">{{ implode(' · ', $metaParts) }}</span>
                            @if ($interest !== null)
                                <div class="d-counts">
                                    <span title="kijkers"><x-v4.icon name="eye" :size="14" stroke="2" />{{ number_format($interest['viewers'], 0, ',', '.') }}</span>
                                    <span title="keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" />{{ $interest['saved'] }}</span>
                                    <span title="keer gedeeld"><x-v4.icon name="share" :size="14" stroke="2" />{{ $interest['shared'] }}</span>
                                </div>
                            @endif
                            <div class="d-listrow-tags">
                                <span class="d-tag {{ $tagTone[$group] }}">{{ $lot->status->label() }}</span>
                                @if ($lot->is_hot)
                                    <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                @endif
                            </div>
                        </div>
                        <div class="d-listrow-right">
                            @if ($amount !== '')
                                <b>{{ $amount }}</b>
                                <span>{{ $amountLabel }}</span>
                            @endif
                            @if ($lot->status === LotStatus::Draft)
                                <a href="{{ $url }}" class="d-btn d-btn-secondary d-btn-sm">{{ __('dashboard.actions.continue_draft') }}</a>
                            @elseif (in_array($lot->status, [LotStatus::Unsold, LotStatus::Cancelled], true))
                                <button type="submit" class="d-btn d-btn-secondary d-btn-sm" formaction="{{ route('dashboard.lots.duplicate', ['lot' => $lot->id, 'relist' => 1]) }}" formmethod="POST">Opnieuw plaatsen</button>
                            @elseif (in_array($lot->status, [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing, LotStatus::Paused], true))
                                <a href="{{ route('dashboard.lots.edit', $lot) }}" class="d-btn d-btn-secondary d-btn-sm">{{ $lot->status === LotStatus::Paused ? 'Hervatten' : 'Bewerken' }}</a>
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
