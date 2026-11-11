@use('App\Enums\SaleState')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    // De groepen van de filterchips uit het ontwerp van november.
    $groupOf = fn (SaleState $state): string => match ($state) {
        SaleState::Live => 'lopend',
        SaleState::Scheduled => 'gepland',
        SaleState::Draft => 'concept',
        default => 'afgerond',
    };
    $groups = ['lopend' => 'Lopend', 'gepland' => 'Gepland', 'concept' => 'Concept', 'afgerond' => 'Afgerond'];
    $counts = $sales->countBy(fn ($sale): string => $groupOf($sale->state()));
@endphp

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
        <div x-data="{ filter: 'alles' }" class="d-stack" style="gap:18px">
            {{-- Alleen de groepen die je hebt, en geen filter als er niets te kiezen valt. --}}
            @if ($counts->count() > 1)
                <div class="d-chips" role="tablist" aria-label="Filter veilingen">
                    <button type="button" role="tab" class="d-chip" :class="filter === 'alles' && 'is-on'" :aria-selected="filter === 'alles'" @click="filter = 'alles'">Alles</button>
                    @foreach ($groups as $key => $label)
                        @if ($counts->get($key, 0) > 0)
                            <button type="button" role="tab" class="d-chip" :class="filter === '{{ $key }}' && 'is-on'" :aria-selected="filter === '{{ $key }}'" @click="filter = '{{ $key }}'">{{ $label }} <span class="d-muted tabular" style="font-weight:700">{{ $counts->get($key) }}</span></button>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="d-salegrid">
                {{-- Lopend eerst, dan gepland, concept en afgerond: wat nu aandacht vraagt staat bovenaan. --}}
                @foreach ($sales->sortBy(fn ($sale): int => array_search($groupOf($sale->state()), array_keys($groups), true))->values() as $sale)
                    @php
                        $state = $sale->state();
                        $group = $groupOf($state);
                        $summary = $summaries[$sale->id];
                        $kavels = $sale->lots_count.' '.($sale->lots_count === 1 ? 'kavel' : 'kavels');
                        $biedingen = $summary['bids'].' '.($summary['bids'] === 1 ? 'bieding' : 'biedingen');

                        [$tagTone, $tagLabel] = match ($state) {
                            SaleState::Live => ['tone-ok', 'Lopend · sluit '.$sale->ends_at->timezone($displayTimezone)->diffForHumans()],
                            SaleState::Scheduled => ['tone-atlas', 'Gepland'],
                            SaleState::Draft => ['tone-warn', 'Concept'],
                            SaleState::Cancelled => ['tone-live', 'Geannuleerd'],
                            default => ['tone-grey', 'Afgerond'],
                        };
                        $line = match ($state) {
                            SaleState::Scheduled => $kavels.' · start '.$sale->ends_at->timezone($displayTimezone)->isoFormat('D MMM YYYY'),
                            SaleState::Draft => $kavels.' · concept opgeslagen',
                            default => $kavels.' · '.$biedingen,
                        };
                        [$amountLabel, $button] = match ($state) {
                            SaleState::Live => [$summary['bids'] > 0 ? 'Huidig totaal' : 'Nog geen biedingen', 'Beheren'],
                            SaleState::Scheduled => ['Nog geen biedingen', 'Bewerken'],
                            SaleState::Draft => ['Nog niet gepubliceerd', 'Verder'],
                            SaleState::Cancelled => ['Geannuleerd', 'Bekijk'],
                            default => ['Eindtotaal', 'Bekijk'],
                        };
                    @endphp
                    <article class="d-salecard" data-group="{{ $group }}" x-show="filter === 'alles' || filter === $el.dataset.group">
                        <a href="{{ route('dashboard.sales.edit', $sale) }}" class="d-salecard-cover" aria-label="{{ $sale->title }}">
                            @if ($summary['cover'])
                                <img src="{{ $summary['cover'] }}" alt="" loading="lazy">
                            @else
                                <span aria-hidden="true"><x-v4.icon name="image" :size="28" stroke="1.6" /></span>
                            @endif
                        </a>
                        <div class="d-salecard-body">
                            <div class="d-task-tags">
                                <span class="d-tag {{ $tagTone }}" style="padding:3px 9px">{{ $tagLabel }}</span>
                                @if ($summary['hot'] && $state === SaleState::Live)
                                    <span class="d-tag tone-hot" style="padding:3px 9px"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                @endif
                            </div>
                            <h2><a href="{{ route('dashboard.sales.edit', $sale) }}">{{ $sale->title }}</a></h2>
                            <div class="d-muted">{{ $line }}</div>
                            @if ($state !== SaleState::Draft)
                                <div class="d-counts">
                                    <span title="{{ $summary['viewers'] }} kijkers"><x-v4.icon name="eye" :size="14" stroke="2" /><span aria-hidden="true">{{ number_format($summary['viewers'], 0, ',', '.') }}</span><span class="sr-only">{{ $summary['viewers'] }} kijkers</span></span>
                                    <span title="{{ $summary['saved'] }} keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" /><span aria-hidden="true">{{ $summary['saved'] }}</span><span class="sr-only">{{ $summary['saved'] }} keer opgeslagen</span></span>
                                    <span title="{{ $summary['shared'] }} keer gedeeld"><x-v4.icon name="share" :size="14" stroke="2" /><span aria-hidden="true">{{ $summary['shared'] }}</span><span class="sr-only">{{ $summary['shared'] }} keer gedeeld</span></span>
                                </div>
                            @endif
                            <div class="d-salecard-foot">
                                <div>
                                    <b>{{ Money::format($summary['total_cents']) }}</b>
                                    <span>{{ $amountLabel }}</span>
                                </div>
                                <div class="d-chips" style="gap:6px;flex-wrap:nowrap">
                                    @if ($sale->isPublished() && in_array($state, [SaleState::Live, SaleState::Scheduled], true))
                                        <a class="d-link" href="{{ route('sales.show', $sale) }}" style="align-self:center">Bekijk</a>
                                    @endif
                                    <a class="d-btn d-btn-secondary d-btn-sm d-btn-sq" href="{{ in_array($state, [SaleState::Closed, SaleState::Cancelled], true) && $sale->isPublished() ? route('sales.show', $sale) : route('dashboard.sales.edit', $sale) }}">{{ $button }}</a>
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.dashboard>
