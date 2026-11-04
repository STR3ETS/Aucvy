@use('App\Support\Money\Money')
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $invoice->kindLabel() }} {{ $invoice->number }} · Aucvy</title>
    <style>
        :root { color-scheme: light; }
        body { margin: 0; padding: 32px 16px; background: #f3f4f8; color: #0c1326; font: 14px/1.5 Manrope, 'Segoe UI', Helvetica, Arial, sans-serif; }
        .sheet { max-width: 800px; margin: 0 auto; background: #fff; border-radius: 14px; border: 1px solid rgba(14,21,38,.09); padding: 40px; }
        .top { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; }
        h1 { margin: 0; font-size: 24px; }
        .muted { color: #6b7390; }
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 28px; }
        .parties p { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 28px; }
        th, td { padding: 10px 8px; text-align: left; border-bottom: 1px solid rgba(14,21,38,.09); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6b7390; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .totals td { border: 0; padding: 6px 8px; }
        .totals .grand td { font-weight: 800; font-size: 16px; border-top: 2px solid #0c1326; }
        .note { margin-top: 24px; padding: 12px 14px; border-radius: 10px; background: #f7f8fb; font-size: 13px; }
        .actions { max-width: 800px; margin: 0 auto 16px; display: flex; gap: 8px; justify-content: flex-end; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 12px; border: 1px solid rgba(14,21,38,.18); background: #fff; color: #0c1326; font-weight: 700; text-decoration: none; cursor: pointer; font: inherit; }
        .btn.primary { background: #2f6bff; color: #fff; border-color: #2f6bff; }
        @media (max-width: 600px) { .parties { grid-template-columns: 1fr; } .sheet { padding: 24px 18px; } }
        @media print { body { background: #fff; padding: 0; } .sheet { border: 0; border-radius: 0; padding: 0; max-width: none; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions">
        <a href="{{ route('orders.show', $invoice->order) }}" class="btn">Terug naar de bestelling</a>
        <button type="button" class="btn primary" onclick="window.print()">Afdrukken of opslaan als pdf</button>
    </div>
    <main class="sheet">
        <div class="top">
            <div>
                <h1>{{ $invoice->kindLabel() }}</h1>
                <p class="muted">Nummer <strong>{{ $invoice->number }}</strong> · factuurdatum {{ $invoice->issued_at->isoFormat('D MMMM YYYY') }}@if ($invoice->delivery_date) · {{ $invoice->isCredit() ? 'correctiedatum' : 'leverdatum of datum van vooruitbetaling' }} {{ $invoice->delivery_date->isoFormat('D MMMM YYYY') }}@endif</p>
                @if ($invoice->credits)<p class="muted">Op factuur {{ $invoice->credits->number }}</p>@endif
                <p class="muted">Bestelling {{ $invoice->order->order_number }}@if ($invoice->order->lot) · kavel {{ $invoice->order->lot->public_id }}@endif</p>
            </div>
            <div style="text-align:right"><strong style="font-size:20px">Aucvy</strong><br><span class="muted">Het AI-commerceplatform voor zakelijke goederen</span></div>
        </div>

        <div class="parties">
            @foreach (['issuer' => 'Van', 'recipient' => 'Aan'] as $key => $label)
                @php $party = $invoice->parties[$key]; @endphp
                <section>
                    <p class="muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.04em">{{ $label }}</p>
                    <p><strong>{{ $party['name'] }}</strong></p>
                    @if (! empty($party['contact']))<p>t.a.v. {{ $party['contact'] }}</p>@endif
                    @foreach ($party['address'] as $line)<p>{{ $line }}</p>@endforeach
                    @if (! empty($party['kvk']))<p>KVK {{ $party['kvk'] }}</p>@endif
                    @if (! empty($party['vat']))<p>Btw-nummer {{ $party['vat'] }}</p>@endif
                    @if (! empty($party['note']))<p class="muted" style="font-size:12px">{{ $party['note'] }}</p>@endif
                </section>
            @endforeach
        </div>

        <table>
            <thead><tr><th>Omschrijving</th><th class="num">Aantal</th><th class="num">Prijs excl. btw</th><th class="num">Btw</th><th class="num">Bedrag excl. btw</th></tr></thead>
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
            <tr><td></td><td class="num">{{ $invoice->vat_regime->isDeductible() ? 'Btw '.rtrim(rtrim(number_format(Money::vatRatePercent(), 1, ',', ''), '0'), ',').'%' : 'Btw (margeregeling)' }}</td><td class="num">{{ Money::format($invoice->vat_cents) }}</td></tr>
            <tr class="grand"><td></td><td class="num">Totaal</td><td class="num">{{ Money::format($invoice->total_cents) }}</td></tr>
        </table>

        @if ($invoice->note)
            <p class="note">{{ $invoice->note }}</p>
        @endif
        @if (! $invoice->vat_regime->isDeductible() && $invoice->kind !== 'admin_fee' && $invoice->kind !== 'fee')
            <p class="note">Bijzondere regeling - gebruikte goederen. Op deze factuur wordt geen btw in rekening gebracht; de btw is niet aftrekbaar.</p>
        @endif
        <p class="muted" style="margin-top:28px;font-size:12px">Betaald via Aucvy. Vragen over deze factuur: <a href="{{ route('contact') }}">neem contact op</a>.</p>
    </main>
</body>
</html>
