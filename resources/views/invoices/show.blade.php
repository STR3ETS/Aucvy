@use('App\Support\Money\Money')

@php
    $order = $invoice->order;
    $issuer = $invoice->parties['issuer'];
    $recipient = $invoice->parties['recipient'];
    $isMargin = ! $invoice->vat_regime->isDeductible() && $invoice->kind !== 'admin_fee' && $invoice->kind !== 'fee';
    $vatLabel = $invoice->vat_regime->isDeductible() ? 'Btw '.rtrim(rtrim(number_format(Money::vatRatePercent(), 1, ',', ''), '0'), ',').'%' : 'Btw (margeregeling)';
    $isPaid = $order->paid_at !== null || $invoice->isCredit();
    $party = fn (array $party): string => implode(', ', array_values(array_filter(array_merge($party['address'] ?? [], [! empty($party['kvk']) ? 'KVK '.$party['kvk'] : null, ! empty($party['vat']) ? 'Btw-nummer '.$party['vat'] : null]))));
@endphp

{{--
    De factuur in de dashboardschil, zoals in het ontwerp van november: het
    vel links, rechts afdrukken, de uitleg over btw en de weg terug naar de
    bestelling. De inhoud komt uit de factuur zelf (App\Orders\Invoicing).
--}}
<x-layouts.dashboard :title="$invoice->kindLabel().' '.$invoice->number" :heading="$invoice->kindLabel()" eyebrow="Bestelling"
    intro="Altijd terug te vinden bij je bestelling. Druk hem af of bewaar hem als pdf voor je administratie.">
    <x-slot:crumbs>
        <a href="{{ route('orders.index') }}">Bestellingen</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a>
        <span aria-hidden="true">/</span>
        <span>{{ $invoice->kindLabel() }}</span>
    </x-slot:crumbs>

    <div class="d-chips d-print-hide" style="margin-bottom:14px">
        <span class="d-chip is-on">{{ $isMargin ? 'Margeregeling' : $vatLabel }}</span>
        @if ($invoice->credits)
            <a class="d-chip" href="{{ route('invoices.show', $invoice->credits) }}">Op factuur {{ $invoice->credits->number }}</a>
        @endif
    </div>

    <div class="d-cols">
        <main class="d-sheet" style="flex:999 1 440px;min-width:0">
            <div class="d-sheet-top">
                <div>
                    <h2>{{ $issuer['name'] }}</h2>
                    @if (! empty($issuer['contact']))<p class="d-muted" style="margin:4px 0 0">t.a.v. {{ $issuer['contact'] }}</p>@endif
                    <p class="d-muted" style="margin:4px 0 0">{{ $party($issuer) }}</p>
                    @if (! empty($issuer['note']))<p class="d-hint">{{ $issuer['note'] }}</p>@endif
                </div>
                <div style="text-align:right">
                    <div class="big">{{ $invoice->kindLabel() }}</div>
                    <span class="d-tag {{ $isPaid ? 'tone-ok' : 'tone-warn' }}" style="margin-top:10px">{{ $isPaid ? ($invoice->isCredit() ? 'Verrekend' : 'Betaald') : 'Wordt definitief na betaling' }}</span>
                </div>
            </div>

            <div class="d-sheet-meta">
                <div>
                    <div class="kicker">Aan</div>
                    <p><strong>{{ $recipient['name'] }}</strong></p>
                    @if (! empty($recipient['contact']))<p>t.a.v. {{ $recipient['contact'] }}</p>@endif
                    <p>{{ $party($recipient) }}</p>
                    @if (! empty($recipient['note']))<p class="d-hint">{{ $recipient['note'] }}</p>@endif
                </div>
                <dl class="d-sheet-kv">
                    <dt>Factuurnummer</dt><dd>{{ $invoice->number }}</dd>
                    <dt>Factuurdatum</dt><dd>{{ $invoice->issued_at->isoFormat('D MMM YYYY') }}</dd>
                    @if ($invoice->delivery_date)
                        <dt>{{ $invoice->isCredit() ? 'Correctiedatum' : ($order->delivered_at ? 'Leverdatum' : 'Vooruitbetaling') }}</dt><dd>{{ $invoice->delivery_date->isoFormat('D MMM YYYY') }}</dd>
                    @endif
                    <dt>Bestelling</dt><dd>{{ $order->order_number }}</dd>
                    @if ($order->lot)
                        <dt>Kavel</dt><dd>#{{ $order->lot->public_id }}</dd>
                    @endif
                </dl>
            </div>

            <table>
                <thead><tr><th>Omschrijving</th><th class="num">Aantal</th><th class="num">Prijs excl. btw</th><th class="num">Btw</th><th class="num">Bedrag</th></tr></thead>
                <tbody>
                    @foreach ($invoice->lines as $line)
                        <tr>
                            <td>{{ $line['description'] }}</td>
                            <td class="num">{{ $line['quantity'] }}</td>
                            <td class="num">{{ Money::format((int) $line['unit_cents']) }}</td>
                            <td class="num">{{ $line['vat_rate'] > 0 ? rtrim(rtrim(number_format((float) $line['vat_rate'], 1, ',', ''), '0'), ',').'%' : '0%' }}</td>
                            <td class="num">{{ Money::format((int) $line['total_cents']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totals" style="margin-top:12px">
                <tr><td></td><td class="num" style="width:220px">Subtotaal excl. btw</td><td class="num" style="width:140px">{{ Money::format($invoice->subtotal_cents) }}</td></tr>
                <tr><td></td><td class="num">{{ $vatLabel }}</td><td class="num">{{ Money::format($invoice->vat_cents) }}</td></tr>
                <tr class="grand"><td></td><td class="num">Totaal</td><td class="num">{{ Money::format($invoice->total_cents) }}</td></tr>
            </table>

            @if ($invoice->note)
                <p class="note">{{ $invoice->note }}</p>
            @endif
            @if ($isMargin)
                <p class="note">Bijzondere regeling - gebruikte goederen. Op deze factuur wordt geen btw in rekening gebracht; de btw is niet aftrekbaar.</p>
            @endif

            <p class="d-note" style="margin-top:20px">
                @if ($invoice->isCredit())
                    Deze creditnota verrekent de genoemde factuur. Een terugbetaling loopt via het platform.
                @elseif (! $isPaid && $order->payment_deadline_at)
                    Betaal uiterlijk {{ $order->payment_deadline_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY, HH:mm') }} via het platform. De verkoper ontvangt het geld na de houdtermijn.
                @else
                    Betaald via Aucvy. De verkoper ontvangt het geld na de houdtermijn.
                @endif
            </p>
            <p class="foot">Opgemaakt door Aucvy B.V. {{ $invoice->kind === 'lot' ? 'namens de verkoper' : '' }}. Vragen over deze factuur? Stel ze via je bestelling of <a href="{{ route('contact') }}">neem contact op</a>. Factuurdatum, leverdatum en nummers staan hierboven; bewaar deze factuur voor je administratie (factuurdatum {{ $invoice->issued_at->isoFormat('D MMMM YYYY') }}@if ($invoice->delivery_date), {{ $invoice->isCredit() ? 'correctiedatum' : 'leverdatum of datum van vooruitbetaling' }} {{ $invoice->delivery_date->isoFormat('D MMMM YYYY') }}@endif).</p>
        </main>

        <aside class="d-print-hide" style="flex:1 1 240px;min-width:0;display:flex;flex-direction:column;gap:12px">
            <div class="d-card d-stack-tight">
                <button type="button" class="d-btn d-btn-wide d-btn-sq" onclick="window.print()">Download PDF</button>
                <button type="button" class="d-btn d-btn-secondary d-btn-wide d-btn-sq" onclick="window.print()">Afdrukken</button>
            </div>
            @if ($isMargin)
                <div class="d-card">
                    <h3 class="d-h3">Waarom staat er geen btw?</h3>
                    <p class="d-sub">Bij de margeregeling mag de btw niet apart op de factuur staan. Je betaalt het totaalbedrag, zonder btw erbovenop.</p>
                </div>
            @endif
            <a class="d-aside-link" href="{{ route('orders.show', $order) }}">Naar je bestelling <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
            <a class="d-aside-link" href="{{ route('orders.show', $order) }}#berichten">Vraag over deze factuur <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
        </aside>
    </div>
</x-layouts.dashboard>
