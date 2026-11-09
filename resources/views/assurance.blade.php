@use('App\Support\Money\Money')

@php
    // De belofte met deadlines (13.6): deze getallen komen uit dezelfde
    // config die orders:advance afdwingt. Verandert de termijn, dan verandert
    // deze pagina automatisch mee - tekst en code kunnen niet uiteenlopen.
    $melden = (int) config('aucvy.orders.dispute_window_hours');
    $reageren = (int) config('aucvy.orders.seller_response_hours');
    $betalen = (int) config('aucvy.orders.payment_deadline_hours');
    $borgCents = (int) config('aucvy.deposit.threshold_cents');
    $borg = '€ '.number_format($borgCents / 100, 0, ',', '.');
@endphp

<x-layouts.public title="Zekerheid" eyebrow="Zekerheid"
    intro="Wat er bij elke kavel vaststaat, met de termijnen erbij. En wat er nog niet is.">
    <x-slot:headline>Bij elke kavel <span class="g">geregeld.</span></x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">Wat er bij elke kavel vaststaat, met de termijnen erbij. En wat er nog niet is.</p>
        <div class="page-actions" style="margin-top:18px">
            <a class="btn btn-primary" href="{{ route('lots.index') }}">Naar de veilingen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            <a class="btn btn-outline-white" href="{{ route('faq') }}">Alle vragen</a>
        </div>
        <div class="d-hero-trust">
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Verkopers</b>&nbsp;gecontroleerd</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Kosten</b>&nbsp;vóór het bieden</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Een mens</b>&nbsp;beoordeelt</span>
        </div>
    </x-slot:lead>
    <x-slot:actions>
        <div class="d-hero-table" aria-label="De termijnen">
            <span class="title">De termijnen</span>
            <div><span>Betalen na de hamerslag</span><b>{{ $betalen }} u</b></div>
            <div><span>Melden na levering</span><b>{{ $melden }} u</b></div>
            <div><span>Reactie verkoper</span><b>{{ $reageren }} u</b></div>
            <div><span>Borg vanaf</span><b>{{ $borg }}</b></div>
        </div>
    </x-slot:actions>

    <section class="d-sec" aria-labelledby="vast">
        <span class="d-pill">Wat vaststaat</span>
        <h2 id="vast" style="max-width:none">Vier dingen die altijd kloppen.</h2>
        <div class="d-cards3 is-four">
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Verkoper gecontroleerd</h3><p>Identiteit en de KVK-inschrijving, vóór een kavel live kan.</p></div>
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Kosten vóór het bieden</h3><p>De volledige kostenopbouw staat op de kavelpagina: hetzelfde bedrag dat je straks betaalt.</p></div>
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Biedhistorie vastgelegd</h3><p>Elk bod krijgt een volgnummer en blijft controleerbaar bewaard.</p></div>
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Bezichtiging mogelijk</h3><p>Waar de verkoper dat aanbiedt, plan je een kijkdag vóór je biedt.</p></div>
        </div>
    </section>

    <section class="d-stepsband d-bleed" aria-labelledby="misgaat">
        <div class="d-stepsband-text">
            <span class="d-pill is-dark">Als het misgaat</span>
            <h2 id="misgaat">Vier stappen, <span class="g">met een mens aan het eind.</span></h2>
            <p>Elke stap heeft een termijn. Zo weet iedereen waar hij aan toe is.</p>
        </div>
        <ol class="d-numlist">
            <li><b>01</b><div><strong>Je meldt binnen {{ $melden }} uur</strong><span>Na levering. Vanaf dat moment wordt de uitbetaling aan de verkoper aangehouden.</span></div></li>
            <li><b>02</b><div><strong>Verkoper reageert binnen {{ $reageren }} uur</strong><span>Met eigen bewijs.</span></div></li>
            <li><b>03</b><div><strong>Een medewerker beoordeelt</strong><span>Geen algoritme.</span></div></li>
            <li><b>04</b><div><strong>Uitkomst met motivering</strong><span>Alsnog leveren, een prijscorrectie of terugdraaien.</span></div></li>
        </ol>
    </section>

    <section class="d-sec d-band-grey d-bleed" aria-labelledby="vragen">
        {{-- Eerlijk over wat er nog niet is - geen escrow-claim (hfst. 9/18). --}}
        <p class="d-soft is-warn" style="margin:0 0 28px;padding:14px 18px;font-weight:500;color:#5c3a0c"><strong style="color:#8f5209">Waar we nog aan werken.</strong> Derdengelden-afwikkeling, technische keuring en verzekerd transport. Dat staat hier pas als het er echt is, niet eerder.</p>

        <span class="d-pill">Vragen</span>
        <h2 id="vragen">Veelgestelde vragen</h2>
        <div class="d-faq" style="margin-top:28px;gap:0">
            <details class="d-faq-item" style="margin-top:0">
                <summary><span>Wanneer moet ik betalen?</span><i aria-hidden="true"><x-v4.icon name="plus" :size="14" stroke="2.6" /></i></summary>
                <p>Binnen {{ $betalen }} uur na de hamerslag. Betaal je niet, dan vervalt de koop automatisch.</p>
            </details>
            <details class="d-faq-item">
                <summary><span>Wanneer krijgt de verkoper zijn geld?</span><i aria-hidden="true"><x-v4.icon name="plus" :size="14" stroke="2.6" /></i></summary>
                <p>Na levering loopt eerst de meldtermijn van {{ $melden }} uur. Geen melding? Dan komt de uitbetaling vrij. Loopt er een geschil, dan staat de uitbetaling stil tot het is afgehandeld.</p>
            </details>
            <details class="d-faq-item">
                <summary><span>Waarom moet ik me verifiëren voor ik bied?</span><i aria-hidden="true"><x-v4.icon name="plus" :size="14" stroke="2.6" /></i></summary>
                <p>Geen nepbiedingen en geen wanbetaling: iedereen die biedt is echt en aanspreekbaar. Rondkijken en volgen kan zonder verificatie.</p>
            </details>
            <details class="d-faq-item">
                <summary><span>Wat gebeurt er met een borg?</span><i aria-hidden="true"><x-v4.icon name="plus" :size="14" stroke="2.6" /></i></summary>
                <p>Boven {{ Money::format($borgCents) }} vragen we een reservering vóór je eerste bod. Win je niet, dan komt hij direct vrij; win je wel, dan komt hij vrij zodra je betaling binnen is.</p>
            </details>
        </div>
        <a class="btn btn-primary" style="margin-top:22px" href="{{ route('faq') }}">Alle vragen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
    </section>

    <x-marketing.cta text="Waarde-indicaties, de kosten vooraf en zien wat er hot is. Rondkijken en volgen kan zonder verificatie."
        primary="Ontdek de veilingen" :primary-url="route('lots.index')"
        secondary="Hoe bieden werkt" :secondary-url="route('how-it-works').'#kopen'">
        Slim bieden. <span class="g">Weten waar je aan toe bent.</span>
    </x-marketing.cta>
</x-layouts.public>
