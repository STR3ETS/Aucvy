@php
    // Eerlijk vergeleken: wat elke route goed doet en wat niet.
    $routes = [
        ['Opkoper', 'Morgen leeg', [[true, 'Snel, alles in één keer'], [true, 'Weinig werk'], [false, 'Eén bod, van één partij'], [false, 'Je weet niet wat het waard is']], false],
        ['Zelf adverteren', 'Eén of twee items', [[true, 'Jij bepaalt de vraagprijs'], [false, 'Per item foto, tekst en contact'], [false, 'Betaling en kopers zelf regelen']], false],
        ['Veilen via Aucvy', 'Meerdere items of de hele zaak', [[true, 'De markt, of jouw vaste prijs'], [true, 'Waarde-indicatie met bron'], [true, 'Bieders doorlopen een verificatie'], [false, 'Reken op een looptijd']], true],
    ];
@endphp

{{--
    De drie routes naast elkaar (ontwerp van november), op de home en op de
    zakelijke pagina. Met plussen en minnen, ook bij Aucvy zelf.
--}}
<div class="d-compare">
    @foreach ($routes as [$name, $when, $points, $ours])
        <article @class(['is-dark' => $ours])>
            @if ($ours)<span class="badge">Aucvy</span>@endif
            <h3>{{ $name }}</h3>
            <span class="when">{{ $when }}</span>
            <ul>
                @foreach ($points as [$plus, $point])
                    <li @class(['is-min' => ! $plus])><i aria-hidden="true">{{ $plus ? '+' : '−' }}</i><span class="sr-only">{{ $plus ? 'Voordeel:' : 'Nadeel:' }}</span>{{ $point }}</li>
                @endforeach
            </ul>
        </article>
    @endforeach
</div>
<p class="d-sec-lead" style="font-size:15px">Heb je morgen de sleutel nodig? Dan is een opkoper vaak het snelst. Vraag wel eerst een waarde-indicatie, dan weet je wat zijn bod betekent.</p>
