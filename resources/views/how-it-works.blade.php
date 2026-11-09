@use('App\Support\Money\Money')
@use('App\Support\PlatformFacts')

@php
    // De termijnen komen uit dezelfde instellingen als de code die ze afdwingt.
    $betalen = (int) config('aucvy.orders.payment_deadline_hours');
    $melden = (int) config('aucvy.orders.dispute_window_hours');
    $reageren = (int) config('aucvy.orders.seller_response_hours');
    $adminFee = (int) PlatformFacts::all()['admin_fee_cents'];

    // Deel 1: het bedrijf dat verkoopt. [clip, label, titel, tekst, jij doet, Aucvy doet]
    $selling = [
        ['verkoop-1', 'Stap 1 · Upload', 'Het begint met een foto', 'Iemand loopt op weg naar de koffie langs de heftruck, pakt zijn telefoon en maakt drie foto\'s. Geen formulier, geen specificatielijst opzoeken. Documenten kunnen later.', 'Een paar foto\'s maken vanaf je telefoon.', 'Bewaart alles als concept.'],
        ['verkoop-2', 'Stap 2 · Ivy herkent', 'Ivy ziet meteen wat het is', 'Elektrische heftruck, 2,5 ton, 3.800 draaiuren, gebruikt en in goede staat. Ivy is voor 82% zeker en zegt er eerlijk bij wat ze niet zeker weet: het bouwjaar. Je hoeft dus niet alles na te lopen, alleen dat ene punt.', 'Nakijken wat Ivy aanwijst.', 'Herkent type, capaciteit en conditie, met een zekerheidsscore.'],
        ['verkoop-3', 'Stap 3 · Advertentie', 'Nova schrijft het op, ook de minder mooie kant', 'Titel, omschrijving en specificaties staan klaar. Nova noemt de accu en de lader, en vermeldt ook de slijtage op de vorken. Eerlijk verkoopt beter en voorkomt gedoe achteraf. Zonder jouw akkoord gaat er niets live.', 'Lezen, aanpassen en goedkeuren.', 'Schrijft de advertentie en de specificaties.'],
        ['verkoop-4', 'Stap 4 · Waarde-indicatie', 'Atlas onderbouwt wat hij waard is', 'Niet één mooi getal, maar een range: € 9.500 tot € 12.000, op basis van 14 vergelijkbare verkopen, met zekerheid midden. Een range, geen belofte. De markt bepaalt straks de prijs, maar jij weet nu waar je staat.', 'De bron bekijken en je prijs bepalen.', 'Zoekt vergelijkbare verkopen en toont de bron.'],
        [null, 'Stap 5 · Verkoopvorm', 'Jij kiest hoe je verkoopt', 'Veiling als de markt de prijs mag bepalen. Afslag als het snel moet: de prijs daalt in stappen en wie afmijnt, koopt. Koop direct bij een vaste prijs. Dit bedrijf wil de hal leeg en kiest afslag.', 'De vorm kiezen en je bodemprijs instellen.', 'Laat zien wat elke vorm betekent; de keuze blijft bij jou.'],
        ['verkoop-6', 'Stap 6 · Verkocht', 'Afgemijnd, en dan pas betaald krijgen', 'De prijs start op € 10.500 en daalt. Bij € 9.750 slaat iemand toe. De koper betaalt aan het platform en het transport wordt ingepland. Het geld komt vrij na de houdtermijn, als de koper heeft kunnen controleren of alles klopt.', 'De overdracht afstemmen met de koper.', 'Regelt de betaling en de uitbetaling na de houdtermijn.'],
    ];

    // Deel 2: het bedrijf dat koopt.
    $buying = [
        ['koop-1', 'Stap 1 · Zoeken', 'Gewoon zeggen wat je zoekt', '"Elektrische heftruck van 2 à 3 ton, in de buurt van Nijmegen." Ivy zet die zin om in filters die je zelf kunt corrigeren. Een paar tellen later staat hij op het scherm.', 'Zoeken in je eigen woorden.', 'Maakt er filters van en toont passende kavels.'],
        ['koop-2', 'Stap 2 · Bekijken', 'Eerst weten, dan bieden', 'Foto\'s, specificaties en de waarde-indicatie staan bij elkaar. En de volledige kostenopbouw: geen opgeld, wel de btw-regeling'.($adminFee > 0 ? ', '.Money::format($adminFee).' administratiekosten per bestelling' : '').' en het transport als je dat wilt. Het bedrag dat je daar ziet, is het bedrag dat je straks betaalt. Twijfel je? Waar het kan, plan je een kijkmoment.', 'Vergelijken en eventueel komen kijken.', 'Zet alle kosten klaar vóórdat je biedt.'],
        ['koop-3', 'Stap 3 · Afmijnen', 'De prijs daalt, jij beslist', '€ 10.500. € 10.250. € 10.000. Wachten kan een mooiere prijs opleveren, maar iemand anders kan je voor zijn. Bij € 9.750 sleep je de knop opzij. Slepen in plaats van klikken, zodat je nooit per ongeluk koopt. Bij een veiling bied je tot je maximum en biedt het platform automatisch voor je mee.', 'Bewust bevestigen; een bod is bindend.', 'Legt elk bod vast met een volgnummer.'],
        ['koop-4', 'Stap 4 · Betalen en ophalen', 'Pas als het klopt, krijgt de verkoper zijn geld', 'Je betaalt binnen '.$betalen.' uur, aan het platform. Daarna regel je transport of haal je zelf op. Na levering heb je '.$melden.' uur om te controleren en iets te melden. Geen melding? Dan komt de uitbetaling vrij. En de heftruck rijdt weer.', 'Betalen, ophalen en controleren.', 'Houdt de uitbetaling vast tijdens de meldtermijn.'],
    ];
