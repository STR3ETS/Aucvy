@use('App\Enums\PayoutStatus')
@use('App\Support\Money\Money')

@php
    $order = $payout->order;
    $lot = $order->lot;
    $displayTimezone = config('aucvy.display_timezone');
    $at = fn ($moment): ?string => $moment?->timezone($displayTimezone)->isoFormat('D MMM, HH:mm');

    // Kop en melding per status: nooit "verzonden" beloven als het bedrag nog wordt vastgehouden.
    [$title, $intro, $tone, $lead, $detail] = match ($payout->status) {
        PayoutStatus::Paid => ['Uitbetaling verzonden', 'De opbrengst van je verkochte kavel is overgemaakt naar je bankrekening.', 'ok', 'Uitbetaling is verzonden', 'Het bedrag staat meestal binnen 1 tot 2 werkdagen op je rekening.'],
        PayoutStatus::Released => ['Uitbetaling vrijgegeven', 'De houdtermijn is voorbij; het bedrag is vrijgegeven voor uitbetaling.', 'ok', 'Uitbetaling is vrijgegeven', 'Het bedrag is onderweg naar je rekening.'],
        PayoutStatus::Held => ['Uitbetaling in de houdtermijn', 'De koper kan nog een probleem melden; daarna volgt je uitbetaling.', 'info', 'In de houdtermijn', $payout->hold_until ? 'Vrijgave op '.$payout->hold_until->timezone($displayTimezone)->isoFormat('D MMMM, HH:mm').', als er geen melding komt.' : 'De vrijgave volgt na de houdtermijn.'],
        PayoutStatus::Failed => ['Uitbetaling niet gelukt', 'De overboeking is niet aangekomen. Hieronder staat waarom.', 'warn', 'De uitbetaling is niet gelukt', $payout->failure_reason ?: 'Controleer je bankrekening bij je bedrijfsgegevens of neem contact op.'],
        default => ['Uitbetaling ingepland', 'Na de levering start de houdtermijn; daarna volgt je uitbetaling.', 'info', 'Uitbetaling staat ingepland', 'De houdtermijn start zodra de levering is bevestigd.'],
    };
    if ($payout->blocked_reason) {
        [$tone, $lead, $detail] = ['warn', 'Deze uitbetaling wordt aangehouden', $payout->blocked_reason];
    }

    $rank = match ($payout->status) {
        // Het aantal stappen dat af is; tijdens de houdtermijn is alleen de verkoop afgerond.
        PayoutStatus::Scheduled, PayoutStatus::Held => 1,
        PayoutStatus::Released => 3,
        PayoutStatus::Paid => 4,
        default => 1,
    };
    $steps = [
        ['Verkoop afgerond', $at($order->paid_at ?? $order->created_at)],
        ['Houdtermijn voorbij', $rank > 2 ? $at($payout->released_at ?? $payout->hold_until) : ($payout->hold_until ? 'Op '.$at($payout->hold_until) : 'Na de levering')],
        ['Uitbetaling vrijgegeven', $at($payout->released_at) ?? 'Na de houdtermijn'],
        ['Bijgeschreven op je rekening', $at($payout->paid_at) ?? 'Meestal binnen 1 tot 2 werkdagen na vrijgave'],
    ];
    $feePercent = $order->hammer_cents > 0 ? rtrim(rtrim(number_format($payout->fee_cents / $order->hammer_cents * 100, 1, ',', '.'), '0'), ',') : null;
@endphp

