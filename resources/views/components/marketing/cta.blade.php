@props([
    'text' => 'Begin met één foto. Een concept maken kan zonder account. Publiceren doe je met een geverifieerd zakelijk account.',
    'primary' => 'Start met één foto',
    'primaryUrl' => null,
    'secondary' => 'Hoe het werkt',
    'secondaryUrl' => null,
])

{{--
    De afsluitende band van een sitepagina (ontwerp van november): over de
    volle breedte, navy, met de hamer rechts. De kop komt uit de slot; zonder
    slot staat er de vaste vraag.
--}}
<section class="d-cta d-bleed" aria-label="Aan de slag">
    <div class="d-cta-photo" aria-hidden="true" style="background-image:url('{{ asset('images/v4/footer-gavel.jpg') }}')"></div>
    <div class="d-cta-text">
        <h2>@if ($slot->isEmpty())Wat staat er <span class="g">bij jou stil?</span>@else{{ $slot }}@endif</h2>
        <p>{{ $text }}</p>
        <div class="d-cta-actions">
            <a class="btn btn-primary" href="{{ $primaryUrl ?? route('sell') }}">{{ $primary }} <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            <a class="btn btn-outline-white" href="{{ $secondaryUrl ?? route('how-it-works') }}">{{ $secondary }}</a>
        </div>
    </div>
</section>
