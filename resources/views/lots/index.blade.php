@use('App\Lots\LotFilters')

<x-layouts.public title="Veilingen">
    <x-slot:actions>
        @auth
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.button type="submit" variant="text">Uitloggen</x-ui.button>
            </form>
        @endauth
        @guest
            <x-ui.button variant="text" :href="route('login')">Inloggen</x-ui.button>
        @endguest
    </x-slot:actions>

    {{-- Het stippenveld van de hero als paginalaag: donkergrijs en zwak
         op de lichte achtergrond, achter alle kaarten. --}}
    <canvas class="page-dots" x-data="dotField({ color: '#0e1526', strength: 0.25 })"
            aria-hidden="true"></canvas>

    <h1 class="text-2xl font-extrabold tracking-tight">Veilingen</h1>

    {{--
        Alles hierbinnen wisselt zonder harde reload (lots-index.js): links en
        formulieren blijven gewone GET's, fetch haalt dezelfde pagina op en
        alleen dit blok wordt vervangen.
    --}}
    <div id="lots-content">

    {{-- Zoeken ---------------------------------------------------------- --}}
    <form method="GET" action="{{ route('lots.index') }}" class="mt-4 flex gap-2">
        @foreach ($filters->toQuery(['q' => null]) as $key => $value)
            @foreach ((array) $value as $item)
                <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $item }}">
            @endforeach
        @endforeach

        <label class="flex-1">
            <span class="sr-only">Zoeken</span>
            <input type="search" name="q" value="{{ $filters->query }}"
                   placeholder="Waar ben je naar op zoek?"
                   class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-4"
                   style="min-height: var(--spacing-cta)">
        </label>
        <x-ui.button type="submit">Zoeken</x-ui.button>
    </form>

    {{--
        De geïnterpreteerde zoekvraag als corrigeerbare lezing (hfst. 13.2).
        Correcties zijn links met aangepaste filters - nooit een nieuwe
        LLM-call. Zonder AI of zonder uitkomst: geen banner, geen blokkade.
    --}}
    @if ($filters->query)
        <div x-data="searchInterpret({{ \Illuminate\Support\Js::from($filters->query) }}, {{ config('aucvy.ai.enabled') ? 'true' : 'false' }})"
             x-show="reading" x-cloak
             class="mt-3 flex flex-wrap items-center gap-2 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm"
             aria-live="polite">
            <x-ui.agent-avatar agent="ivy" class="size-6 shrink-0" />
            <span x-text="reading?.output?.sentence"></span>

            {{-- Elke chip is een onderdeel van de lezing; het kruisje zoekt
                 mét de rest maar zonder dit onderdeel. --}}
            <template x-for="chip in chips" :key="chip.label">
                <a :href="applyUrl(chip.remove)"
                   class="rounded-aj-sm border border-aj-border-strong px-2 py-1 text-xs font-bold"
                   :aria-label="'Zoek zonder ' + chip.label">
                    <span x-text="chip.label"></span> <span aria-hidden="true">×</span>
                </a>
            </template>

            <a x-show="chips.length" :href="applyUrl({})"
               class="btn btn-primary" style="height:36px;font-size:13px">Zo zoeken</a>
        </div>
    @endif

    {{--
        Snelfilters als horizontaal scrollende chips. Op mobiel vult een
        volledig filterblok anders het hele eerste scherm (hfst. 12).
    --}}
    <nav aria-label="Snelfilters" class="mt-4 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <ul class="flex gap-2">
            @foreach (LotFilters::quickOptions() as $option)
                @php $count = $facets['quick'][$option] ?? 0; @endphp
                <li class="shrink-0">
                    <a href="{{ route('lots.index', $filters->toQuery(['toon' => $option === LotFilters::QUICK_ALL ? null : $option])) }}"
                       @class([
                           'flex items-center gap-2 rounded-full px-4 text-sm font-semibold',
                           'bg-aj-navy text-white' => $filters->quick === $option,
                           'border border-aj-border-strong bg-aj-surface' => $filters->quick !== $option,
                           'opacity-40' => $count === 0 && $filters->quick !== $option,
                       ])
                       style="min-height: var(--spacing-cta)">
                        {{ __('lots.quick.'.$option) }}
                        <span class="tabular text-xs opacity-70">{{ $count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="mt-6 lg:grid lg:grid-cols-[220px_1fr] lg:gap-8 lg:items-start">

        {{-- Filters: op desktop een kolom, op mobiel achter één knop -------- --}}
        <aside class="hidden lg:block">
            <h2 class="text-sm font-extrabold">{{ __('lots.filters.heading') }}</h2>
            <x-lot.filter-panel class="mt-3" :$filters :$facets :$categories />
        </aside>

        <div x-data="{ sheet: false }" class="lg:hidden">
            <button type="button" @click="sheet = true"
                    class="flex w-full items-center justify-center gap-2 rounded-aj-sm
                           border border-aj-border-strong bg-aj-surface text-sm font-semibold"
                    style="min-height: var(--spacing-cta)">
                {{ __('lots.filters.show') }}
                @if ($filters->activeCount() > 0)
                    <span class="tabular rounded-full bg-aj-blue px-2 py-0.5 text-xs text-white">
                        {{ $filters->activeCount() }}
                    </span>
                @endif
            </button>

            {{-- Bottom sheet met sluitgebaar én sluitknop (hfst. 10). --}}
            <div x-show="sheet" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true"
                 aria-label="{{ __('lots.filters.heading') }}"
                 @keydown.escape.window="sheet = false">
                <div class="absolute inset-0 bg-aj-navy/40" @click="sheet = false"></div>

                <div class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto rounded-t-aj-l bg-aj-surface p-5"
                     x-trap.noscroll="sheet"
                     style="padding-bottom: max(1.25rem, env(safe-area-inset-bottom))">
                    <div class="flex items-center justify-between">
                        <h2 class="font-extrabold">{{ __('lots.filters.heading') }}</h2>
                        <button type="button" @click="sheet = false"
                                class="rounded-aj-sm px-3 text-sm font-semibold"
                                style="min-height: var(--spacing-cta)">Sluiten</button>
                    </div>

                    <x-lot.filter-panel class="mt-4" :$filters :$facets :$categories />
                </div>
            </div>
        </div>

        {{-- Resultaten ------------------------------------------------------ --}}
        <div class="mt-6 lg:mt-0">
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-sm text-aj-muted">
                    {{ trans_choice('lots.results.count', $results->total(), ['count' => $results->total()]) }}
                    @if ($filters->activeCount() > 0)
                        · {{ trans_choice('lots.filters.active', $filters->activeCount(), ['count' => $filters->activeCount()]) }}
                    @endif
                </p>

                <form method="GET" action="{{ route('lots.index') }}" class="ml-auto"
                      x-data @change="$el.requestSubmit()">
                    @foreach ($filters->toQuery(['sorteer' => null]) as $key => $value)
                        @foreach ((array) $value as $item)
                            <input type="hidden" name="{{ $key }}{{ is_array($value) ? '[]' : '' }}" value="{{ $item }}">
                        @endforeach
                    @endforeach

                    <label class="flex items-center gap-2 text-sm">
                        <span class="sr-only">{{ __('lots.sort.label') }}</span>
                        <select name="sorteer"
                                class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                                style="min-height: var(--spacing-cta)">
                            @foreach (LotFilters::sortOptions() as $option)
                                {{-- Relevantie zonder zoekterm is betekenisloos. --}}
                                @continue($option === LotFilters::SORT_RELEVANCE && $filters->query === null)
                                <option value="{{ $option }}" @selected($filters->sort === $option)>
                                    {{ __('lots.sort.'.$option) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </form>
            </div>

            @if ($results->isEmpty())
                {{--
                    Nooit een doodlopende pagina: één concrete verbreding, plus
                    de mogelijkheid een alert te zetten. Dat laatste is geen
                    registratiemuur (hfst. 13.2).
                --}}
                <div class="mt-4 rounded-aj-s border border-aj-border-strong bg-aj-surface p-6">
                    <p class="font-semibold">{{ __('lots.empty.title') }}</p>

                    @if ($suggestion)
                        <p class="mt-2 text-sm text-aj-muted">
                            {{ __('lots.empty.suggestion', [
                                'filter' => $suggestion['label'],
                                'count' => $suggestion['count'],
                            ]) }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-ui.button :href="route('lots.index', $suggestion['filters']->toQuery())">
                                {{ __('lots.empty.show_those', ['count' => $suggestion['count']]) }}
                            </x-ui.button>
                            <x-ui.button variant="secondary" :href="route('lots.index')">
                                {{ __('lots.filters.clear') }}
                            </x-ui.button>
                        </div>
                    @else
                        <p class="mt-2 text-sm text-aj-muted">{{ __('lots.empty.nothing') }}</p>
                    @endif
                </div>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($results as $lot)
                        <x-lot.card :lot="$lot" />
                    @endforeach
                </div>

                <div class="mt-6">{{ $results->links() }}</div>
            @endif
        </div>
    </div>

    </div>
</x-layouts.public>