<x-layouts.dashboard :title="$title" eyebrow="Uitbetalingen" :intro="$intro">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.payouts') }}">Uitbetalingen</a> <span aria-hidden="true">/</span> <span>{{ $order->order_number }}</span>
    </x-slot:crumbs>

    <div class="d-cols">
        <div class="d-col-main" style="display:flex;flex-direction:column;gap:14px">
            <div class="{{ $tone === 'ok' ? 'd-callout-ok' : ($tone === 'warn' ? 'd-callout-warn' : 'd-callout-info') }}">
                <span class="mark" aria-hidden="true">@if ($tone === 'ok')<x-v4.icon name="check" :size="20" stroke="3" />@elseif ($tone === 'warn')!@else i @endif</span>
                <div class="text" style="flex:1 1 240px;min-width:0">
                    <strong>{{ $lead }}</strong>
                    <span>{{ $detail }}</span>
                </div>
                @if ($payout->paid_at ?? $payout->released_at)
                    <span class="d-muted" style="flex:0 0 auto">{{ $at($payout->paid_at ?? $payout->released_at) }}</span>
                @endif
            </div>

            <section class="d-card d-payout-amount" aria-label="Bedrag">
                <span class="icon" aria-hidden="true"><x-v4.icon name="banknote" :size="22" stroke="2" /></span>
                <div>
                    <span class="d-muted">{{ in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Released], true) ? 'Uitbetaald bedrag' : 'Uit te betalen bedrag' }} (netto)</span>
                    <div class="d-big" style="font-size:30px">{{ Money::format($payout->amount_cents) }}</div>
                    <span class="d-muted">Na aftrek van de succesfee</span>
                </div>
            </section>

            <section aria-labelledby="overzicht">
                <h2 id="overzicht" class="d-h3" style="margin:6px 0 10px">Overzicht</h2>
                <article class="d-listrow">
                    @if ($lot->media->isNotEmpty())
                        <img class="d-listrow-thumb" src="{{ $lot->media->first()->path }}" alt="">
                    @else
                        <span class="d-listrow-thumb" aria-hidden="true"></span>
                    @endif
                    <div class="d-listrow-body">
                        <strong>{{ $lot->title }}</strong>
                        <span class="d-listrow-meta">Kavel #{{ $lot->public_id }} · bestelling {{ $order->order_number }}</span>
                    </div>
                    <div class="d-listrow-right">
                        <b>{{ Money::format($order->hammer_cents) }}</b>
                        <span>Verkocht voor</span>
                    </div>
                </article>

                <div class="d-costs" style="margin-top:12px">
                    <div class="d-row"><span>Succesfee{{ $feePercent ? ' ('.$feePercent.'%)' : '' }}</span><strong>- {{ Money::format($payout->fee_cents) }}</strong></div>
                    <div class="d-row is-total"><span>{{ in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Released], true) ? 'Uitbetaald bedrag' : 'Uit te betalen' }} (netto)</span><strong>{{ Money::format($payout->amount_cents) }}</strong></div>
                </div>

                <div class="d-chips" style="margin-top:12px">
                    @if ($invoice)
                        <a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('invoices.show', $invoice) }}">Bekijk de factuur</a>
                    @endif
                    <a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('orders.show', $order) }}">Naar de bestelling</a>
                </div>
            </section>
        </div>

        <aside class="d-col-aside" style="border:0;background:none;display:flex;flex-direction:column;gap:14px">
            <section class="d-card" aria-label="Status van de uitbetaling">
                <h2 class="d-h3">Status van de uitbetaling</h2>
                <ol class="d-steps">
                    @foreach ($steps as $index => [$label, $when])
                        @php $step = $index + 1; @endphp
                        <li class="{{ $step <= $rank || $rank === 4 ? 'is-done' : ($step === $rank + 1 ? 'is-now' : '') }}">
                            <span class="dot" aria-hidden="true">@if ($step <= $rank || $rank === 4)<x-v4.icon name="check" :size="12" stroke="3" />@endif</span>
                            <div><strong>{{ $label }}</strong><span>{{ $when }}</span></div>
                        </li>
                    @endforeach
                </ol>
            </section>

            <div class="d-callout-info">
                <span class="mark" aria-hidden="true">i</span>
                <div>
                    <strong>Uitbetaling ontvangen?</strong>
                    Het bedrag staat meestal binnen 1 tot 2 werkdagen na vrijgave op je rekening. Na 3 werkdagen nog niets? <a class="d-link" href="{{ route('contact') }}">Neem contact met ons op</a>.
                </div>
            </div>
        </aside>
    </div>
</x-layouts.dashboard>
