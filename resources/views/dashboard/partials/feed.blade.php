{{--
    Taken, meldingen en tickets: één lijst met tabs. Elke regel is een link.
    Het team erboven filtert de lijst op specialist; de chips in de
    zijkolom doen hetzelfde (dezelfde Alpine-variabele agent).
--}}
<section class="feed" aria-labelledby="feed-title"
         x-data="{ tab: 'alles', counts: {{ Js::from(collect($feed['tabs'])->map(fn (array $tab): int => $tab[1])) }} }">
    <header class="feed-head">
        <div>
            <h2 id="feed-title" class="d-h2">Taken, meldingen en tickets</h2>
            <p class="d-sub">Wat je kunt doen en wat er gebeurd is, op één plek. Een taak verdwijnt zodra hij af is.</p>
        </div>
        <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('notifications.index') }}">Alle meldingen</a>
    </header>

    <div class="feed-team" aria-label="Jouw AI-team">
        <h3 class="feed-team-title">Jouw AI-team</h3>
        <p class="d-sub">Klik een specialist om alleen zijn taken te zien.</p>
        <div class="team-row">
            @foreach ($agents as $agent)
                @php $did = collect($done)->firstWhere('agent', $agent->value); @endphp
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
