@php
    $words = [1 => 'Eén', 'Twee', 'Drie', 'Vier', 'Vijf', 'Zes', 'Zeven', 'Acht', 'Negen', 'Tien', 'Elf', 'Twaalf'];
    $amount = $roots->count();
    $worlds = ($words[$amount] ?? (string) $amount).' '.($amount === 1 ? 'wereld.' : 'werelden.');
@endphp

{{--
    Het categorie-overzicht (ontwerp van november): de hoofdcategorieën als
    fotovlakken, de eerste groot. Elke kaart toont het aantal zichtbare kavels
    en de subcategorieën als links; de foto komt uit het aanbod en anders uit
    het ontwerp. Daaronder wat er nu onder de hamer gaat.
--}}
<x-layouts.public title="Categorieën" eyebrow="Ontdek">
    <x-slot:headline>{{ $worlds }} <span class="g">Eén veilinghuis.</span></x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">{{ $amount }} hoofdcategorieën, elk met eigen subcategorieën en specificaties. Klik door naar het aanbod of zoek meteen in je eigen woorden.</p>
        <div class="page-actions" style="margin-top:18px">
            <a class="btn btn-primary" href="{{ route('lots.index') }}">Alle veilingen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            <a class="btn btn-outline-white" href="{{ route('dashboard.alerts') }}">Alert instellen</a>
        </div>
        <div class="d-hero-trust">
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Geverifieerde</b>&nbsp;verkopers</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Waarde-indicatie</b>&nbsp;van Atlas</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Geen opgeld</b>&nbsp;voor kopers</span>
        </div>
    </x-slot:lead>
    <x-slot:actions>
        <x-marketing.hero-ivy
            question="Waar vind ik een complete keuken?"
            answer="Zeg wat je zoekt en ik wijs de categorie aan, met wat er nu live staat en de waarde-indicatie van Atlas erbij."
            :chips="$roots->take(3)->pluck('name')->all()" />
    </x-slot:actions>

    <section class="d-sec" aria-labelledby="cats-title">
        <span class="d-pill">Categorieën</span>
        <h2 id="cats-title" style="max-width:none">Waar ben je naar op zoek?</h2>
        <div class="d-catgrid">
            @foreach ($roots as $root)
                @php
                    $photo = 'images/design/cat-'.$root->slug.'.jpg';
                    $cover = $covers[$root->id] ?? (file_exists(public_path($photo)) ? asset($photo) : null);
                    $total = (int) ($totals[$root->id] ?? 0);
                @endphp
                <article @class(['is-big' => $loop->first])>
                    @if ($cover)
                        <img src="{{ $cover }}" alt="" loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                    @endif
                    <span class="count">{{ $total }} {{ $total === 1 ? 'kavel' : 'kavels' }}</span>
                    <div class="text">
                        <h3><a href="{{ route('lots.category', $root) }}">{{ $root->name }}</a></h3>
                        @if ($root->children->isNotEmpty())
                            <p>
                                @foreach ($root->children as $child)
                                    <a href="{{ route('lots.category', $child) }}">{{ $child->name }}</a>@if (! $loop->last), @endif
                                @endforeach
                            </p>
                        @endif
                    </div>
                </article>
            @endforeach
            <a class="more" href="{{ route('dashboard.alerts') }}">
                <strong>Niet gevonden wat je zoekt?</strong>
                <span>Stel een alert in. Komt er iets nieuws, dan hoor je het. <x-v4.icon name="arrow-right" :size="13" stroke="2.4" /></span>
            </a>
        </div>
    </section>

    <section class="d-sec d-band-grey d-bleed" aria-labelledby="live-title">
        <span class="d-pill">Nu live</span>
        <h2 id="live-title" style="max-width:none">Dit gaat nu onder de hamer.</h2>
        <div class="lots d-catlots">
            @forelse ($featured as $lot)
                <x-lot.card :lot="$lot" />
            @empty
                <p style="grid-column:1/-1;margin:0;color:#4d5566">Er loopt op dit moment niets. Zodra er een veiling start, staat hij hier.</p>
            @endforelse
        </div>
    </section>

    <x-marketing.cta text="Waarde-indicaties, de kosten vooraf en zien wat er hot is. Rondkijken en volgen kan zonder verificatie."
        primary="Ontdek de veilingen" :primary-url="route('lots.index')"
        secondary="Hoe bieden werkt" :secondary-url="route('how-it-works').'#kopen'">
        Slim bieden. <span class="g">Weten waar je aan toe bent.</span>
    </x-marketing.cta>
</x-layouts.public>
