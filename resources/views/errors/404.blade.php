@php
    // Een foutpagina mag zelf niet omvallen: lukt het ophalen niet, dan blijft het blok weg.
    $featured = rescue(fn () => app(\App\Lots\FeaturedLots::class)->get(), collect(), report: false);
@endphp

{{--
    De 404 (ontwerp van november) staat in de gewone siteschil, met Ivy en
    een paar lopende kavels als weg terug. De andere foutpagina's houden de
    kale kaart: daar kan de site zelf het probleem zijn.
--}}
<x-layouts.public title="Pagina niet gevonden" eyebrow="Fout 404" :noindex="true">
    <x-slot:headline>Deze pagina is <span class="g">onder de hamer geweest.</span></x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">Deze pagina bestaat niet of is verplaatst. Misschien is de kavel al verkocht.</p>
        <div class="page-actions" style="margin-top:18px">
            <a class="btn btn-primary" href="{{ route('lots.index') }}">Naar de veilingen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            <a class="btn btn-outline-white" href="{{ route('home') }}">Naar de homepage</a>
        </div>
    </x-slot:lead>
    <x-slot:actions>
        <x-marketing.hero-ivy
            question="Waar is deze kavel gebleven?"
            answer="Die vind ik hier niet meer. Vertel me wat je zoekt, dan kijk ik wat er nu live staat."
            ask="Ik zoek een kavel die ik niet meer kan vinden."
            :chips="['Veilingen', 'Categorieën', 'Contact']" />
    </x-slot:actions>

    <section class="d-sec" aria-labelledby="more-title">
        <h2 id="more-title" style="margin-top:0;max-width:none">Misschien iets voor jou</h2>
        @if ($featured->isNotEmpty())
            <div class="lots d-catlots">
                @foreach ($featured as $lot)
                    <x-lot.card :lot="$lot" />
                @endforeach
            </div>
        @else
            <p class="d-sec-lead">Bekijk <a href="{{ route('lots.index') }}" style="color:#1e56e0;font-weight:700;text-decoration:underline">alle veilingen</a> of blader door de <a href="{{ route('categories.index') }}" style="color:#1e56e0;font-weight:700;text-decoration:underline">categorieën</a>.</p>
        @endif
    </section>
</x-layouts.public>
