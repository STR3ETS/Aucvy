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
    $vrijBieden = Money::format((int) \App\Support\PlatformFacts::all()['bid_free_limit_cents']);
    $startFee = rtrim(rtrim(number_format(CompanyPlan::Start->defaultFeePercentage(), 1, ',', '.'), '0'), ',');
@endphp

{{--
    De FAQ (briefing hfst. 2b: "statisch, met een accordeon"). Native
    details/summary: toegankelijk zonder JavaScript.
--}}
<x-layouts.public title="Veelgestelde vragen" eyebrow="Veelgestelde vragen">
    <x-slot:headline>Alles wat je wilt <span class="g">weten,</span> op één plek.</x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">Van bieden en verkoopvormen tot verificatie, kosten en zekerheid. Staat je vraag er niet bij? Vraag het Ivy.</p>
        <div class="page-actions" style="margin-top:18px">
            <button type="button" class="btn btn-primary" @click="$dispatch('ivy-open')">Vraag het Ivy <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></button>
            <a class="btn btn-outline-white" href="{{ route('assurance') }}">Bekijk zekerheid</a>
        </div>
        <div class="d-hero-trust">
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Duidelijke</b>&nbsp;antwoorden</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Geen opgeld</b>&nbsp;voor kopers</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Betalen</b>&nbsp;via het platform</span>
        </div>
    </x-slot:lead>
    <x-slot:actions>
        <x-marketing.hero-ivy
            question="Wanneer krijgt de verkoper zijn geld?"
            :answer="'Na levering loopt eerst een meldtermijn van '.$melden.' uur. Geen melding? Dan komt de uitbetaling vrij. Loopt er een geschil, dan staat de uitbetaling stil.'"
            :chips="['Is bieden veilig?', 'Wat kost verkopen?', 'Hoe verifieer ik?']" />
    </x-slot:actions>

    <div class="d-faq">

        @foreach ([
            'Algemeen' => [
                ['Wat is Aucvy?', 'Een online veilingplatform voor zakelijke goederen: machines, inventaris, horeca- en kantoorinrichting. Bedrijven verkopen per veiling, afslag of vaste prijs; iedereen met een account kan kopen.'],
                ['Wie is Ivy, en zijn er meer assistenten?', 'Ivy is de AI-assistent van Aucvy. Ze werkt met vijf specialisten: Atlas voor de markt en de waarde-indicatie, Nova voor de advertentietekst, Finn voor het geld, Flow voor de logistiek en Lex voor de documenten. Het zijn AI-assistenten, geen mensen; bij een geschil beslist altijd een medewerker.'],
                ['Heb ik een account nodig?', 'Rondkijken kan zonder. Om te bieden heb je een account nodig met een bevestigd e-mailadres en telefoonnummer; tot '.$vrijBieden.' is dat genoeg, daarboven vragen we je identiteit. Verkopen kan alleen met een geverifieerd zakelijk account.'],
            ],
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
                ['Kan ik documenten inzien en daar vragen over stellen?', 'Zet de verkoper een document open, dan bekijk je het via het oogje op de kavelpagina; downloaden kan niet. Een vraag stel je bij de kavel zelf. De verkoper kan het antwoord voor alle bieders tonen, zodat iedereen hetzelfde weet.'],
                ['Kan ik eerst komen kijken?', 'Bij kavels met "Bezichtiging mogelijk" kun je een kijkmoment afspreken. Bij een lage zekerheid op de waarde-indicatie raden we dat ook echt aan.'],
            ],
            'Verkopen' => [
                ['Wie mag er verkopen?', 'Alleen bedrijven met een geverifieerd zakelijk account: KVK-inschrijving en identiteit van de bevoegde persoon. Particulieren kunnen kopen, niet verkopen.'],
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
            <section aria-label="{{ $sectie }}">
                <h2 class="d-faq-label">{{ $sectie }}</h2>
                @foreach ($vragen as [$vraag, $antwoord])
                    <details class="d-faq-item">
                        <summary>
                            <span>{{ $vraag }}</span>
                            <i aria-hidden="true"><x-v4.icon name="plus" :size="14" stroke="2.6" /></i>
                        </summary>
                        <p>{{ $antwoord }}</p>
                    </details>
                @endforeach
            </section>
        @endforeach
    </div>
</x-layouts.public>
