@use('App\Support\Money\Money')

{{--
    De zijkolom uit het ontwerp van november: bij de koper eerst de zwarte
    betaalkaart, dan het team met hun foto (filter), "Wacht op jou" met de
    taken per specialist en daaronder wat het team al deed.
--}}
@php
    // Wat het eerst moet staat bovenaan: nu, dan vandaag, dan deze week.
    $urgencyOrder = ['red' => 0, 'orange' => 1, 'blue' => 2, 'green' => 3];
    $tasks = collect($feed['items'])->where('kind', 'task')->sortBy(fn (array $task): int => $urgencyOrder[$task['tone']] ?? 2)->values();
    $urgency = ['red' => ['Nu', 'tone-live'], 'orange' => ['Vandaag', 'tone-warn'], 'blue' => ['Deze week', 'tone-atlas'], 'green' => ['Klaar', 'tone-ok']];
    $displayTimezone = config('aucvy.display_timezone');
    // Het aantal taken per specialist: de teller bij "Wacht op jou" volgt het filter.
    $taskCounts = $tasks->countBy(fn (array $task): string => (string) ($task['agent'] ?? ''))->all();
    // Zoveel taken staan open; de rest zit achter "Nog n tonen".
    $taskLimit = 4;
    // Eén blauwe knop per scherm: staat de betaalkaart er, dan is dat de hoofdhandeling.
    $hasPayCard = ! $isSeller && $toPay !== null && $toPay['count'] > 0;
@endphp

