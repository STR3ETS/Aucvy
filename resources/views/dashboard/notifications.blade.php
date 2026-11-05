@php
    $displayTimezone = config('aucvy.display_timezone');

    // "Zojuist", "Vandaag, 10:24", "Gisteren, 16:05", daarna de datum.
    $when = function (\Carbon\CarbonInterface $at) use ($displayTimezone): string {
        $local = $at->copy()->timezone($displayTimezone);
        $now = now()->timezone($displayTimezone);

        return match (true) {
            $at->diffInSeconds(now(), true) < 120 => 'Zojuist',
            $local->isSameDay($now) => 'Vandaag, '.$local->format('H:i'),
            $local->isSameDay($now->copy()->subDay()) => 'Gisteren, '.$local->format('H:i'),
            $local->isSameYear($now) => rtrim($local->isoFormat('D MMM'), '.'),
            default => rtrim($local->isoFormat('D MMM YYYY'), '.'),
        };
    };
@endphp

<x-layouts.dashboard title="Meldingen" eyebrow="Dashboard" intro="Wat je kunt doen en wat er gebeurd is, op één plek. Een taak verdwijnt zodra hij af is.">
    <x-slot:actions>
        @if ($unread > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-white">Alles gelezen</button>
            </form>
        @endif
        <a class="btn btn-outline-white" href="{{ route('account.settings.section', 'meldingen') }}">Voorkeuren</a>
    </x-slot:actions>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif

    @if ($feed['items'] === [])
        <x-ui.empty-state title="Nog geen meldingen"
            body="Zodra je overboden wordt, een alert raak is of een bestelling verandert, staat het hier.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div x-data="{ tab: 'alles', counts: {{ Js::from(collect($feed['tabs'])->map(fn (array $tab): int => $tab[1])) }} }">
            <div class="d-chips" role="tablist" aria-label="Filter" style="margin-bottom:14px">
                @foreach ($feed['tabs'] as $key => [$label, $count])
                    <button type="button" role="tab" class="d-chip" :class="tab === '{{ $key }}' && 'is-on'" :aria-selected="tab === '{{ $key }}'"
                            @click="tab = '{{ $key }}'">{{ $label }} <span class="tabular" x-text="counts['{{ $key }}']">{{ $count }}</span></button>
                @endforeach
            </div>

            <ul class="d-notifs">
                @foreach ($feed['items'] as $item)
                    <li class="d-notif tone-{{ $item['tone'] }}{{ $item['unread'] ? ' is-unread' : '' }}"
                        x-data="{ gone: false }"
                        x-show="! gone && (tab === 'alles' || (tab === 'ongelezen' && {{ $item['unread'] ? 'true' : 'false' }}) || tab === '{{ $item['kind'] }}')">
                        <span class="d-notif-icon" aria-hidden="true"><x-v4.icon :name="$item['icon']" :size="18" stroke="2" /></span>
                        <a class="d-notif-body" href="{{ $item['url'] }}">
                            <strong>{{ $item['title'] }}</strong>
                            @if ($item['body'])
                                <span>{{ $item['body'] }}</span>
                            @endif
                        </a>
                        <time class="d-notif-time" datetime="{{ $item['at']->toIso8601String() }}">{{ $when($item['at']) }}</time>
                        @if ($item['dismiss_id'] !== null)
                            <button type="button" class="d-notif-dismiss" aria-label="Taak verbergen"
                                    @click="gone = true; counts.alles--; counts.ongelezen--; counts.task--; fetch('/api/insights/{{ $item['dismiss_id'] }}/dismiss', {
                                        method: 'POST',
                                        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? ''},
                                    })"><x-v4.icon name="x" :size="15" stroke="2.2" /></button>
                        @endif
                    </li>
                @endforeach
                <li class="d-notif-empty" x-show="counts[tab] === 0" x-cloak>Niets in deze lijst.</li>
            </ul>
        </div>
    @endif
</x-layouts.dashboard>
