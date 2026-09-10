@use('App\Enums\LotCondition')
@use('App\Enums\SaleType')

@props(['filters', 'facets', 'categories'])

{{--
    Elke wijziging verzendt het formulier, zodat de filters in de URL landen.
    Deelbaar, terugknop werkt, en de servercache kan erop werken (hfst. 13.2).

    Een optie die nul oplevert wordt uitgeschakeld getoond in plaats van
    verborgen - dat scheelt doodlopende klikken.
--}}
<form method="GET" action="{{ route('lots.index') }}"
      x-data
      @change="$el.requestSubmit()"
      {{ $attributes->merge(['class' => 'space-y-5']) }}>

    {{-- Zoekterm en snelfilter meenemen, anders vallen ze weg bij het filteren. --}}
    @if ($filters->query)
        <input type="hidden" name="q" value="{{ $filters->query }}">
    @endif
    @if ($filters->quick !== \App\Lots\LotFilters::QUICK_ALL)
        <input type="hidden" name="toon" value="{{ $filters->quick }}">
    @endif
    @if ($filters->sort !== \App\Lots\LotFilters::SORT_ENDING_SOON)
        <input type="hidden" name="sorteer" value="{{ $filters->sort }}">
    @endif

    <fieldset>
        <legend class="text-sm font-extrabold">Categorie</legend>
        <label class="mt-2 block">
            <span class="sr-only">Categorie</span>
            <select name="categorie" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                    style="min-height: var(--spacing-cta)">
                <option value="">Alle categorieën</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->slug }}" @selected($filters->category === $category->slug)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </label>
    </fieldset>

    <fieldset>
        <legend class="text-sm font-extrabold">Verkoopvorm</legend>
        <div class="mt-2 space-y-1">
            @foreach (SaleType::cases() as $type)
                @php $count = $facets['sale_type'][$type->value] ?? 0; @endphp
                <label @class(['flex items-center gap-2 text-sm', 'opacity-40' => $count === 0])>
                    <input type="checkbox" name="vorm[]" value="{{ $type->value }}"
                           @checked(in_array($type->value, $filters->saleTypes, true))
                           @disabled($count === 0)
                           class="rounded">
                    <span>{{ $type->label() }}</span>
                    <span class="tabular ml-auto text-xs text-aj-muted">{{ $count }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend class="text-sm font-extrabold">Conditie</legend>
        <div class="mt-2 space-y-1">
            @foreach (LotCondition::cases() as $condition)
                @php $count = $facets['condition'][$condition->value] ?? 0; @endphp
                <label @class(['flex items-center gap-2 text-sm', 'opacity-40' => $count === 0])>
                    <input type="checkbox" name="conditie[]" value="{{ $condition->value }}"
                           @checked(in_array($condition->value, $filters->conditions, true))
                           @disabled($count === 0)
                           class="rounded">
                    <span>{{ $condition->label() }}</span>
                    <span class="tabular ml-auto text-xs text-aj-muted">{{ $count }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    @if (! empty($facets['province']))
        <fieldset>
            <legend class="text-sm font-extrabold">Provincie</legend>
            <div class="mt-2 space-y-1">
                @foreach ($facets['province'] as $province => $count)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="provincie[]" value="{{ $province }}"
                               @checked(in_array($province, $filters->provinces, true))
                               class="rounded">
                        <span>{{ $province }}</span>
                        <span class="tabular ml-auto text-xs text-aj-muted">{{ $count }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endif

    <fieldset>
        <legend class="text-sm font-extrabold">Prijs</legend>
        <div class="mt-2 flex items-center gap-2">
            <label class="flex-1">
                <span class="sr-only">Vanaf</span>
                <input type="number" name="vanaf" min="0" placeholder="vanaf €"
                       value="{{ $filters->priceMinCents !== null ? intdiv($filters->priceMinCents, 100) : '' }}"
                       class="tabular w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                       style="min-height: var(--spacing-cta)">
            </label>
            <label class="flex-1">
                <span class="sr-only">Tot</span>
                <input type="number" name="tot" min="0" placeholder="tot €"
                       value="{{ $filters->priceMaxCents !== null ? intdiv($filters->priceMaxCents, 100) : '' }}"
                       class="tabular w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                       style="min-height: var(--spacing-cta)">
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend class="sr-only">Extra voorwaarden</legend>
        <label @class(['flex items-center gap-2 text-sm', 'opacity-40' => ($facets['toggles']['kijkdag'] ?? 0) === 0])>
            <input type="checkbox" name="kijkdag" value="1" @checked($filters->viewingOnly)
                   @disabled(($facets['toggles']['kijkdag'] ?? 0) === 0) class="rounded">
            <span>Kijkdag mogelijk</span>
            <span class="tabular ml-auto text-xs text-aj-muted">{{ $facets['toggles']['kijkdag'] ?? 0 }}</span>
        </label>
        <label @class(['mt-1 flex items-center gap-2 text-sm', 'opacity-40' => ($facets['toggles']['transport'] ?? 0) === 0])>
            <input type="checkbox" name="transport" value="1" @checked($filters->transportOnly)
                   @disabled(($facets['toggles']['transport'] ?? 0) === 0) class="rounded">
            <span>Transport beschikbaar</span>
            <span class="tabular ml-auto text-xs text-aj-muted">{{ $facets['toggles']['transport'] ?? 0 }}</span>
        </label>
    </fieldset>

    {{-- Zonder JavaScript verzendt deze knop het formulier alsnog. --}}
    <noscript>
        <x-ui.button type="submit" class="w-full justify-center">Filters toepassen</x-ui.button>
    </noscript>

    @if ($filters->activeCount() > 0)
        <a href="{{ route('lots.index') }}" class="block text-sm text-aj-blue-text underline">
            {{ __('lots.filters.clear') }}
        </a>
    @endif
</form>
