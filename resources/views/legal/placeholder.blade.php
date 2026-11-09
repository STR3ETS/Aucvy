@php
    $sections ??= [];
    $toc = collect($sections)->mapWithKeys(fn (array $section, int $index): array => ['deel-'.($index + 1) => ($index + 1).'. '.$section[0]])->all();
@endphp

{{--
    Voorwaarden en privacy zolang de definitieve tekst er niet is: de regels
    die het platform nu al toepast, uit dezelfde bron als de code, met erbij
    dat de volledige tekst nog volgt. Niet geïndexeerd.
--}}
<x-legal.page :title="$title" :first="$first" :accent="$accent" :lead="$intro" :summary="$summary ?? []" :toc="$toc" :noindex="true">
    <p class="d-soft is-warn" style="padding:14px 18px;font-weight:500;color:#5c3a0c"><strong style="color:#8f5209">Deze tekst wordt op dit moment opgesteld.</strong> {{ $lead }}</p>

    @foreach ($sections as $index => [$heading, $text])
        <h2 id="deel-{{ $index + 1 }}">{{ $index + 1 }}. {{ $heading }}</h2>
        <p>{{ $text }}</p>
    @endforeach

    <p>Vragen in de tussentijd? Neem <a href="{{ route('contact') }}">contact</a> met ons op. Zie ook de <a href="{{ route('legal.cookies') }}">cookieverklaring</a>.</p>
</x-legal.page>
