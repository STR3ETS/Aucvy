{{--
    Meldingen en tickets: wat er gebeurd is, in één lijst met drie tabs. Elke
    regel is een link. Taken staan hier niet nog een keer: die staan met hun
    knop onder "Wacht op jou". De eerste vijf regels staan open, de rest
    achter één knop, en de hele lijst staat op de pagina Meldingen.
--}}
@php
    $feedLimit = 5;
    // De plek van elke regel binnen zijn tab, zodat elke tab zijn eigen eerste vijf toont.
    $rank = ['alles' => 0, 'ongelezen' => 0, 'ticket' => 0];
    $feedRows = collect($feed['items'])->where('kind', '!=', 'task')->values()->map(function (array $item) use (&$rank): array {
        $item['rank'] = ['alles' => $rank['alles']++, 'ongelezen' => $item['unread'] ? $rank['ongelezen']++ : -1, 'ticket' => $item['kind'] === 'ticket' ? $rank['ticket']++ : -1];

        return $item;
    });
    $feedTabs = ['alles' => ['Alles', $rank['alles']], 'ongelezen' => ['Ongelezen', $rank['ongelezen']], 'ticket' => ['Tickets', $rank['ticket']]];
@endphp

<section class="feed" aria-labelledby="feed-title"
         x-data="{ tab: 'alles', all: false, limit: {{ $feedLimit }}, counts: {{ Js::from(collect($feedTabs)->map(fn (array $tab): int => $tab[1])) }} }">
    <header class="feed-head">
        <div class="d-titleline">
            <h2 id="feed-title" class="d-h2">Meldingen en tickets</h2>
            <x-dashboard.help text="Wat er gebeurd is, met wat je nog niet gelezen hebt bovenaan. Wat je zelf moet doen staat onder Wacht op jou." />
        </div>
        <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('notifications.index') }}">Alle meldingen</a>
    </header>

    <nav class="feed-tabs" role="tablist" aria-label="Filter">
        @foreach ($feedTabs as $key => [$label, $count])
            <button type="button" role="tab" :aria-selected="tab === '{{ $key }}'" :class="tab === '{{ $key }}' && 'is-on'"
                    @click="tab = '{{ $key }}'; all = false">{{ $label }} <span class="tabular">{{ $count }}</span></button>
        @endforeach
    </nav>

    @if ($feedRows->isEmpty())
        <p class="feed-empty">Niets gemist. Zodra een bod, betaling, document of ticket je aandacht vraagt, staat het hier.</p>
    @else
        <ul class="feed-list">
            @foreach ($feedRows as $item)
                <li class="feed-row kind-{{ $item['kind'] }} tone-{{ $item['tone'] }}{{ $item['unread'] ? ' is-unread' : '' }}"
                    x-data="{ rank: {{ Js::from($item['rank']) }} }"
                    x-show="rank[tab] >= 0 && (all || rank[tab] < limit)"
                    @if ($item['rank']['alles'] >= $feedLimit) x-cloak @endif>
                    <span class="feed-icon" aria-hidden="true"><x-v4.icon :name="$item['icon']" :size="18" stroke="2" /></span>
                    <a class="feed-body" href="{{ $item['url'] }}">
                        <b>{{ $item['title'] }}@if ($item['unread'])<i class="dot" role="img" aria-label="nieuw"></i>@endif</b>
                        @if ($item['body'])
                            <span>{{ $item['body'] }}</span>
                        @endif
                    </a>
                    <span class="feed-meta">
                        <time datetime="{{ $item['at']->toIso8601String() }}">{{ $item['at']->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</time>
                        <a class="feed-go" href="{{ $item['url'] }}" aria-hidden="true" tabindex="-1"><x-v4.icon name="arrow-right" :size="16" stroke="2.2" /></a>
                    </span>
                </li>
            @endforeach
        </ul>
        <p class="feed-empty" x-show="counts[tab] === 0" x-cloak>Niets in deze lijst.</p>
        <div class="d-more" x-show="! all && counts[tab] > limit" @if ($rank['alles'] <= $feedLimit) x-cloak @endif>
            <button type="button" class="d-btn d-btn-secondary d-btn-sm" @click="all = true">Meer tonen</button>
        </div>
    @endif
</section>
