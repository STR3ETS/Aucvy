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
    (hfst. 9).
--}}
<div {{ $attributes->merge(['class' => 'rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4']) }}>
    <p class="text-sm font-extrabold">{{ $title ?? __('Kosten vóór je biedt') }}</p>

    <dl class="mt-3 space-y-1.5 text-sm">
        @foreach ($costs->lines() as $line)
            <div class="flex items-baseline justify-between gap-4">
                @php $isIndicativeTransport = $transportNote !== null && $line->key === 'transport' && $costs->transportCents > 0; @endphp
                <dt class="text-aj-muted">
                    {{ $line->label }}
                    @if ($line->note)
                        <span class="block text-xs">{{ $line->note }}</span>
                    @elseif ($isIndicativeTransport)
                        <span class="block text-xs">{{ $transportNote }}</span>
                    @endif
                </dt>
                <dd class="tabular shrink-0 font-semibold">{{ $isIndicativeTransport ? 'vanaf ' : '' }}{{ $line->formatted() }}</dd>
            </div>
        @endforeach

        <div class="flex items-baseline justify-between gap-4 border-t border-aj-border pt-2">
            <dt class="font-extrabold">{{ __('money.lines.total') }}</dt>
            <dd class="tabular shrink-0 text-lg font-extrabold">
                {{ \App\Support\Money\Money::format($costs->totalCents) }}
            </dd>
        </div>
    </dl>

    @if ($note)
        <p class="mt-3 text-xs text-aj-muted">{{ $note }}</p>
    @endif
</div>
