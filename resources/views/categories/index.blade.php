@php
    $intro = $roots->count().' hoofdcategorieën, elk met eigen subcategorieën en specificaties. Klik door naar het aanbod of zoek meteen in alles.';
@endphp

<x-layouts.public title="Categorieën" eyebrow="Ontdek" :intro="$intro">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('lots.index') }}">Alle veilingen</a>
    </x-slot:actions>

    {{--
        Per hoofdcategorie een kaart: de nieuwste foto uit het aanbod, het
        aantal zichtbare kavels en de subcategorieën als chips. Zonder foto
        (een lege categorie, of productie zonder beeld) staat de eerste
        letter op het navy vlak.
    --}}
    <div class="cat-grid">
        @foreach ($roots as $root)
            <article class="cat-card">
                <a class="cat-cover" href="{{ route('lots.category', $root) }}" aria-label="{{ $root->name }}">
                    @if (! empty($covers[$root->id]))
                        <img src="{{ $covers[$root->id] }}" alt="" loading="lazy">
                    @else
                        <span class="cat-cover-empty" aria-hidden="true">{{ mb_substr($root->name, 0, 1) }}</span>
                    @endif
                    <span class="cat-count">{{ $totals[$root->id] }} {{ $totals[$root->id] === 1 ? 'kavel' : 'kavels' }}</span>
                </a>
                <div class="cat-body">
                    <h2><a href="{{ route('lots.category', $root) }}">{{ $root->name }}</a></h2>
                    @if ($root->children->isNotEmpty())
                        <ul class="cat-subs">
                            @foreach ($root->children as $child)
                                <li>
                                    <a href="{{ route('lots.category', $child) }}">
                                        {{ $child->name }}
                                        @if ($counts->get($child->id))
                                            <span>{{ $counts->get($child->id) }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</x-layouts.public>
