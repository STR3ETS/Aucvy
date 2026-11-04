@props([
    'costs',
    'title' => null,
    'note' => null,
    // Op de kavelpagina is het transportbedrag een vanaf-tarief; deze
    // toelichting maakt dat expliciet zonder de orderweergave te raken.
    'transportNote' => null,
])

{{--
    De kostenopbouw komt volledig uit App\Support\Money.

    Er wordt hier niets opgeteld en niets omgerekend: het totaal dat hier staat
    is hetzelfde getal dat straks op de order en bij de betaalprovider staat.
    Zouden ze uiteenlopen, dan is dat per definitie een bug en geen afronding
    (hfst. 9). Opmaak: de grijze tabel uit het ontwerp van november.
--}}
<div {{ $attributes }}>
    <h3 class="d-h3" style="font-size:14px;font-weight:700">{{ $title ?? __('Kosten vóór je biedt') }}</h3>

    <div class="d-costs" style="margin-top:8px">
        @foreach ($costs->lines() as $line)
            @php $isIndicativeTransport = $transportNote !== null && $line->key === 'transport' && $costs->transportCents > 0; @endphp
            <div class="d-row">
                <span>
                    {{ $line->label }}
                    @if ($line->note)
                        <small>{{ $line->note }}</small>
                    @elseif ($isIndicativeTransport)
                        <small>{{ $transportNote }}</small>
                    @endif
                </span>
                <strong>{{ $isIndicativeTransport ? 'vanaf ' : '' }}{{ $line->formatted() }}</strong>
            </div>
        @endforeach
        <div class="d-row is-total">
            <span>Jij betaalt</span>
            <strong>{{ \App\Support\Money\Money::format($costs->totalCents) }}</strong>
        </div>
    </div>

    @if ($note)
        <p class="d-hint" style="margin-top:8px">{{ $note }}</p>
    @endif
</div>
