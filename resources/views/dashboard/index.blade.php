@use('App\Support\Money\Money')

<x-layouts.public title="Dashboard">
    {{--
        Geen cijferoverzicht maar een werkplan (hfst. 13.5). De volgorde van
        de blokken ís het ontwerp: gedaan → vraagt aandacht → cijfers → wat
        Ivy zag. Draai die volgorde niet om.
    --}}
    {{-- Volle .wrap-breedte, gelijk aan de header - geen extra keurslijf. --}}
    <div x-data="{ agent: null }">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-3xl font-extrabold tracking-[-0.03em]">Dashboard</h1>

            {{-- Eén dashboard, een toggle. De blokken verschillen, het raamwerk niet. --}}
            <nav class="flex gap-2" aria-label="Rol">
                <a href="{{ route('dashboard', ['rol' => 'verkoper']) }}"
                   class="btn {{ $isSeller ? 'btn-primary' : 'btn-ghost' }}"
                   style="height:40px;font-size:14px" @if ($isSeller) aria-current="page" @endif>Verkopen</a>
                <a href="{{ route('dashboard', ['rol' => 'koper']) }}"
                   class="btn {{ $isSeller ? 'btn-ghost' : 'btn-primary' }}"
                   style="height:40px;font-size:14px" @unless ($isSeller) aria-current="page" @endunless>Kopen</a>
            </nav>
        </div>

        <x-dashboard.nav />

        @if ($isNew)
            {{-- Nieuwe gebruikers zien startstappen in plaats van lege blokken. --}}
            <section class="mt-6 rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Aan de slag">
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

        {{-- Jouw AI-team: klik een specialist om alleen zijn punten te zien. --}}
        <section class="mt-6" aria-label="Jouw AI-team">
            <h2 class="text-sm font-extrabold text-aj-soft">Jouw AI-team</h2>
            <div class="chip-row mt-2 -mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                @foreach ($agents as $agent)
                    <button type="button"
                            class="btn btn-ghost"
                            style="height:44px;font-size:13px;flex:none"
                            :class="agent === '{{ $agent->value }}' && 'btn-primary'"
                            @click="agent = agent === '{{ $agent->value }}' ? null : '{{ $agent->value }}'">
                        <x-ui.agent-avatar :agent="$agent" class="size-6" />
                        {{ $agent->label() }} · {{ $agent->speciality() }}
                    </button>
                @endforeach
            </div>
        </section>

        {{-- Dit heeft het team al gedaan - vóór de vragen, dat is het ontwerp. --}}
        @if ($done !== [])
            <section class="mt-6" aria-label="Al gedaan">
                <h2 class="text-lg font-extrabold">✔ Dit heeft het team al gedaan</h2>
                <p class="text-sm text-aj-muted">Zonder dat je iets hoefde te doen - geteld over de laatste 30 dagen.</p>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($done as $activity)
                        <li class="flex items-center gap-2 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm"
                            x-show="agent === null || agent === '{{ $activity['agent'] }}'">
                            <x-ui.agent-avatar :agent="$activity['agent']" class="size-6 shrink-0" />
                            <span>
                                <span class="font-extrabold" style="color:var(--color-aj-{{ $activity['agent'] }})">
                                    {{ ucfirst($activity['agent']) }}</span>
                                - {{ $activity['text'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Vraagt vandaag je aandacht: geen kaart zonder knop. --}}
        <section class="mt-6" aria-label="Vraagt je aandacht">
            <h2 class="text-lg font-extrabold">
                Vraagt vandaag je aandacht
                <span class="text-aj-muted">- {{ $attention->count() }} {{ $attention->count() === 1 ? 'punt' : 'punten' }}</span>
            </h2>

            @if ($attention->isEmpty())
                <p class="mt-2 text-sm text-aj-muted">Niets - alles wat lopen moet, loopt.</p>
            @else
                <ul class="mt-3 space-y-2">
                    @foreach ($attention as $insight)
                        <li x-show="(agent === null || agent === '{{ $insight->agent->value }}') && ! gone"
                            x-data="{ gone: false }"
                            class="flex flex-wrap items-center gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                            <span class="flex shrink-0 items-center gap-1.5">
                                <x-ui.agent-avatar :agent="$insight->agent" class="size-8" />
                                <span class="text-xs font-extrabold" style="color:var(--color-aj-{{ $insight->agent->value }})">
                                    {{ $insight->agent->label() }}
                                </span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold">{{ $insight->title }}</span>
                                @if ($insight->body)
                                    <span class="block text-xs text-aj-muted">{{ $insight->body }}</span>
                                @endif
                            </span>
                            <a class="btn btn-ghost" style="height:40px;font-size:13px"
                               href="{{ route($insight->action_route, $insight->action_params ?? []) }}">
                                {{ $insight->action_label }}
                            </a>
                            <button type="button" class="text-aj-muted" aria-label="Signaal verbergen"
                                    @click="gone = true; fetch('/api/insights/{{ $insight->id }}/dismiss', {
                                        method: 'POST',
                                        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''},
                                    })">×</button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($isSeller && $intent !== null && $forecast !== null)
            <div class="mt-6 grid gap-5 lg:grid-cols-2 lg:items-start">
                {{-- Koperintentie: aantallen, geen personen. --}}
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Koperintentie">
                    <h2 class="text-lg font-extrabold">{{ __('dashboard.intent.title') }}</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        @foreach (['watchers', 'viewings', 'bidders', 'business'] as $key)
                            <div class="flex items-center gap-3">
                                <dd class="tabular w-10 text-right text-lg font-extrabold">{{ $intent[$key] }}</dd>
                                <dt class="flex-1 text-aj-muted">{{ __('dashboard.intent.'.$key) }}</dt>
                                <div class="h-2 rounded-aj-sm bg-aj-blue/70"
                                     style="width:{{ min(120, $intent[$key] * 12) }}px" aria-hidden="true"></div>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-3 text-xs text-aj-muted">{{ __('dashboard.intent.privacy') }}</p>
                </section>

                {{-- De vraagvoorspelling: bandbreedte met een zichtbare meetgrens. --}}
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Verwachting">
                    <h2 class="text-lg font-extrabold">{{ __('dashboard.forecast.title') }}</h2>

                    @if (! $forecast['has_history'])
                        <p class="mt-2 text-sm text-aj-muted">{{ __('dashboard.forecast.empty') }}</p>
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

        {{-- Wat Ivy deze week zag: observaties, bewust zonder knoppen. --}}
        @if ($weekly->isNotEmpty())
            <section class="mt-6" aria-label="Wat Ivy deze week zag">
                <h2 class="text-lg font-extrabold">Wat Ivy deze week zag</h2>
                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($weekly as $insight)
                        <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4"
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
            </section>
        @endif

        {{-- Snelkoppelingen --}}
        <nav class="mt-8 flex flex-wrap gap-2 border-t border-aj-border pt-5" aria-label="Snelkoppelingen">
            <a class="btn btn-ghost" href="{{ route('orders.index') }}">Bestellingen</a>
            <a class="btn btn-ghost" href="{{ route('sell') }}">Kavel plaatsen</a>
            <a class="btn btn-ghost" href="{{ route('lots.index') }}">Veilingen</a>
        </nav>
    </div>
</x-layouts.public>