@endphp

{{--
    Hoe het werkt (ontwerp van november): het verhaal van één heftruck, eerst
    van de kant van het bedrijf dat verkoopt en dan van het bedrijf dat koopt.
    Elke stap heeft een korte clip, wat jij doet en wat Aucvy doet. De
    termijnen komen uit de instellingen; de heftruck en de bedragen zijn een
    voorbeeld.
--}}
<x-layouts.public title="Hoe het werkt" eyebrow="Hoe het werkt" heading="Het verhaal van één heftruck"
    intro="Hij staat stil in een hal in Nijmegen. Een ander bedrijf kan hem net goed gebruiken. Zo komen ze op Aucvy bij elkaar.">

    <div class="d-story">
        <nav class="d-chips" aria-label="Ga naar">
            <a class="d-chip" href="#reis">Ik wil verkopen</a>
            <a class="d-chip" href="#kopen">Ik wil kopen</a>
            <a class="d-chip" href="#zekerheid">Als het misgaat</a>
            <a class="d-chip" href="#kosten">Wat het kost</a>
        </nav>
        <p class="d-hint" style="margin-top:12px">Een voorbeeld: de heftruck, de plaats en de bedragen zijn ter illustratie.</p>

        @foreach ([['reis', 'Deel 1 · Het bedrijf dat verkoopt', 'Een heftruck die niets meer doet', 'Sinds de nieuwe machine er is, staat hij achter in de hal. Hij werkt nog prima en is geld waard, maar verkopen blijft liggen. Want wie gaat dat allemaal uitzoeken?', $selling], ['kopen', 'Deel 2 · Het bedrijf dat koopt', 'Ondertussen zoekt iemand precies deze heftruck', 'Een ander bedrijf heeft er eentje nodig, maar geen nieuwprijs in het budget en geen zin om tien sites af te lopen. Dit is hetzelfde verhaal, van de andere kant.', $buying]] as [$anchor, $part, $title, $lead, $steps])
            <section class="d-story-part" id="{{ $anchor }}" aria-labelledby="{{ $anchor }}-title">
                <span class="d-story-label">{{ $part }}</span>
                <h2 id="{{ $anchor }}-title">{{ $title }}</h2>
                <p class="d-story-lead">{{ $lead }}</p>

                @foreach ($steps as [$clip, $label, $stepTitle, $text, $you, $we])
                    <article class="d-story-step"@if ($label === 'Stap 5 · Verkoopvorm') id="vormen"@endif>
                        @if ($clip)
                            <video controls loop muted playsinline preload="none" poster="{{ asset('videos/verhaal/'.$clip.'.jpg') }}" aria-label="{{ $stepTitle }}">
                                <source src="{{ asset('videos/verhaal/'.$clip.'.mp4') }}" type="video/mp4">
                            </video>
                        @endif
                        <span class="d-story-label">{{ $label }}</span>
                        <h3>{{ $stepTitle }}</h3>
                        <p>{{ $text }}</p>
                        <div class="d-story-pair">
                            <div><strong>Jij doet</strong>{{ $you }}</div>
                            <div><strong>Aucvy doet</strong>{{ $we }}</div>
                        </div>
                    </article>
                @endforeach

                @if ($anchor === 'reis')
                    <div class="d-callout-info">
                        <span class="mark" aria-hidden="true">i</span>
                        <div>
                            <strong>Verkopen op Aucvy is alleen voor bedrijven</strong>
                            Een concept maken kan zonder account. Om te publiceren heb je een geverifieerd zakelijk account nodig.
                            <div class="d-chips" style="margin-top:12px">
                                <a class="d-btn d-btn-sq" href="{{ route('sell') }}">Kavel plaatsen</a>
                                <a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('business') }}">Zakelijk verkopen</a>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="d-chips">
                        <a class="d-btn d-btn-sq" href="{{ route('lots.index') }}">Bekijk de veilingen</a>
                        <a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('categories.index') }}">Alle categorieën</a>
                    </div>
                @endif
            </section>
        @endforeach

        <section class="d-story-part" id="zekerheid" aria-labelledby="zekerheid-title">
            <span class="d-story-label">Zekerheid</span>
            <h2 id="zekerheid-title">En als het toch misgaat?</h2>
            <p class="d-story-lead">Soms klopt een kavel niet met de beschrijving. Daarom staan de termijnen vast, en kijkt er aan het eind een mens naar.</p>
            <ol class="d-story-steps">
                <li><b>1</b><div><strong>Je meldt binnen {{ $melden }} uur</strong><span>Na levering. Vanaf dat moment wordt de uitbetaling aangehouden.</span></div></li>
                <li><b>2</b><div><strong>Verkoper reageert binnen {{ $reageren }} uur</strong><span>Met eigen bewijs.</span></div></li>
                <li><b>3</b><div><strong>Een medewerker beoordeelt</strong><span>Geen algoritme.</span></div></li>
                <li><b>4</b><div><strong>Uitkomst met motivering</strong><span>Alsnog leveren, een prijscorrectie of terugdraaien.</span></div></li>
            </ol>
            {{-- Eerlijk over wat er nog niet is - geen escrow-claim (hfst. 9/18). --}}
            <p class="d-soft is-warn" style="margin:0;padding:14px 18px;font-weight:500;color:#5c3a0c"><strong style="color:#8f5209">Waar we nog aan werken.</strong> Derdengelden-afwikkeling, technische keuring en verzekerd transport. Dat staat hier pas als het er echt is, niet eerder.</p>
            <div><a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('assurance') }}">Lees hoe zekerheid werkt</a></div>
        </section>

        <section class="d-story-part" id="kosten" aria-labelledby="kosten-title">
            <span class="d-story-label">Wat het kost</span>
            <h2 id="kosten-title">Gratis plaatsen, betalen bij resultaat</h2>
            <p class="d-story-lead">Kopers betalen geen opgeld. Verkopende bedrijven betalen alleen een succesfee over een geslaagde verkoop, en het tarief staat vooraf per kavel in de kostenopbouw. Niet verkocht betekent geen kosten.</p>
            <div><a class="d-btn d-btn-sq" href="{{ route('pricing') }}">Bekijk de prijzen</a></div>
        </section>
    </div>

    <x-marketing.cta text="Of je nu iets zoekt of iets kwijt wilt: begin met één foto of één zin."
        primary="Bekijk de veilingen" :primary-url="route('lots.index')"
        secondary="Kavel plaatsen" :secondary-url="route('sell')" />
</x-layouts.public>
