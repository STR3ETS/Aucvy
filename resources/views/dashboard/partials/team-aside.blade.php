@use('App\Support\Money\Money')

{{--
    De zijkolom uit het ontwerp van november: bij de koper eerst de zwarte
    betaalkaart, dan het team met hun foto (filter), "Wacht op jou" met de
    taken per specialist en daaronder wat het team al deed.
--}}
@php
    $tasks = collect($feed['items'])->where('kind', 'task')->values();
    $urgency = ['red' => ['Nu', 'tone-live'], 'orange' => ['Vandaag', 'tone-warn'], 'blue' => ['Deze week', 'tone-atlas'], 'green' => ['Klaar', 'tone-ok']];
    $displayTimezone = config('aucvy.display_timezone');
    // Het aantal taken per specialist: de teller bij "Wacht op jou" volgt het filter.
    $taskCounts = $tasks->countBy(fn (array $task): string => (string) ($task['agent'] ?? ''))->all();
@endphp

<aside class="d-col-aside" aria-label="Team">
    @if (! $isSeller && $toPay !== null && $toPay['count'] > 0)
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

    <div class="d-aside-block" x-data="{ counts: {{ Js::from($taskCounts) }}, total: {{ $tasks->count() }}, get shown() { return agent === null ? this.total : (this.counts[agent] ?? 0); } }">
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
                    <article class="d-task" x-show="agent === null || agent === '{{ $task['agent'] ?? '' }}'">
                        <div class="d-task-tags">
                            @if ($task['agent'])
                                <span class="d-tag tone-{{ $task['agent'] }}">{{ ucfirst($task['agent']) }}</span>
                            @endif
                            <span class="d-tag {{ $whenTone }}">{{ $when }}</span>
                        </div>
                        <p>{{ $task['title'] }}</p>
                        <a class="d-btn d-btn-sm" href="{{ $task['url'] }}">{{ $task['action'] ?? 'Bekijken' }} <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
                    </article>
                @endforeach
            </div>
            <p class="d-sub" style="margin-top:8px" x-show="shown === 0" x-cloak>Bij deze specialist wacht nu niets op je.</p>
        @endif
    </div>

    <div class="d-aside-block">
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
                <div class="d-done" x-show="agent === null || agent === '{{ $insight->agent->value }}'">
                    <span class="d-agent d-agent-sm has-photo" aria-hidden="true"><img src="{{ asset('images/agents/'.$insight->agent->value.'.jpg') }}" alt="" width="28" height="28" loading="lazy"></span>
                    <span class="d-done-text">
                        <strong>{{ $insight->title }}</strong>
                        <span>{{ $insight->agent->label() }}{{ $insight->body ? ' · '.$insight->body : '' }}</span>
                    </span>
                </div>
            @empty
                <p class="d-sub" style="margin-top:8px">Nog niets deze week. Zodra er iets opvalt in je biedingen of je volglijst, staat het hier.</p>
            @endforelse
        @endif
    </div>
</aside>
