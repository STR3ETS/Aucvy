@props(['size' => 'base', 'theme' => 'light'])

{{--
    Telt af op de klok van de server, niet die van de bezoeker.

    De offset wordt bij het laden bepaald: een telefoon die tien seconden
    voorloopt is normaal, een aftelling die daardoor fout staat is dat niet
    (hfst. 11).

    `tabular` geeft cijfers een vaste breedte, zodat de tekst niet danst bij
    elke seconde - en zodat oude en nieuwe waarde elkaar niet overlappen
    tijdens de animatie (mobiele buildlijst).

    `theme="dark"` is voor donkere of blauwe vlakken: daar moet de
    slotfasekleur licht zijn in plaats van donker amber.
--}}
<span
    x-cloak
    class="tabular {{ $size === 'lg' ? 'text-2xl font-extrabold' : 'font-semibold' }}"
    :class="{
        '{{ $theme === 'dark' ? 'text-amber-200' : 'text-aj-warn-text' }}': status === 'closing',
        '{{ $theme === 'dark' ? 'text-white/60' : 'text-aj-muted' }}': status === 'closed',
    }"
    x-text="countdownLabel"
    {{ $attributes }}
></span>
