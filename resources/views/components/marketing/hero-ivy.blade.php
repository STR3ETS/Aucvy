@props([
    // De vraag van de bezoeker en het antwoord van Ivy, als voorbeeldgesprek.
    'question',
    'answer',
    // Korte vervolgvragen onder het antwoord.
    'chips' => [],
    // Wat er naar Ivy gaat bij een klik; standaard de vraag zelf.
    'ask' => null,
])

{{--
    Het venster rechts in de paginakop (ontwerp van november): een klein
    voorbeeldgesprek met Ivy. Staat Ivy aan, dan opent een klik het echte
    paneel met deze vraag; anders is het alleen een voorbeeld.
--}}
@php $live = (bool) config('aucvy.ai.enabled') && \App\Ivy\Chat\IvyFlags::on('ivy.enabled'); @endphp

<{{ $live ? 'button type="button"' : 'div' }} class="d-hero-ivy"
    @if ($live) @click="$dispatch('ivy-open', { text: @js($ask ?? $question) })" aria-label="Stel deze vraag aan Ivy" @endif>
    <span class="top">
        <span class="lights" aria-hidden="true"><i></i><i></i><i></i></span>
        <b>Aucvy</b>
        <span>Ivy, jouw AI-hulp</span>
    </span>
    <span class="q">{{ $question }}</span>
    <span class="a"><img src="{{ asset('images/agents/ivy.jpg') }}" alt=""><span>{{ $answer }}</span></span>
    @if ($chips !== [])
        <span class="chips">
            @foreach ($chips as $chip)
                <span>{{ $chip }}</span>
            @endforeach
        </span>
    @endif
</{{ $live ? 'button' : 'div' }}>
