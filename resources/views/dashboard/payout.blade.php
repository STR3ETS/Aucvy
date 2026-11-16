@use('App\Enums\PayoutStatus')
@use('App\Support\Money\Money')

@php
    $order = $payout->order;
    $lot = $order->lot;
    $company = $payout->seller?->company ?? auth()->user()->company;
    $displayTimezone = config('aucvy.display_timezone');
    $at = fn ($moment): ?string => $moment?->timezone($displayTimezone)->isoFormat('D MMM, HH:mm');
    $isOut = in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Released], true);

    // Kop en stand per status: nooit "verzonden" beloven als het bedrag nog wordt vastgehouden.
    [$title, $intro, $statusTone, $statusLabel] = match ($payout->status) {
        PayoutStatus::Paid => ['Uitbetaling verzonden', 'De opbrengst van je verkochte kavel is overgemaakt naar je bankrekening.', 'tone-ok', 'Verzonden'],
        PayoutStatus::Released => ['Uitbetaling vrijgegeven', 'De houdtermijn is voorbij; het bedrag is onderweg naar je rekening.', 'tone-ok', 'Vrijgegeven'],
        PayoutStatus::Held => ['Uitbetaling in de houdtermijn', 'De koper kan nog een probleem melden; daarna volgt je uitbetaling.', 'tone-atlas', 'In houdtermijn'],
        PayoutStatus::Failed => ['Uitbetaling niet gelukt', 'De overboeking is niet aangekomen. Hieronder staat waarom.', 'tone-warn', 'Niet gelukt'],
        default => ['Uitbetaling ingepland', 'Na de levering start de houdtermijn; daarna volgt je uitbetaling.', 'tone-grey', 'Ingepland'],
    };

    // Alleen als er iets mis is een melding bovenaan; de gewone stand staat in de kaart en de stappen.
    $problem = match (true) {
        $payout->blocked_reason !== null => ['Deze uitbetaling wordt aangehouden', $payout->blocked_reason],
        $payout->status === PayoutStatus::Failed => ['De uitbetaling is niet gelukt', $payout->failure_reason ?: 'Controleer je bankrekening bij je bedrijfsgegevens of neem contact op.'],
        default => null,
    };
    if ($payout->blocked_reason !== null) {
        [$statusTone, $statusLabel] = ['tone-warn', 'Aangehouden'];
    }

    // Wanneer het geld komt, of kwam.
    [$whenLabel, $whenValue] = match (true) {
        $payout->paid_at !== null => ['Verzonden', $at($payout->paid_at)],
        $payout->released_at !== null => ['Vrijgegeven', $at($payout->released_at)],
        $payout->hold_until !== null => ['Vrijgave', $at($payout->hold_until)],
        default => ['Vrijgave', 'Na de houdtermijn'],
    };

    $rank = match ($payout->status) {
        // Het aantal stappen dat af is; tijdens de houdtermijn is alleen de verkoop afgerond.
        PayoutStatus::Released => 3,
        PayoutStatus::Paid => 4,
        default => 1,
    };
    $steps = [
        ['Verkoop afgerond', $at($order->paid_at ?? $order->created_at)],
        ['Houdtermijn voorbij', $rank > 2 ? $at($payout->released_at ?? $payout->hold_until) : ($payout->hold_until ? 'Op '.$at($payout->hold_until) : 'Na de levering')],
        ['Vrijgegeven', $at($payout->released_at) ?? 'Na de houdtermijn'],
        ['Op je rekening', $at($payout->paid_at) ?? '1 tot 2 werkdagen na vrijgave'],
    ];
    $feePercent = $order->hammer_cents > 0 ? rtrim(rtrim(number_format($payout->fee_cents / $order->hammer_cents * 100, 1, ',', '.'), '0'), ',') : null;
@endphp

