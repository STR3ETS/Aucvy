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
    // De profielkaart: initialen als er geen beeldmerk is, en een link naar het publieke profiel als dat bestaat.
    $meInitials = collect(explode(' ', trim((string) $user->name)))->filter()->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('');
    $hasProfile = $user->hasSellerProfile();
@endphp

<aside class="d-col-aside is-stack" aria-label="Jij en je team">
    {{-- Wie je bent: naam, rol en bedrijf, of je geverifieerd bent en de weg naar je instellingen. --}}
    <section class="d-me" aria-label="Je profiel">
        <div class="d-me-top">
            @if ($user->company?->logo_path)
                <img class="d-me-avatar" src="{{ $user->company->logo_path }}" alt="">
            @else
                <span class="d-me-avatar" aria-hidden="true">{{ $meInitials !== '' ? $meInitials : 'A' }}</span>
            @endif
            <div class="d-me-text">
                <strong>{{ $user->name }}</strong>
                <span>{{ $user->company !== null ? $user->companyRole()->label().' bij '.$user->company->name : 'Particulier account' }}</span>
            </div>
        </div>
        <div class="d-me-tags">
            @if ($user->kyc_status->allowsBidding())
                <span class="d-tag tone-ok"><x-v4.icon name="badge-check" :size="13" stroke="2.2" /> Geverifieerd</span>
            @else
                <a class="d-tag tone-warn" href="{{ route('verification.overview') }}">Nog niet geverifieerd</a>
            @endif
            <span class="d-tag tone-grey">{{ $user->company !== null ? 'Zakelijk' : 'Particulier' }}</span>
            <span class="d-tag tone-grey">Lid sinds {{ str_replace('.', '', $user->created_at->timezone($displayTimezone)->isoFormat('MMM YYYY')) }}</span>
        </div>
        <div class="d-me-actions">
            <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('account.settings') }}">Instellingen</a>
            @if ($hasProfile)
                <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('sellers.show', $user) }}">Mijn profiel</a>
            @endif
        </div>
    </section>

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

    <div class="d-aside-block d-side-team">
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
