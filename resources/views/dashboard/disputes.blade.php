@use('App\Enums\DisputeStatus')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $at = fn ($moment): ?string => $moment?->timezone($displayTimezone)->isoFormat('D MMM, HH:mm');
    $viewer = auth()->user();
@endphp

<x-layouts.dashboard title="Geschillen" eyebrow="Dashboard" intro="Gaat er iets mis, dan volg je hier de melding, de reactie en de uitkomst.">
    @if ($disputes->isEmpty())
        <div>
            <x-ui.empty-state
                title="Geen geschillen"
                body="Zo hoort het. Gaat er ooit iets mis, dan volg je hier de melding, de reactie en de uitkomst." />
        </div>
    @else
        <div class="d-stack" style="gap:28px">
            @foreach ($disputes as $dispute)
                @php
                    $order = $dispute->order;
                    $lot = $order->lot;
                    $isReporter = $dispute->reported_by === $viewer->id;
                    $isSeller = $order->seller_id === $viewer->id;
                    $open = $dispute->status->isOpen();

                    // De melding in gewone taal; zolang het loopt beloven we niets over de uitkomst.
                    [$tone, $lead, $detail] = match ($dispute->status) {
                        DisputeStatus::Reported => $isSeller
                            ? ['warn', 'Er is een melding over deze verkoop', 'Reageer vóór '.($at($dispute->sla_due_at) ?? 'de termijn').'; daarna beoordeelt een medewerker met wat er ligt.']
                            : ['info', 'Je melding is binnen', 'De verkoper kan reageren tot '.($at($dispute->sla_due_at) ?? 'de termijn').'. We doen geen toezegging over de uitkomst voordat de melding is onderzocht.'],
                        DisputeStatus::SellerResponding, DisputeStatus::UnderReview => ['info', $isReporter ? 'Je melding is in behandeling' : 'De melding is in behandeling', 'Een medewerker beoordeelt wat er ligt. We doen geen toezegging over de uitkomst voordat de melding is onderzocht.'],
                        DisputeStatus::Resolved => ['ok', 'Dit geschil is afgehandeld', $dispute->correction_cents ? 'Uitkomst: een prijscorrectie van '.Money::format($dispute->correction_cents).'.' : 'De uitkomst staat bij de bestelling.'],
                        DisputeStatus::Rejected => ['info', 'De melding is afgewezen', 'De uitkomst en de toelichting staan bij de bestelling.'],
                    };

                    $steps = [
                        ['Melding gedaan', $at($dispute->created_at), true],
                        ['Reactie van de verkoper', $dispute->seller_responded_at ? 'Ontvangen op '.$at($dispute->seller_responded_at) : ($open && $dispute->sla_due_at ? 'Kan tot '.$at($dispute->sla_due_at) : 'Geen reactie ontvangen'), $dispute->seller_responded_at !== null || $dispute->status !== DisputeStatus::Reported],
                        ['Beoordeling door Aucvy', ! $open ? 'Afgerond' : ($dispute->status === DisputeStatus::Reported ? 'Start na de reactie of de termijn' : 'Loopt nu'), ! $open],
                        ['Uitkomst', ! $open ? ($at($dispute->resolved_at) ?? 'Bekend') : 'Volgt na onderzoek', ! $open],
                    ];
                    $nowIndex = collect($steps)->search(fn (array $step): bool => ! $step[2]);
                @endphp

                <section class="d-cols" aria-label="Geschil over {{ $lot->title }}">
                    <div class="d-col-main">
                        <div class="{{ $tone === 'ok' ? 'd-callout-ok' : ($tone === 'warn' ? 'd-callout-warn' : 'd-callout-info') }}" style="align-items:center">
                            <span class="mark" aria-hidden="true" style="width:40px;height:40px">@if ($tone === 'ok')<x-v4.icon name="check" :size="20" stroke="3" />@elseif ($tone === 'warn')!@else i @endif</span>
                            <div class="text" style="flex:1 1 240px;min-width:0">
                                <strong style="margin:0">{{ $lead }}</strong>
                                <span>{{ $detail }}</span>
                            </div>
                            <span class="d-muted tabular" style="flex:0 0 auto">Zaak #G-{{ $dispute->created_at->format('Y') }}-{{ str_pad((string) $dispute->id, 4, '0', STR_PAD_LEFT) }}</span>
                        </div>

                        <h2 class="d-h3" style="margin-top:6px">Waar gaat het over</h2>
                        <article class="d-listrow">
                            @if ($lot->media->isNotEmpty())
                                <img class="d-listrow-thumb" src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                            @else
                                <span class="d-listrow-thumb" aria-hidden="true"></span>
                            @endif
                            <div class="d-listrow-body">
                                <strong>{{ $lot->title }}</strong>
                                <span class="d-listrow-meta">Kavel #{{ $lot->public_id }} · bestelling {{ $order->order_number }}</span>
                                <div class="d-listrow-tags">
                                    <span class="d-tag tone-warn">{{ __('orders.dispute_reasons.'.$dispute->reason) }}</span>
                                    <span class="d-tag {{ $open ? 'tone-atlas' : 'tone-ok' }}">{{ $dispute->status->label() }}</span>
                                </div>
                            </div>
                            <div class="d-listrow-right">
                                <b>{{ Money::format($order->hammer_cents) }}</b>
                                <span>{{ $order->status->label() }}</span>
                            </div>
                        </article>

                        @if ($dispute->description || $dispute->seller_response)
                            <h2 class="d-h3" style="margin-top:6px">Gesprek</h2>
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
                        @endif

                        <div class="d-chips">
                            @if ($isSeller && $dispute->status === DisputeStatus::Reported)
                                <a class="d-btn d-btn-sq" href="{{ route('orders.show', $order) }}">Reageer op de melding</a>
                            @else
                                <a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('orders.show', $order) }}">Naar de bestelling</a>
                            @endif
                        </div>
                    </div>

                    <aside class="d-col-aside" style="border:0;background:none;display:flex;flex-direction:column;gap:14px">
                        <section class="d-card" aria-label="Status van de melding">
                            <h3 class="d-h3">Status van de melding</h3>
                            <ol class="d-steps">
                                @foreach ($steps as $index => [$label, $when, $done])
                                    <li class="{{ $done ? 'is-done' : ($index === $nowIndex ? 'is-now' : '') }}">
                                        <span class="dot" aria-hidden="true">@if ($done)<x-v4.icon name="check" :size="12" stroke="3" />@endif</span>
                                        <div><strong>{{ $label }}</strong><span>{{ $when }}</span></div>
                                    </li>
                                @endforeach
                            </ol>
                        </section>

                        @if ($loop->first)
                            <div class="d-callout-info">
                                <span class="mark" aria-hidden="true">i</span>
                                <div>
                                    <strong>Wat betekent dit?</strong>
                                    Vanaf de melding wordt de uitbetaling aan de verkoper aangehouden, tot het geschil is afgehandeld. Een medewerker beoordeelt, geen algoritme. <a class="d-link" href="{{ route('assurance') }}">Zo werkt de geschilroute</a>.
                                </div>
                            </div>
                        @endif
                    </aside>
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
