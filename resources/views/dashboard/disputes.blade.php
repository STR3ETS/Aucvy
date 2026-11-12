@use('App\Enums\DisputeStatus')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $at = fn ($moment): ?string => $moment === null ? null : rtrim($moment->timezone($displayTimezone)->isoFormat('D MMM'), '.').', '.$moment->timezone($displayTimezone)->format('H:i');
    $day = fn ($moment): string => rtrim($moment->timezone($displayTimezone)->isoFormat('D MMM YYYY'), '.');
    $viewer = auth()->user();

    // Wat nog loopt eerst, daaronder wat een uitkomst heeft.
    $tables = collect([
        'lopend' => ['Lopend', 'Vanaf de melding wordt de uitbetaling aan de verkoper aangehouden, tot het geschil is afgehandeld. Een medewerker beoordeelt, geen algoritme.', $disputes->filter(fn ($dispute): bool => $dispute->status->isOpen())->values()],
        'afgehandeld' => ['Afgehandeld', 'Geschillen met een uitkomst. De toelichting staat bij de bestelling.', $disputes->reject(fn ($dispute): bool => $dispute->status->isOpen())->values()],
    ])->filter(fn (array $table): bool => $table[2]->isNotEmpty());
    $listLimit = 10;
@endphp

{{--
    Geschillen, net als Mijn kavels: wat loopt en wat is afgehandeld elk in
    een eigen tabel, de koppen één keer en de acties als pictogram. De stand
    is een ring die meeloopt met de vier stappen. Het verhaal erachter (de
    toelichting, de stappen en wat er gezegd is) klapt uit onder de regel;
    bij een lopend geschil staat het al open.
--}}
<x-layouts.dashboard title="Geschillen" eyebrow="Dashboard" intro="Gaat er iets mis, dan volg je hier de melding, de reactie en de uitkomst.">
    <x-slot:actions>
        <a class="btn btn-outline-white" href="{{ route('assurance') }}">Zo werkt de geschilroute</a>
    </x-slot:actions>

    @if ($disputes->isEmpty())
        <div>
            <x-ui.empty-state
                title="Geen geschillen"
                body="Zo hoort het. Gaat er ooit iets mis, dan volg je hier de melding, de reactie en de uitkomst." />
        </div>
    @else
        <div class="d-sections d-stack">
            @foreach ($tables as $key => [$title, $help, $rows])
                <section class="d-card" aria-labelledby="geschillen-{{ $key }}" x-data="{ all: false }">
                    <div class="d-titleline" style="margin-bottom:12px">
                        <h2 id="geschillen-{{ $key }}" class="d-h2">{{ $title }}</h2>
                        <span class="d-count tabular">{{ $rows->count() }}</span>
                        <x-dashboard.help :text="$help" />
                    </div>

                    <div class="d-lottable" style="--cols:minmax(0,1fr) 130px 320px 86px">
                        <div class="d-lottable-head" aria-hidden="true">
                            <span>Kavel</span>
                            <span class="num">Bedrag</span>
                            <span>Stand</span>
                            <span></span>
                        </div>

                        @foreach ($rows as $dispute)
                            @php
                                $order = $dispute->order;
                                $lot = $order->lot;
                                $isReporter = $dispute->reported_by === $viewer->id;
                                $isSeller = $order->seller_id === $viewer->id;
                                $open = $dispute->status->isOpen();
                                $mustRespond = $isSeller && $dispute->status === DisputeStatus::Reported;
                                $case = 'Zaak #G-'.$dispute->created_at->format('Y').'-'.str_pad((string) $dispute->id, 4, '0', STR_PAD_LEFT);

                                // De melding in gewone taal; zolang het loopt beloven we niets over de uitkomst.
                                [$lead, $detail] = match ($dispute->status) {
                                    DisputeStatus::Reported => $isSeller
                                        ? ['Er is een melding over deze verkoop', 'Reageer vóór '.($at($dispute->sla_due_at) ?? 'de termijn').'; daarna beoordeelt een medewerker met wat er ligt.']
                                        : ['Je melding is binnen', 'De verkoper kan reageren tot '.($at($dispute->sla_due_at) ?? 'de termijn').'. We doen geen toezegging over de uitkomst voordat de melding is onderzocht.'],
                                    DisputeStatus::SellerResponding, DisputeStatus::UnderReview => [$isReporter ? 'Je melding is in behandeling' : 'De melding is in behandeling', 'Een medewerker beoordeelt wat er ligt. We doen geen toezegging over de uitkomst voordat de melding is onderzocht.'],
                                    DisputeStatus::Resolved => ['Dit geschil is afgehandeld', $dispute->correction_cents ? 'Uitkomst: een prijscorrectie van '.Money::format($dispute->correction_cents).'.' : 'De uitkomst staat bij de bestelling.'],
                                    DisputeStatus::Rejected => ['De melding is afgewezen', 'De uitkomst en de toelichting staan bij de bestelling.'],
                                };

                                $steps = [
                                    ['Melding gedaan', $at($dispute->created_at), true],
                                    ['Reactie van de verkoper', $dispute->seller_responded_at ? 'Ontvangen op '.$at($dispute->seller_responded_at) : ($open && $dispute->sla_due_at ? 'Kan tot '.$at($dispute->sla_due_at) : 'Geen reactie ontvangen'), $dispute->seller_responded_at !== null || $dispute->status !== DisputeStatus::Reported],
                                    ['Beoordeling door Aucvy', ! $open ? 'Afgerond' : ($dispute->status === DisputeStatus::Reported ? 'Start na de reactie of de termijn' : 'Loopt nu'), ! $open],
                                    ['Uitkomst', ! $open ? ($at($dispute->resolved_at) ?? 'Bekend') : 'Volgt na onderzoek', ! $open],
                                ];
                                $nowIndex = collect($steps)->search(fn (array $step): bool => ! $step[2]);
                                $doneSteps = collect($steps)->filter(fn (array $step): bool => $step[2])->count();

                                // De stand in één regel, met de ring die meeloopt met de stappen.
                                [$ringTone, $stand] = match (true) {
                                    $mustRespond => ['above', 'Reageer vóór '.($at($dispute->sla_due_at) ?? 'de termijn')],
                                    $dispute->status === DisputeStatus::Reported => ['within', 'De verkoper kan reageren tot '.($at($dispute->sla_due_at) ?? 'de termijn')],
                                    $open => ['within', 'Aucvy beoordeelt de melding'],
                                    $dispute->status === DisputeStatus::Rejected => ['none', 'Melding afgewezen'],
                                    $dispute->correction_cents > 0 => ['below', 'Afgehandeld, prijscorrectie '.Money::format($dispute->correction_cents)],
                                    default => ['below', 'Afgehandeld'.($dispute->resolved_at ? ' op '.$day($dispute->resolved_at) : '')],
                                };
                            @endphp
                            <article class="d-lotrow" x-data="{ open: {{ $open ? 'true' : 'false' }} }" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                <div class="d-lotrow-lot">
                                    <a class="thumb" href="{{ route('orders.show', $order) }}" tabindex="-1" aria-hidden="true">
                                        @if ($lot->media->isNotEmpty())
                                            <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                                        @else
                                            <x-v4.icon name="image" :size="20" stroke="1.6" />
                                        @endif
                                    </a>
                                    <div class="text">
                                        <strong><a href="{{ route('orders.show', $order) }}">{{ $lot->title }}</a></strong>
                                        <span class="d-lotrow-tags">
                                            <span class="d-tag tone-warn">{{ __('orders.dispute_reasons.'.$dispute->reason) }}</span>
                                            <span class="d-tag {{ $open ? 'tone-atlas' : 'tone-ok' }}">{{ $dispute->status->label() }}</span>
                                            <span class="d-bid-meta tabular">{{ $case }} · bestelling {{ $order->order_number }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="d-lotrow-facts">
                                    <span class="cell is-value"><span class="label">Bedrag</span><b class="tabular">{{ Money::format($order->hammer_cents) }}</b></span>
                                    <span class="cell d-bought-verdict is-{{ $ringTone }}">
                                        <span class="d-ring is-sm is-{{ $ringTone }}" role="img" aria-label="Stap {{ $doneSteps }} van 4">
                                            <svg viewBox="0 0 100 100" aria-hidden="true">
                                                <circle class="track" cx="50" cy="50" r="38"></circle>
                                                <circle class="value" cx="50" cy="50" r="38" pathLength="100" stroke-dasharray="{{ $doneSteps * 25 }} 100"></circle>
                                            </svg>
                                        </span>
                                        <span>{{ $stand }}</span>
                                    </span>
                                </div>

                                {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                                <div class="d-lotrow-actions">
                                    @if ($mustRespond)
                                        <a href="{{ route('orders.show', $order) }}" class="d-iconbtn is-dark" data-tip="Reageer op de melding" aria-label="Reageer op de melding over {{ $lot->title }}"><x-v4.icon name="message-circle" :size="17" stroke="2" /></a>
                                    @else
                                        <a href="{{ route('orders.show', $order) }}" class="d-iconbtn" data-tip="Naar de bestelling" aria-label="Naar de bestelling van {{ $lot->title }}"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                    @endif
                                    <button type="button" class="d-iconbtn d-fold" :class="open && 'is-open'" data-tip="Toelichting" aria-label="Toelichting bij het geschil over {{ $lot->title }}" :aria-expanded="open" aria-controls="geschil-{{ $dispute->id }}" @click="open = ! open"><x-v4.icon name="chevron-down" :size="17" stroke="2" /></button>
                                </div>

                                {{-- Het verhaal erachter: wat er nu speelt, de vier stappen en wat er gezegd is. --}}
                                <div id="geschil-{{ $dispute->id }}" class="d-disputedetail" x-show="open" @unless ($open) x-cloak @endunless>
                                    <p class="d-lead"><b>{{ $lead }}.</b> {{ $detail }}</p>

                                    <ol class="d-hsteps" aria-label="Status van de melding">
                                        @foreach ($steps as $index => [$label, $when, $done])
                                            <li class="{{ $done ? 'is-done' : ($index === $nowIndex ? 'is-now' : '') }}">
                                                <span class="dot" aria-hidden="true">@if ($done)<x-v4.icon name="check" :size="13" stroke="3" />@endif</span>
                                                <strong>{{ $label }}</strong>
                                                <span>{{ $when }}</span>
                                            </li>
                                        @endforeach
                                    </ol>

                                    @if ($dispute->description || $dispute->seller_response)
                                        <div class="d-disputetalk" aria-label="Wat er gezegd is">
                                            @if ($dispute->description)
                                                <div class="d-dispute-msg">
                                                    <div><strong>{{ $isReporter ? 'Jij' : 'Koper' }}</strong><span class="d-muted">{{ $at($dispute->created_at) }}</span></div>
                                                    <p>{{ $dispute->description }}</p>
                                                </div>
                                            @endif
                                            @if ($dispute->seller_response)
                                                <div class="d-dispute-msg">
                                                    <div><strong>{{ $isSeller ? 'Jij' : 'Verkoper' }}</strong><span class="d-muted">{{ $at($dispute->seller_responded_at) }}</span></div>
                                                    <p>{{ $dispute->seller_response }}</p>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <x-dashboard.more :total="$rows->count()" :limit="$listLimit" />
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
