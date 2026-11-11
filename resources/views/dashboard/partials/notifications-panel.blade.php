{{--
    De inhoud van het meldingenpaneel dat rechts uitschuift: de laatste
    meldingen en tickets, elk een link. Het paneel zelf (kop, sluiten, de
    knop naar alle meldingen) staat in components/dashboard/notifications-drawer.
--}}
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

@if ($items === [])
    <p class="d-drawer-empty">Nog geen meldingen. Zodra je overboden wordt, een alert raak is of een bestelling verandert, staat het hier.</p>
@else
    <ul class="d-drawer-list" data-unread="{{ $unread }}">
        @foreach ($items as $item)
            <li class="d-notif tone-{{ $item['tone'] }}{{ $item['unread'] ? ' is-unread' : '' }}">
                <span class="d-notif-icon" aria-hidden="true"><x-v4.icon :name="$item['icon']" :size="18" stroke="2" /></span>
                <a class="d-notif-body" href="{{ $item['url'] }}">
                    <strong>{{ $item['title'] }}</strong>
                    @if ($item['body'])
                        <span>{{ $item['body'] }}</span>
                    @endif
                    <time datetime="{{ $item['at']->toIso8601String() }}">{{ $when($item['at']) }}</time>
                </a>
            </li>
        @endforeach
    </ul>
@endif
