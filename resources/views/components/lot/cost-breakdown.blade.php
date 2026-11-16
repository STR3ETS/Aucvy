@props([
    'costs',
    'title' => null,
    'note' => null,
    // Op de kavelpagina is het transportbedrag een vanaf-tarief; deze
    // toelichting maakt dat expliciet zonder de orderweergave te raken.
    'transportNote' => null,
    // Wie het totaal betaalt: op de bestelpagina van de verkoper is dat de koper.
    'totalLabel' => 'Jij betaalt',
    // Ingeklapt tot één regel met het totaal; de opbouw klapt open (kavelpagina).
    'fold' => false,
])

{{--
    De kostenopbouw komt volledig uit App\Support\Money.

    Er wordt hier niets opgeteld en niets omgerekend: het totaal dat hier staat
    is hetzelfde getal dat straks op de order en bij de betaalprovider staat.
    Zouden ze uiteenlopen, dan is dat per definitie een bug en geen afronding
    (hfst. 9). Opmaak: de grijze tabel uit het ontwerp van november.
--}}
@php
    $lines = function () use ($costs, $transportNote, $totalLabel): string {
        $html = '';
        foreach ($costs->lines() as $line) {
            $isIndicativeTransport = $transportNote !== null && $line->key === 'transport' && $costs->transportCents > 0;
            $small = $line->note ?: ($isIndicativeTransport ? $transportNote : null);
            $html .= '<div class="d-row"><span>'.e($line->label).($small ? '<small>'.e($small).'</small>' : '').'</span><strong>'.($isIndicativeTransport ? 'vanaf ' : '').e($line->formatted()).'</strong></div>';
        }

        return $html.'<div class="d-row is-total"><span>'.e($totalLabel).'</span><strong>'.e(\App\Support\Money\Money::format($costs->totalCents)).'</strong></div>';
    };
@endphp

@if ($fold)
    <details {{ $attributes->merge(['class' => 'd-costfold']) }}>
        <summary>
            <span>{{ $title ?? $totalLabel }}</span>
            <strong class="tabular">{{ \App\Support\Money\Money::format($costs->totalCents) }}</strong>
            <x-v4.icon name="chevron-down" :size="16" stroke="2.2" />
        </summary>
        <div class="d-costs">{!! $lines() !!}</div>
        @if ($note)
            <p class="d-hint" style="margin-top:8px">{{ $note }}</p>
        @endif
    </details>
@else
    <div {{ $attributes }}>
        <h3 class="d-h3" style="font-size:14px;font-weight:700">{{ $title ?? __('Kosten vóór je biedt') }}</h3>

        <div class="d-costs" style="margin-top:8px">{!! $lines() !!}</div>

        @if ($note)
            <p class="d-hint" style="margin-top:8px">{{ $note }}</p>
        @endif
    </div>
@endif
