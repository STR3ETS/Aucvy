@use('App\Enums\HomeVariant')

@props([
    'variant',
    // In het iframe van de vergelijkingspagina heeft de ouder de knoppen.
    'demo' => false,
])

{{--
    De schakelaar van de A/B-test, op beide ontwerpen van de home. Een klik
    toont het andere ontwerp (?variant=…); tijdens een lopende test zet dat
    ook de cookie, zodat je het ontwerp houdt zoals een toegewezen bezoeker.
--}}
@unless ($demo)
    <div class="ab-switch" role="group" aria-label="A/B-test: ontwerp wisselen">
        <span class="tag">A/B-test</span>
        <a href="{{ route('home', ['variant' => HomeVariant::Current->value]) }}" @class(['on' => $variant === HomeVariant::Current])
           @if ($variant === HomeVariant::Current) aria-current="true" @endif>A · Oud</a>
        <a href="{{ route('home', ['variant' => HomeVariant::Redesign->value]) }}" @class(['on' => $variant === HomeVariant::Redesign])
           @if ($variant === HomeVariant::Redesign) aria-current="true" @endif>B · Nieuw</a>
        <a href="{{ route('home', ['variant' => HomeVariant::Hybrid->value]) }}" @class(['on' => $variant === HomeVariant::Hybrid])
           @if ($variant === HomeVariant::Hybrid) aria-current="true" @endif>C · Hybride</a>
        <a href="{{ route('experiments.home') }}" class="cmp">Naast elkaar</a>
    </div>
@endunless
