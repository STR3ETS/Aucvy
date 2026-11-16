@use('App\Support\Money\Money')

@php
    $order = $invoice->order;
    $issuer = $invoice->parties['issuer'];
    $recipient = $invoice->parties['recipient'];
    $isMargin = ! $invoice->vat_regime->isDeductible() && $invoice->kind !== 'admin_fee' && $invoice->kind !== 'fee';
    $vatLabel = $invoice->vat_regime->isDeductible() ? 'Btw '.rtrim(rtrim(number_format(Money::vatRatePercent(), 1, ',', ''), '0'), ',').'%' : 'Btw (margeregeling)';
    $isPaid = $order->paid_at !== null || $invoice->isCredit();
    $party = fn (array $party): string => implode(', ', array_values(array_filter(array_merge($party['address'] ?? [], [! empty($party['kvk']) ? 'KVK '.$party['kvk'] : null, ! empty($party['vat']) ? 'Btw-nummer '.$party['vat'] : null]))));
    $user = auth()->user();
    $isBuyer = $user->id === $order->buyer_id;

    // De andere facturen van deze bestelling, als tabs: de koper ziet kavel en administratiekosten, de verkoper kavel en succesfee.
    $visibleKinds = $user->is_staff && ! in_array($user->id, [$order->buyer_id, $order->seller_id], true)
        ? ['lot', 'admin_fee', 'fee', 'credit']
        : ($isBuyer ? ['lot', 'admin_fee', 'credit'] : ['lot', 'fee', 'credit']);
    $siblings = $order->invoices->filter(fn ($sibling) => in_array($sibling->kind, $visibleKinds, true))->values();
    $tabLabel = fn ($sibling): string => match ($sibling->kind) {
        'lot' => 'Kavel',
        'admin_fee' => 'Administratiekosten',
        'fee' => 'Succesfee',
        'credit' => 'Creditnota',
        default => $sibling->kindLabel(),
    };

    // De verplichte zin van de margeregeling staat soms al in de notitie; één keer is genoeg.
    $notes = array_values(array_filter([$invoice->note]));
    if ($isMargin) {
        $notes[] = str_contains((string) $invoice->note, 'Bijzondere regeling')
            ? 'Op deze factuur wordt geen btw in rekening gebracht; de btw is niet aftrekbaar.'
            : 'Bijzondere regeling - gebruikte goederen. Op deze factuur wordt geen btw in rekening gebracht; de btw is niet aftrekbaar.';
    }

    $status = match (true) {
        $invoice->isCredit() => ['tone-ok', 'Verrekend'],
        $isPaid => ['tone-ok', 'Betaald'],
        default => ['tone-warn', 'Definitief na betaling'],
    };
@endphp

