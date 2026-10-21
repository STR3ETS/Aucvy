@props(['field'])

{{--
    Herkomstbadge onder een veld in de funnel: wie heeft dit ingevuld en hoe
    zeker is dat. Onder de drempel staat er een "Klopt"-knop; zonder die
    bevestiging weigert het publiceren (briefing, vertrouwensregel 4).

    Twee losse SVG's met x-show: een <template> binnen een <svg> is geen
    HTML-template en laat Alpine struikelen.
--}}
<template x-if="fields['{{ $field }}']">
    <span class="prov"
          :class="{ 'is-warn': fields['{{ $field }}'].needs_confirmation, 'is-human': fields['{{ $field }}'].source === 'seller' }">
        <svg class="prov-icon" x-show="fields['{{ $field }}'].source === 'seller'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm-6 6a6 6 0 0 1 12 0"/>
        </svg>
        <svg class="prov-icon" x-show="fields['{{ $field }}'].source !== 'seller'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10 3v2m0 10v2m7-7h-2M5 10H3m11.5-4.5-1.4 1.4M6.9 13.1l-1.4 1.4m9 0-1.4-1.4M6.9 6.9 5.5 5.5M10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/>
        </svg>
        <span x-text="fields['{{ $field }}'].label"></span>
        <button type="button" x-show="fields['{{ $field }}'].needs_confirmation" @click="confirm(['{{ $field }}'])">Klopt</button>
    </span>
</template>
