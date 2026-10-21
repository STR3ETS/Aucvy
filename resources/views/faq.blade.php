@use('App\Enums\CompanyPlan')
@use('App\Support\Money\Money')

@php
    // Dezelfde bronnen als de code die het afdwingt (13.6): verandert een
    // termijn of drempel, dan verandert deze pagina automatisch mee.
    $borg = Money::format((int) config('aucvy.deposit.threshold_cents'));
    $melden = (int) config('aucvy.orders.dispute_window_hours');
    $betalen = (int) config('aucvy.orders.payment_deadline_hours');
    $slotVenster = (int) config('aucvy.auction.anti_snipe_window_seconds');
    $verlenging = (int) config('aucvy.auction.anti_snipe_extend_seconds');
    $startFee = rtrim(rtrim(number_format(CompanyPlan::Start->defaultFeePercentage(), 1, ',', '.'), '0'), ',');
@endphp

{{--
    De FAQ (briefing hfst. 2b: "statisch, met een accordeon"). Native
    details/summary: toegankelijk zonder JavaScript.
--}}
<x-layouts.public title="Veelgestelde vragen" eyebrow="Hulp" align="center">
    <x-slot:lead>
        Staat je vraag er niet bij? De
        <a href="{{ route('assurance') }}">zekerheidspagina</a>
        legt de geschilroute met termijnen uit, en de
        <a href="{{ route('selling.guide') }}">verkooproute</a>
        het hele verkoopproces.
    </x-slot:lead>
    <div class="mx-auto max-w-3xl">

        @foreach ([
            'Bieden' => [
                ['Hoe werkt bieden?', 'Je bevestigt een bod door de knop naar rechts te slepen - zo plaats je nooit per ongeluk een bod. Het minimum is telkens de huidige stand plus een vaste biedstap, en die staat altijd bij het invoerveld.'],
                ['Wat is automatisch meestappen?', 'Je stelt een maximum in en wij bieden voor je terug zodra iemand over je heen gaat, met de kleinste geldige stap en nooit boven jouw limiet. Niemand ziet jouw maximum, ook de verkoper niet.'],
                ['Waarom werd de veiling verlengd?', 'Een bod in de laatste '.$slotVenster.' seconden verlengt de veiling met '.$verlenging.' seconden, zodat anderen kunnen reageren. Zo wint niemand door op de valreep te snipen.'],
                ['Wat is de borg?', 'Wil je meer dan '.$borg.' bieden, dan vragen we eerst een borgreservering. Win je niet, dan komt die automatisch vrij; win je wel, dan komt hij vrij zodra je betaling binnen is.'],
                ['Kan ik een bod intrekken?', 'Nee, een bod is bindend - daarom bevestig je het bewust met de sleepbeweging. Een ingesteld maximum kun je wel intrekken; je al geplaatste bod blijft dan staan.'],
            ],
            'Kopen en betalen' => [
                ['Wat betaal ik bovenop mijn bod?', 'Geen opgeld: de kostenopbouw op de kavelpagina (bod, eventueel transport, btw-regeling) is het bedrag dat je betaalt. Hetzelfde getal staat straks op je order.'],
                ['Hoe snel moet ik betalen?', 'Binnen '.$betalen.' uur na de gunning. Betaal je niet op tijd, dan vervalt de koop en kan de kavel opnieuw worden aangeboden.'],
                ['Hoe zit het met btw?', 'Dat verschilt per kavel: btw-belast of margeregeling. Het staat altijd in de kostenopbouw vóór je biedt, inclusief wat dat voor jou betekent.'],
                ['Kan ik eerst komen kijken?', 'Bij kavels met "Bezichtiging mogelijk" kun je een kijkmoment afspreken. Bij een lage zekerheid op de waarde-indicatie raden we dat ook echt aan.'],
            ],
            'Verkopen' => [
                ['Wat kost verkopen?', 'Plaatsen is gratis. Alleen bij een geslaagde verkoop wordt een succesfee ingehouden, vanaf '.$startFee.'% afhankelijk van je plan. Het exacte bedrag zie je vóór publicatie in de kostenopbouw.'],
                ['Moet ik alles zelf invullen?', 'Nee. Ivy herkent je object uit de foto\'s, Nova schrijft de advertentie en Atlas geeft een waarde-indicatie. Jij controleert, past aan en keurt goed - zonder jouw akkoord gaat er niets live.'],
                ['Welke verkoopvorm kies ik?', 'Veiling als de markt de prijs mag bepalen, afslag (dalende prijs) als het snel moet, koop direct bij een vaste prijs. Jij kiest; Atlas geeft de waarde-indicatie mee.'],
                ['Wanneer krijg ik mijn geld?', 'Na levering geldt een korte houdtermijn waarin de koper kan controleren of alles klopt. Daarna wordt de uitbetaling vrijgegeven, minus de succesfee.'],
            ],
            'Zekerheid en geschillen' => [
                ['Wat als de kavel niet klopt met de beschrijving?', 'Meld het binnen '.$melden.' uur na levering. Vanaf dat moment wordt de uitbetaling aan de verkoper aangehouden en bemiddelt Aucvy. De volledige route met termijnen staat op de zekerheidspagina.'],
                ['Zijn verkopers gecontroleerd?', 'Ja: identiteit en - zakelijk - de KVK-inschrijving, vóór een kavel live kan. Reviews van eerdere transacties staan op het verkopersprofiel, ook de kritische.'],
                ['Is er een koopgarantie?', 'Nee, en dat zeggen we liever eerlijk dan dat we iets beloven dat er nog niet is. Wat wél geregeld is: kosten vooraf, vastgelegde biedhistorie, een houdtermijn op de uitbetaling en bemiddeling bij geschillen. Waar we nog aan werken staat op de zekerheidspagina.'],
            ],
            'Ivy en AI' => [
                ['Wat doet Ivy precies?', 'Ivy coördineert zes specialisten: herkenning, advertentietekst, waarde-indicatie, prijsadvies, transport en documentcontrole. Alles wat het team maakt is een voorstel dat jij goedkeurt of aanpast.'],
                ['Hoe betrouwbaar is de waarde-indicatie?', 'Het is een range op basis van vergelijkbare verkopen, altijd met een zekerheidslabel. Bij lage zekerheid tonen we bewust geen vergelijking met het huidige bod en raden we een bezichtiging aan.'],
                ['Wat gebeurt er met mijn foto\'s en documenten?', 'Die worden alleen gebruikt om jouw kavel te verwerken. Documenten staan op afgeschermde opslag en zijn nooit publiek te downloaden.'],
            ],
        ] as $sectie => $vragen)
            <section class="mt-8" aria-label="{{ $sectie }}">
                <h2 class="text-lg font-extrabold">{{ $sectie }}</h2>
                <div class="mt-3 space-y-2">
                    @foreach ($vragen as [$vraag, $antwoord])
                        <details class="group rounded-aj-sm border border-aj-border bg-aj-surface">
                            <summary class="flex cursor-pointer items-center justify-between gap-3 p-4 text-sm font-bold marker:content-none">
                                {{ $vraag }}
                                <svg class="size-4 shrink-0 text-aj-muted transition-transform group-open:rotate-180"
                                     viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m5 8 5 5 5-5"/>
                                </svg>
                            </summary>
                            <p class="px-4 pb-4 text-sm text-aj-muted">{{ $antwoord }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</x-layouts.public>
