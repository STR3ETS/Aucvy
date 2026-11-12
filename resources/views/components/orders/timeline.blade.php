@props([
    'entries',
    // Zonder eigen kaart en kop: voor in een tab op de bestelpagina van het dashboard.
    'bare' => false,
])

{{--
    De tijdlijn van een bestelling: wat er gebeurd is, op volgorde. De
    volgende stap staat in het statusblok, niet hier.
--}}
<section @class(['d-card' => ! $bare]) aria-labelledby="tijdlijn">
    <h2 id="tijdlijn" @class(['text-sm font-extrabold text-aj-soft' => ! $bare, 'sr-only' => $bare])>Tijdlijn</h2>
    <ol @class(['order-timeline', 'mt-4' => ! $bare])>
        @foreach ($entries as $entry)
            <li class="order-event kind-{{ $entry['kind'] }}">
                <span class="dot" aria-hidden="true"></span>
                <div class="body">
                    <b>{{ $entry['title'] }}</b>
                    @if ($entry['body'])
                        <span>{{ $entry['body'] }}</span>
                    @endif
                    <time datetime="{{ $entry['at']->toIso8601String() }}">
                        {{ $entry['at']->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY, HH:mm') }}
                        @if ($entry['actor']) · {{ $entry['actor'] }} @endif
                    </time>
                </div>
            </li>
        @endforeach
    </ol>
</section>
