@use('App\Support\Money\Money')

{{--
    Het dashboard als werkplek (hfst. 13.5): eerst wat je snel kunt doen
    (bieden, verkopen, de cijfers), dan één lijst met taken, meldingen en
    tickets, dan het team en pas daarna de analyse. De tegels in de band zijn
    de samenvatting, niet de cijferblokken.
--}}
<x-layouts.dashboard title="Dashboard" :heading="$greeting.', '.$firstName" eyebrow="Dashboard" :intro="$stats['summary']">
    {{-- Eén dashboard. De rol (kopen of verkopen) volgt uit wat je doet:
         wie gepubliceerde kavels heeft, ziet de verkoperscijfers. De knoppen
         Bieden en Verkopen in de tegelrij zijn de ingang, geen schakelaar. --}}

    <x-slot:tiles>
        {{-- De twee dingen die je hier het vaakst komt doen, altijd bovenaan. --}}
        <div class="action-stack">
            <a class="action-card is-bid" href="{{ route('lots.index') }}">
                <span class="ac-icon" aria-hidden="true"><x-v4.icon name="gavel" :size="18" stroke="2.2" /></span>
                <span class="ac-text"><b>Bieden</b><span>Bekijk veilingen</span></span>
                <span class="ac-go" aria-hidden="true"><x-v4.icon name="arrow-right" :size="16" stroke="2.4" /></span>
            </a>
            <a class="action-card is-sell" href="{{ route('sell') }}">
                <span class="ac-icon" aria-hidden="true"><x-v4.icon name="upload" :size="18" stroke="2.2" /></span>
                <span class="ac-text"><b>Verkopen</b><span>Kavel plaatsen</span></span>
                <span class="ac-go" aria-hidden="true"><x-v4.icon name="arrow-right" :size="16" stroke="2.4" /></span>
            </a>
        </div>
        @foreach ($stats['tiles'] as $tile)
            <x-dashboard.tile :tone="$tile['tone']" :icon="$tile['icon']" :label="$tile['label']"
                              :value="$tile['value']" :sub="$tile['sub']" :href="$tile['href']" />
        @endforeach
    </x-slot:tiles>

    <div x-data="{ agent: null }">

        @if ($isNew)
            {{-- Nieuwe gebruikers zien startstappen in plaats van lege blokken. --}}
            <section class="dash-section rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Aan de slag">
                <h2 class="text-lg font-extrabold">{{ __('dashboard.start.title') }}</h2>
                <p class="mt-1 text-sm text-aj-muted">{{ __('dashboard.start.intro') }}</p>
                <ol class="mt-4 space-y-3 text-sm">
                    <li class="flex items-start gap-3">
                        <span class="tabular font-extrabold text-aj-blue-text">1</span>
                        <span>{{ __('dashboard.start.step_sell') }}
                            <a href="{{ route('sell') }}" class="font-bold text-aj-blue-text underline">Kavel plaatsen</a></span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="tabular font-extrabold text-aj-blue-text">2</span>
                        <span>{{ __('dashboard.start.step_browse') }}
                            <a href="{{ route('lots.index') }}" class="font-bold text-aj-blue-text underline">Naar de veilingen</a></span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="tabular font-extrabold text-aj-blue-text">3</span>
                        <span>{{ __('dashboard.start.step_bid') }}</span>
                    </li>
                </ol>
            </section>
        @endif

        {{-- Taken, meldingen en tickets: één lijst met tabs. Elke regel is een link. --}}
        <section class="feed dash-section" aria-labelledby="feed-title"
                 x-data="{ tab: 'alles', counts: {{ Js::from(collect($feed['tabs'])->map(fn (array $tab): int => $tab[1])) }} }">
            <header class="feed-head">
                <div>
                    <h2 id="feed-title" class="dash-h2">Taken, meldingen en tickets</h2>
                    <p class="dash-sub">Wat je kunt doen en wat er gebeurd is, op één plek. Een taak verdwijnt zodra hij af is.</p>
                </div>
                <a class="btn btn-ghost" href="{{ route('notifications.index') }}" style="height:40px;font-size:13.5px">Alle meldingen</a>
            </header>

            {{-- Jouw AI-team: wie het is en wat hij de laatste 30 dagen al deed.
                 Klik een specialist om de lijst eronder op zijn taken te filteren. --}}
            <div class="feed-team" aria-label="Jouw AI-team">
            <h3 class="feed-team-title">Jouw AI-team</h3>
            <p class="dash-sub">Klik een specialist om alleen zijn taken te zien.</p>
            <div class="team-row">
                @foreach ($agents as $agent)
                    @php $did = collect($done)->firstWhere('agent', $agent->value); @endphp
                    {{-- Klein: foto en naam. Rol en wat hij deed staan in de tooltip. --}}
                    <button type="button" class="team-member"
                            title="{{ $agent->speciality() }}{{ $did ? ' · '.$did['text'] : '' }}"
                            :class="{ 'is-on': agent === '{{ $agent->value }}', 'is-dim': agent !== null && agent !== '{{ $agent->value }}' }"
                            :aria-pressed="agent === '{{ $agent->value }}'"
                            @click="agent = agent === '{{ $agent->value }}' ? null : '{{ $agent->value }}'">
                        <x-ui.agent-avatar :agent="$agent" class="team-photo" />
                        <b>{{ $agent->label() }}</b>
                    </button>
                @endforeach
            </div>
            </div>

            <nav class="feed-tabs" role="tablist" aria-label="Filter">
                @foreach ($feed['tabs'] as $key => [$label, $count])
                    <button type="button" role="tab" :aria-selected="tab === '{{ $key }}'" :class="tab === '{{ $key }}' && 'is-on'"
                            @click="tab = '{{ $key }}'">{{ $label }} <span class="tabular">{{ $count }}</span></button>
                @endforeach
            </nav>

            @if ($feed['items'] === [])
                <p class="feed-empty">Niets te doen en niets gemist. Zodra een bod, betaling, document of melding je aandacht vraagt, staat het hier.</p>
            @else
                <ul class="feed-list">
                    @foreach ($feed['items'] as $item)
                        <li class="feed-row kind-{{ $item['kind'] }} tone-{{ $item['tone'] }}{{ $item['unread'] ? ' is-unread' : '' }}"
                            x-data="{ gone: false }"
                            x-show="! gone
                                && (tab === 'alles' || (tab === 'ongelezen' && {{ $item['unread'] ? 'true' : 'false' }}) || tab === '{{ $item['kind'] }}')
                                && (agent === null || agent === '{{ $item['agent'] ?? '' }}')">
                            <span class="feed-icon" aria-hidden="true"><x-v4.icon :name="$item['icon']" :size="18" stroke="2" /></span>
                            <a class="feed-body" href="{{ $item['url'] }}">
                                <b>{{ $item['title'] }}@if ($item['unread'])<i class="dot" role="img" aria-label="nieuw"></i>@endif</b>
                                @if ($item['body'])
                                    <span>{{ $item['body'] }}</span>
                                @endif
                            </a>
                            <span class="feed-meta">
                                <time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</time>
                                @if ($item['dismiss_id'] !== null)
                                    <button type="button" class="feed-dismiss" aria-label="Taak verbergen"
                                            @click="gone = true; counts.alles--; counts.ongelezen--; counts.task--; fetch('/api/insights/{{ $item['dismiss_id'] }}/dismiss', {
                                                method: 'POST',
                                                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''},
                                            })"><x-v4.icon name="x" :size="15" stroke="2.2" /></button>
                                @endif
                                <a class="feed-go" href="{{ $item['url'] }}" aria-hidden="true" tabindex="-1"><x-v4.icon name="arrow-right" :size="16" stroke="2.2" /></a>
                            </span>
                        </li>
                    @endforeach
                </ul>
                <p class="feed-empty" x-show="counts[tab] === 0 && agent === null" x-cloak>Niets in deze lijst.</p>
            @endif
        </section>

        @if ($isSeller && $intent !== null && $forecast !== null)
            <div class="dash-section grid gap-5 lg:grid-cols-2 lg:items-start">
                {{-- Koperintentie: aantallen, geen personen. --}}
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Koperintentie">
                    <h2 class="dash-h2">{{ __('dashboard.intent.title') }}</h2>
                    <p class="dash-sub">Wat kopers de laatste 30 dagen met je kavels deden.</p>
                    {{-- Vier stats met icoon in dezelfde kleurgroepen als de tegels bovenaan. --}}
                    <dl class="intent-grid">
                        @foreach ([
                            ['watchers', 'heart', 'blue'],
                            ['viewings', 'scan-search', 'purple'],
                            ['bidders', 'gavel', 'green'],
                            ['business', 'building', 'orange'],
                        ] as [$key, $icon, $tone])
                            <div class="intent-stat tone-{{ $tone }}">
                                <span class="stat-icon" aria-hidden="true"><x-v4.icon :name="$icon" :size="18" stroke="2" /></span>
                                <dd class="tabular">{{ $intent[$key] }}</dd>
                                <dt>{{ __('dashboard.intent.'.$key) }}</dt>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-4 text-xs text-aj-muted">{{ __('dashboard.intent.privacy') }}</p>
                </section>

                {{-- De vraagvoorspelling: bandbreedte met een zichtbare meetgrens. --}}
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Verwachting">
                    <h2 class="dash-h2">{{ __('dashboard.forecast.title') }}</h2>

                    @if (! $forecast['has_history'])
                        {{-- Nog geen historie: de grafiek als schets met de echte weeklabels,
                             zodat je ziet wat hier komt, en de stap die ervoor nodig is. --}}
                        <p class="dash-sub">{{ __('dashboard.forecast.empty') }}</p>
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
                                    <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen</a>
                                </div>
                            </div>
                        </div>
                    @else
                        @php $peak = max(1, max(array_column($forecast['weeks'], 'high_cents'))); @endphp
                        <div class="mt-4 flex h-32 items-end gap-1" role="img"
                             aria-label="Weekomzet, {{ __('dashboard.forecast.measured') }} en {{ __('dashboard.forecast.predicted') }}">
                            @foreach ($forecast['weeks'] as $week)
                                <div class="flex flex-1 flex-col items-center gap-1"
                                     @if (! $week['measured']) style="opacity:.55" @endif>
                                    <div class="w-full rounded-t-aj-sm {{ $week['measured'] ? 'bg-aj-blue' : 'border-2 border-dashed border-aj-blue bg-aj-blue/15' }}"
                                         style="height:{{ max(2, intdiv($week['high_cents'] * 112, $peak)) }}px"
                                         title="{{ $week['label'] }}: {{ $week['measured'] ? Money::format($week['high_cents']) : Money::format($week['low_cents']).' – '.Money::format($week['high_cents']) }}"></div>
                                    <span class="text-[10px] text-aj-muted">{{ $week['label'] }}</span>
                                </div>
                                @if ($week['measured'] && ! ($forecast['weeks'][$loop->index + 1]['measured'] ?? true))
                                    {{-- Hier stopt meten en begint schatten. --}}
                                    <div class="h-28 w-px shrink-0 bg-aj-border-strong" aria-hidden="true"></div>
                                @endif
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-aj-muted">
                            {{ count(array_filter($forecast['weeks'], fn ($week) => $week['measured'])) }} {{ __('dashboard.forecast.measured') }}
                            · {{ count(array_filter($forecast['weeks'], fn ($week) => ! $week['measured'])) }} {{ __('dashboard.forecast.predicted') }}
                            - {{ __('dashboard.forecast.disclaimer') }}
                        </p>
                    @endif
                </section>
            </div>
        @endif

        <div class="dash-section">
            {{-- Wat Ivy deze week zag: observaties, bewust zonder knoppen. --}}
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Wat Ivy deze week zag">
                <h2 class="dash-h2">Wat Ivy deze week zag</h2>
                <p class="dash-sub">Observaties uit je kavels en biedingen. Geen knoppen: dit is om te weten, niet om te doen.</p>
                @if ($weekly->isEmpty())
                    <p class="mt-4 rounded-aj-s bg-aj-bg p-4 text-sm text-aj-muted">Nog niets deze week. Zodra er iets opvalt in je kavels of biedingen, staat het hier.</p>
                @else
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($weekly as $insight)
                            <li class="rounded-aj-s border border-aj-border bg-aj-bg p-4"
                                x-show="agent === null || agent === '{{ $insight->agent->value }}'">
                                <span class="flex items-center gap-1.5 text-xs font-extrabold"
                                      style="color:var(--color-aj-{{ $insight->agent->value }})">
                                    <x-ui.agent-avatar :agent="$insight->agent" class="size-5" />
                                    {{ $insight->agent->label() }}
                                </span>
                                <p class="mt-1 text-sm font-bold">{{ $insight->title }}</p>
                                @if ($insight->body)
                                    <p class="mt-1 text-xs text-aj-muted">{{ $insight->body }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
</x-layouts.dashboard>