{{--
    Eén uitbetaling, in dezelfde opzet als de factuur: links de afrekening en
    de stappen, rechts een meeschuivende kaart met het nettobedrag, de stand,
    de rekening en de acties als pictogram.
--}}
<x-layouts.dashboard :title="$title" eyebrow="Uitbetalingen" :intro="$intro">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.payouts') }}">Uitbetalingen</a> <span aria-hidden="true">/</span> <span>{{ $order->order_number }}</span>
    </x-slot:crumbs>

    <div class="d-split">
        <aside class="d-card d-split-side" aria-label="Uitbetaling {{ $order->order_number }}">
            <div class="d-split-side-head">
                <span class="thumb" aria-hidden="true"><x-v4.icon name="banknote" :size="22" stroke="1.8" /></span>
                <div>
                    <span class="d-bid-meta">{{ $isOut ? 'Uitbetaald bedrag' : 'Uit te betalen' }}, netto</span>
                    <strong>{{ $lot->title }}</strong>
                </div>
            </div>

            <div class="d-split-side-amount">
                <b class="tabular">{{ Money::format($payout->amount_cents) }}</b>
                <span class="d-tag {{ $statusTone }}">{{ $statusLabel }}</span>
            </div>

            <dl class="d-split-side-facts">
                <dt>{{ $whenLabel }}</dt><dd>{{ $whenValue }}</dd>
                <dt>Naar rekening</dt>
                <dd class="tabular">{{ $company?->payout_iban_last4 ? 'NL·· '.$company->payout_iban_last4 : 'Nog geen rekening' }}</dd>
                <dt>Bestelling</dt><dd class="tabular">{{ $order->order_number }}</dd>
            </dl>

            <div class="d-split-side-actions is-icons">
                @if ($invoice)
                    <a class="d-iconbtn" href="{{ route('invoices.show', $invoice) }}" data-tip="Bekijk de factuur" aria-label="Factuur {{ $invoice->number }} bekijken"><x-v4.icon name="file-text" :size="17" stroke="2" /></a>
                @endif
                <a class="d-iconbtn" href="{{ route('orders.show', $order) }}" data-tip="Naar de bestelling" aria-label="Naar bestelling {{ $order->order_number }}"><x-v4.icon name="shopping-bag" :size="17" stroke="2" /></a>
                <a class="d-iconbtn" href="{{ route('organisation.show') }}#rekening" data-tip="{{ $company?->payout_iban_last4 ? 'Rekening wijzigen' : 'Rekening toevoegen' }}" aria-label="{{ $company?->payout_iban_last4 ? 'Uitbetalingsrekening wijzigen' : 'Uitbetalingsrekening toevoegen' }}"><x-v4.icon name="pencil" :size="17" stroke="2" /></a>
            </div>
        </aside>

        <div class="d-split-main">
            @if ($problem)
                <div class="d-callout-warn" role="alert">
                    <span class="mark" aria-hidden="true">!</span>
                    <div class="text" style="flex:1 1 240px;min-width:0">
                        <strong>{{ $problem[0] }}</strong>
                        <span>{{ $problem[1] }}</span>
                    </div>
                </div>
            @endif

            <section class="d-card" aria-labelledby="stand">
                <div class="d-titleline">
                    <h2 id="stand" class="d-h3">Stand van de uitbetaling</h2>
                    <x-dashboard.help text="Het bedrag staat meestal binnen 1 tot 2 werkdagen na vrijgave op je rekening. Na 3 werkdagen nog niets? Neem dan contact met ons op." />
                </div>
                <ol class="d-hsteps" aria-label="Stappen van de uitbetaling">
                    @foreach ($steps as $index => [$label, $when])
                        @php $done = $index < $rank; @endphp
                        <li class="{{ $done ? 'is-done' : ($index === $rank ? 'is-now' : '') }}">
                            <span class="dot" aria-hidden="true">@if ($done)<x-v4.icon name="check" :size="13" stroke="3" />@endif</span>
                            <strong>{{ $label }}</strong>
                            <span>{{ $when }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="d-card" aria-labelledby="afrekening">
                <h2 id="afrekening" class="d-h3">Afrekening</h2>
                <div class="d-payout-lot">
                    @if ($lot->media->isNotEmpty())
                        <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                    @else
                        <span aria-hidden="true"><x-v4.icon name="image" :size="20" stroke="1.6" /></span>
                    @endif
                    <div>
                        <strong>{{ $lot->title }}</strong>
                        <span class="d-bid-meta">Kavel #{{ $lot->public_id }} · bestelling {{ $order->order_number }}</span>
                    </div>
                </div>
                <dl class="d-payout-sum">
                    <dt>Verkocht voor</dt><dd>{{ Money::format($order->hammer_cents) }}</dd>
                    <dt>Succesfee{{ $feePercent ? ' ('.$feePercent.'%)' : '' }}</dt><dd>- {{ Money::format($payout->fee_cents) }}</dd>
                    <dt class="is-total">{{ $isOut ? 'Uitbetaald' : 'Uit te betalen' }}, netto</dt><dd class="is-total">{{ Money::format($payout->amount_cents) }}</dd>
                </dl>
            </section>
        </div>
    </div>
</x-layouts.dashboard>
