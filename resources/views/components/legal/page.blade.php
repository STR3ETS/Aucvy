@props([
    // De paginatitel voor <title> en zoekmachines.
    'title',
    // De kop in twee delen: het tweede krijgt het verloop.
    'first',
    'accent',
    'lead',
    // "In het kort" rechts in de kop: [label => waarde].
    'summary' => [],
    // "Op deze pagina": [anker => label].
    'toc' => [],
    'noindex' => false,
])

{{--
    Een juridische pagina (ontwerp van november): de kop met rechts "In het
    kort", links de inhoudsopgave en rechts de tekst.
--}}
<x-layouts.public :title="$title" eyebrow="Juridisch" :noindex="$noindex">
    <x-slot:headline>{{ $first }} <span class="g">{{ $accent }}</span></x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">{{ $lead }}</p>
    </x-slot:lead>
    @if ($summary !== [])
        <x-slot:actions>
            <div class="d-hero-table" aria-label="In het kort">
                <span class="title">In het kort</span>
                @foreach ($summary as $label => $value)
                    <div><span>{{ $label }}</span><b>{{ $value }}</b></div>
                @endforeach
            </div>
        </x-slot:actions>
    @endif

    <div class="d-article">
        <aside aria-label="Op deze pagina">
            @if ($toc !== [])
                <span class="d-article-label">Op deze pagina</span>
                <ol>
                    @foreach ($toc as $anchor => $label)
                        <li><a href="#{{ $anchor }}">{{ $label }}</a></li>
                    @endforeach
                </ol>
            @endif
        </aside>
        <div class="d-article-body is-plain">
            {{ $slot }}
        </div>
    </div>
</x-layouts.public>