{{--
    De factuur in de dashboardschil. Bovenaan één kop met het bedrag, de stand
    en de acties als pictogram, met de andere facturen van de bestelling als
    tabs. Daaronder het vel zelf, dat ook precies zo wordt afgedrukt. De
    inhoud komt uit de factuur (App\Orders\Invoicing).
--}}
<x-layouts.dashboard :title="$invoice->kindLabel().' '.$invoice->number" :heading="$invoice->kindLabel().' '.$invoice->number" eyebrow="Bestelling {{ $order->order_number }}"
    intro="Altijd terug te vinden bij je bestelling. Bewaar hem als pdf voor je administratie.">
    <x-slot:crumbs>
        <a href="{{ route('orders.index') }}">Bestellingen</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('orders.show', $order) }}">{{ $order->order_number }}</a>
        <span aria-hidden="true">/</span>
        <span>{{ $invoice->kindLabel() }}</span>
    </x-slot:crumbs>

    <div class="d-invoice">
        <section class="d-card d-orderhead d-print-hide" aria-label="{{ $invoice->kindLabel() }} {{ $invoice->number }}">
            <div class="d-orderhead-top">
                <span class="thumb" aria-hidden="true"><x-v4.icon name="file-text" :size="24" stroke="1.8" /></span>

                <div class="text">
                    <strong class="d-orderhead-title">{{ $order->lot?->title ?? $invoice->kindLabel() }}</strong>
                    <span class="d-lotrow-tags">
                        <span class="d-tag {{ $status[0] }}">{{ $status[1] }}</span>
                        <span class="d-bid-meta">{{ $tabLabel($invoice) }} · {{ $isMargin ? 'Margeregeling' : $vatLabel }} · {{ $invoice->issued_at->isoFormat('D MMM YYYY') }}</span>
                        @if ($isMargin)
                            <x-dashboard.help text="Bij de margeregeling mag de btw niet apart op de factuur staan. Je betaalt het totaalbedrag, zonder btw erbovenop." />
                        @endif
                        @if ($invoice->credits)
                            <a class="d-bid-meta d-link" href="{{ route('invoices.show', $invoice->credits) }}">Op factuur {{ $invoice->credits->number }}</a>
                        @endif
                    </span>
                </div>

                <div class="d-orderhead-amount">
                    <b class="tabular">{{ Money::format($invoice->total_cents) }}</b>
                    <span>{{ $invoice->vat_regime->isDeductible() ? 'Totaal, met btw' : 'Totaal' }}</span>
                </div>

                <div class="d-lotrow-actions">
                    <a href="{{ route('orders.show', $order) }}" class="d-iconbtn" data-tip="Naar de bestelling" aria-label="Naar bestelling {{ $order->order_number }}"><x-v4.icon name="shopping-bag" :size="17" stroke="2" /></a>
                    <a href="{{ route('orders.show', $order) }}#berichten" class="d-iconbtn" data-tip="Vraag over deze factuur" aria-label="Een vraag stellen over deze factuur"><x-v4.icon name="message-circle" :size="17" stroke="2" /></a>
                    <button type="button" class="d-btn d-btn-sq" onclick="window.print()"><x-v4.icon name="download" :size="16" stroke="2.2" /> Download pdf</button>
                </div>
            </div>
        </section>

        @if ($siblings->count() > 1)
            <nav class="d-tabs d-print-hide" aria-label="Facturen bij deze bestelling">
                @foreach ($siblings as $sibling)
                    <a href="{{ route('invoices.show', $sibling) }}" @class(['is-on' => $sibling->is($invoice)]) @if ($sibling->is($invoice)) aria-current="page" @endif>{{ $tabLabel($sibling) }}</a>
                @endforeach
            </nav>
        @endif

        <article class="d-sheet">
            <div class="d-sheet-top">
                <div>
                    <h2>{{ $issuer['name'] }}</h2>
                    @if (! empty($issuer['contact']))<p class="d-muted" style="margin:4px 0 0">t.a.v. {{ $issuer['contact'] }}</p>@endif
                    <p class="d-muted" style="margin:4px 0 0">{{ $party($issuer) }}</p>
                    @if (! empty($issuer['note']))<p class="d-hint">{{ $issuer['note'] }}</p>@endif
                </div>
                <div style="text-align:right">
                    <div class="big">{{ $invoice->isCredit() ? 'Creditnota' : 'Factuur' }}</div>
                    <span class="d-tag {{ $status[0] }}" style="margin-top:10px">{{ $status[1] }}</span>
                </div>
            </div>

            <div class="d-sheet-meta">
                <div>
                    <div class="d-sheet-kicker">Aan</div>
                    <p><strong>{{ $recipient['name'] }}</strong></p>
                    @if (! empty($recipient['contact']))<p>t.a.v. {{ $recipient['contact'] }}</p>@endif
                    <p>{{ $party($recipient) }}</p>
                    @if (! empty($recipient['note']))<p class="d-hint">{{ $recipient['note'] }}</p>@endif
                </div>
                <dl class="d-sheet-kv">
                    <dt>Factuurnummer</dt><dd>{{ $invoice->number }}</dd>
                    <dt>Factuurdatum</dt><dd>{{ $invoice->issued_at->isoFormat('D MMM YYYY') }}</dd>
                    @if ($invoice->delivery_date)
                        <dt>{{ $invoice->isCredit() ? 'Correctiedatum' : ($order->delivered_at ? 'Leverdatum' : 'Datum vooruitbetaling') }}</dt><dd>{{ $invoice->delivery_date->isoFormat('D MMM YYYY') }}</dd>
                    @endif
                    <dt>Bestelling</dt><dd>{{ $order->order_number }}</dd>
                    @if ($order->lot)
                        <dt>Kavel</dt><dd>#{{ $order->lot->public_id }}</dd>
                    @endif
                </dl>
            </div>

            <div class="d-sheet-scroll">
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
            </div>

            <dl class="d-sheet-totals">
                <dt>Subtotaal excl. btw</dt><dd>{{ Money::format($invoice->subtotal_cents) }}</dd>
                <dt>{{ $vatLabel }}</dt><dd>{{ Money::format($invoice->vat_cents) }}</dd>
                <dt class="grand">Totaal</dt><dd class="grand">{{ Money::format($invoice->total_cents) }}</dd>
            </dl>

            @if ($notes !== [])
                <p class="note">{{ implode(' ', $notes) }}</p>
            @endif

            <div class="d-sheet-foot">
                <p>
                    @if ($invoice->isCredit())
                        Deze creditnota verrekent de genoemde factuur. Een terugbetaling loopt via het platform.
                    @elseif (! $isPaid && $order->payment_deadline_at)
                        Betaal uiterlijk {{ $order->payment_deadline_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY, HH:mm') }} via het platform. De verkoper ontvangt het geld na de houdtermijn.
                    @else
                        Betaald via Aucvy. De verkoper ontvangt het geld na de houdtermijn.
                    @endif
                </p>
                <p>Vragen over deze factuur? Stel ze via je bestelling of <a href="{{ route('contact') }}">neem contact op</a>.</p>
            </div>
        </article>
    </div>
</x-layouts.dashboard>
