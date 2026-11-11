@props([
    // De uitleg: één of twee gewone zinnen.
    'text',
])

{{--
    Uitleg bij een kaart: een vraagteken naast de titel met de tekst in een
    tooltip bij aanwijzen en bij focus. Zo staat er geen alinea bovenin de
    kaart en blijft het scherm rustig.
--}}
@php $helpId = 'uitleg-'.\Illuminate\Support\Str::random(6); @endphp
<span {{ $attributes->merge(['class' => 'd-help']) }}>
    <button type="button" class="d-help-btn" aria-label="Uitleg" aria-describedby="{{ $helpId }}" @keydown.escape="$el.blur()"><x-v4.icon name="help" :size="16" stroke="2" /></button>
    <span id="{{ $helpId }}" class="d-help-tip" role="tooltip">{{ $text }}</span>
</span>
