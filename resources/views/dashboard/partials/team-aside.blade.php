@use('App\Support\Money\Money')

{{--
    De zijkolom: bij de koper eerst de zwarte betaalkaart, dan het team.
    Elk teamlid is één regel met zijn foto, zijn vak en hoeveel er vanuit
    dat vak nog op je wacht. Een klik opent het gesprek met dat teamlid
    rechtsonder; daar staan de taken zelf, elk met zijn knop. Zonder het
    paneel van Ivy brengt de regel je naar Meldingen.
--}}
@php
    $displayTimezone = config('aucvy.display_timezone');
    $tasks = collect($feed['items'])->where('kind', 'task');
    // Per teamlid: hoeveel er wacht en of er iets bij zit dat nu moet.
    $waiting = $tasks->countBy(fn (array $task): string => (string) ($task['agent'] ?? ''));
    $urgent = $tasks->where('tone', 'red')->pluck('agent')->unique()->flip();
    // Wat het teamlid al deed (verkoper), als er niets wacht.
    $handled = collect($done)->keyBy('agent');
    // Eén blauwe knop per scherm: staat de betaalkaart er, dan is dat de hoofdhandeling.
    $hasPayCard = ! $isSeller && $toPay !== null && $toPay['count'] > 0;
@endphp

<aside class="d-col-aside" aria-label="Je team">
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
        <div class="d-aside-head">
            <div class="d-titleline">
                <h2 class="d-h3">Je team</h2>
                <x-dashboard.help text="Klik op iemand om met die specialist te praten. In dat gesprek staat wat er vanuit dat vak nog op je wacht. Het zijn AI-assistenten, geen mensen." />
            </div>
            <span class="d-muted">{{ $tasks->isEmpty() ? 'niets open' : $tasks->count().' '.($tasks->count() === 1 ? 'punt' : 'punten').' open' }}</span>
        </div>

        <div class="d-team">
            @foreach ($agents as $agent)
                @php
                    $count = (int) $waiting->get($agent->value, 0);
                    $status = match (true) {
                        $count === 1 => 'Eén ding wacht op je',
                        $count > 1 => $count.' dingen wachten op je',
                        $handled->has($agent->value) => $handled->get($agent->value)['text'],
                        default => 'Niets open',
                    };
                @endphp
                <a class="d-member{{ $count > 0 ? ' has-open' : '' }}" href="{{ route('notifications.index') }}"
                   aria-label="Praat met {{ $agent->label() }}, {{ $agent->speciality() }}. {{ $status }}."
                   @click="if (document.querySelector('.ivy-root')) { $event.preventDefault(); window.dispatchEvent(new CustomEvent('ivy-open', { detail: { agent: '{{ $agent->value }}' } })) }">
                    <img src="{{ asset('images/agents/'.$agent->value.'.jpg') }}" alt="" width="44" height="44" loading="lazy">
                    <span class="d-member-text">
                        <strong>{{ $agent->label() }} <small>{{ $agent->speciality() }}</small></strong>
                        <span>{{ $status }}</span>
                    </span>
                    @if ($count > 0)
                        <b class="d-member-count{{ $urgent->has($agent->value) ? ' is-urgent' : '' }}">{{ $count }}</b>
                    @else
                        <span class="d-member-go" aria-hidden="true"><x-v4.icon name="arrow-right" :size="16" stroke="2.2" /></span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</aside>