<aside class="d-col-aside" aria-label="Team">
    @if ($hasPayCard)
        <div class="d-pay">
            <div class="d-pay-head">
                <span>Te betalen</span>
                @if ($toPay['deadline'])
                    <span>uiterlijk {{ $toPay['deadline']->timezone($displayTimezone)->isoFormat('D MMM, HH:mm') }}</span>
                @endif
            </div>
            <div class="d-pay-amount tabular">{{ Money::format($toPay['total_cents']) }}</div>
            <a class="d-btn d-btn-wide" href="{{ $toPay['count'] === 1 ? route('orders.show', $toPay['order']) : route('orders.index') }}">Nu betalen</a>
        </div>
    @endif

    <div class="d-aside-block">
        <span class="d-aside-label">Team</span>
        <div class="d-agent-row" role="group" aria-label="Filter op specialist">
            <button type="button" class="d-agent is-all" :class="agent === null && 'is-on'" :aria-pressed="agent === null" @click="agent = null" aria-label="Filter op Alles">Alles</button>
            @foreach ($agents as $agent)
                <button type="button" class="d-agent has-photo tone-{{ $agent->value }}" title="{{ $agent->label() }} · {{ $agent->speciality() }}"
                        :class="{ 'is-on': agent === '{{ $agent->value }}', 'is-dim': agent !== null && agent !== '{{ $agent->value }}' }" :aria-pressed="agent === '{{ $agent->value }}'"
                        @click="agent = agent === '{{ $agent->value }}' ? null : '{{ $agent->value }}'"
                        aria-label="Filter op {{ $agent->label() }}"><img src="{{ asset('images/agents/'.$agent->value.'.jpg') }}" alt="" width="44" height="44" loading="lazy"></button>
            @endforeach
        </div>
    </div>

    {{--
        Wacht op jou: de eerste vier staan open, de rest achter één knop. Alleen
        de bovenste taak heeft de blauwe knop, zodat duidelijk is wat eerst moet.
        Taken staan alleen hier, niet nog een keer in de lijst met meldingen.
    --}}
    <div class="d-aside-block" x-data="{ counts: {{ Js::from($taskCounts) }}, total: {{ $tasks->count() }}, all: false, get shown() { return agent === null ? this.total : (this.counts[agent] ?? 0); } }">
        <div class="d-aside-head">
            <h2 class="d-h3">Wacht op jou</h2>
            <span class="d-muted" x-text="shown + (shown === 1 ? ' punt' : ' punten')">{{ $tasks->count() }} {{ $tasks->count() === 1 ? 'punt' : 'punten' }}</span>
        </div>
        @if ($tasks->isEmpty())
            <p class="d-sub" style="margin-top:8px">Niets wacht op je. Zodra het team iets nodig heeft, staat het hier met een knop.</p>
        @else
            <div class="d-stack-tight" style="margin-top:10px">
                @foreach ($tasks as $task)
                    @php [$when, $whenTone] = $urgency[$task['tone']] ?? ['Deze week', 'tone-atlas']; @endphp
                    <article class="d-task" x-data="{ gone: false }"
                             x-show="! gone && (agent === null ? (all || {{ $loop->index }} < {{ $taskLimit }}) : agent === '{{ $task['agent'] ?? '' }}')"
                             @if ($loop->index >= $taskLimit) x-cloak @endif>
                        <div class="d-task-top">
                            <div class="d-task-tags">
                                @if ($task['agent'])
                                    <span class="d-tag tone-{{ $task['agent'] }}">{{ ucfirst($task['agent']) }}</span>
                                @endif
                                <span class="d-tag {{ $whenTone }}">{{ $when }}</span>
                            </div>
                            @if ($task['dismiss_id'] !== null)
                                <button type="button" class="d-task-x" aria-label="Taak verbergen" title="Verbergen"
                                        @click="gone = true; total--; counts['{{ $task['agent'] ?? '' }}'] = (counts['{{ $task['agent'] ?? '' }}'] ?? 1) - 1; fetch('/api/insights/{{ $task['dismiss_id'] }}/dismiss', {
                                            method: 'POST',
                                            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''},
                                        })"><x-v4.icon name="x" :size="15" stroke="2.2" /></button>
                            @endif
                        </div>
                        <p>{{ $task['title'] }}</p>
                        <a class="d-btn d-btn-sm{{ $loop->first && ! $hasPayCard ? '' : ' d-btn-secondary' }}" href="{{ $task['url'] }}">{{ $task['action'] ?? 'Bekijken' }} <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
                    </article>
                @endforeach
            </div>
            @if ($tasks->count() > $taskLimit)
                <div class="d-more" x-show="agent === null && ! all">
                    <button type="button" class="d-btn d-btn-secondary d-btn-sm d-btn-wide" @click="all = true">Nog {{ $tasks->count() - $taskLimit }} tonen</button>
                </div>
            @endif
            <p class="d-sub" style="margin-top:8px" x-show="shown === 0" x-cloak>Bij deze specialist wacht nu niets op je.</p>
        @endif
    </div>

    <div class="d-aside-block" x-data="{ more: false }">
        <div class="d-aside-head">
            <h2 class="d-h3">{{ $isSeller ? 'Al afgehandeld' : 'Ivy regelde deze week' }}</h2>
            <span class="d-muted">{{ $isSeller ? 'laatste 30 dagen' : 'observaties' }}</span>
        </div>
        @if ($isSeller)
            @forelse ($done as $entry)
                <div class="d-done" x-show="agent === null || agent === '{{ $entry['agent'] }}'">
                    <span class="d-agent d-agent-sm has-photo" aria-hidden="true"><img src="{{ asset('images/agents/'.$entry['agent'].'.jpg') }}" alt="" width="28" height="28" loading="lazy"></span>
                    <span class="d-done-text">
                        <strong>{{ $entry['text'] }}</strong>
                        <span>{{ ucfirst($entry['agent']) }}</span>
                    </span>
                </div>
            @empty
                <p class="d-sub" style="margin-top:8px">Nog niets afgehandeld. Zodra Ivy kavels herkent, teksten schrijft of documenten controleert, staat het hier.</p>
            @endforelse
        @else
            @forelse ($weekly as $insight)
                <div class="d-done" x-show="agent === null ? (more || {{ $loop->index }} < 3) : agent === '{{ $insight->agent->value }}'" @if ($loop->index >= 3) x-cloak @endif>
                    <span class="d-agent d-agent-sm has-photo" aria-hidden="true"><img src="{{ asset('images/agents/'.$insight->agent->value.'.jpg') }}" alt="" width="28" height="28" loading="lazy"></span>
                    <span class="d-done-text">
                        <strong>{{ $insight->title }}</strong>
                        <span>{{ $insight->agent->label() }}{{ $insight->body ? ' · '.$insight->body : '' }}</span>
                    </span>
                </div>
            @empty
                <p class="d-sub" style="margin-top:8px">Nog niets deze week. Zodra er iets opvalt in je biedingen of je volglijst, staat het hier.</p>
            @endforelse
            @if ($weekly->count() > 3)
                <div class="d-more" x-show="agent === null && ! more">
                    <button type="button" class="d-btn d-btn-secondary d-btn-sm d-btn-wide" @click="more = true">Nog {{ $weekly->count() - 3 }} tonen</button>
                </div>
            @endif
        @endif
    </div>
</aside>
