@php
    $team = [
        ['ivy', 'Ivy', 'Coördineert, herkent en adviseert.'],
        ['atlas', 'Atlas', 'Waarde-indicaties en marktdata.'],
        ['nova', 'Nova', 'Advertentietekst en promotie.'],
        ['finn', 'Finn', 'Prijsadvies en verkoopvorm.'],
        ['flow', 'Flow', 'Transport en planning.'],
        ['lex', 'Lex', 'Documenten en voorwaarden.'],
    ];
    $situations = [
        ['zakelijk-stroom.jpg', 'Doorlopende stroom', 'Vervangen materieel, retouren of overtollige voorraad. Plaats per kavel of in series, met vaste afspraken over vorm en planning.'],
        ['zakelijk-sluiting.jpg', 'Verhuizing of sluiting', 'Een complete inventaris in één keer. Ivy herkent per object, Nova schrijft, Atlas waardeert. Jij keurt goed.'],
        ['zakelijk-liquidatie.jpg', 'Liquidatie', 'Snel en controleerbaar: afslag of veiling met een duidelijke bodem, de biedhistorie vastgelegd, uitbetaling na de houdtermijn.'],
    ];
    $checks = [
        ['KVK-controle en verificatie', 'Identiteit en de KVK-inschrijving, vóór een kavel live kan.'],
        ['Kostenopbouw vooraf', 'Bod, transport en btw-regeling staan bij elkaar vóórdat een koper biedt.'],
        ['Houdtermijn op uitbetaling', 'Je ontvangt het geld nadat de koper heeft kunnen controleren.'],
        ['Geschilroute met bemiddeling', 'Een melding binnen de termijn, jouw reactie met eigen bewijs, een medewerker die beoordeelt.'],
        ['Biedhistorie vastgelegd', 'Elk bod krijgt een volgnummer en blijft controleerbaar bewaard.'],
        ['Trust Index op je profiel', 'Afgeronde transacties, documentkwaliteit en levering bouwen je score op.'],
    ];
    $registerUrl = route('register', ['soort' => 'zakelijk']);
@endphp

<x-layouts.public title="Zakelijk" eyebrow="Zakelijk verkopen"
    intro="Voor bedrijven met een doorlopende stroom aan inventaris, overtollig materieel of een liquidatie. Ivy coördineert en jij houdt de regie.">
    <x-slot:headline>Een verkoopteam dat <span class="g">nooit slaapt.</span></x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">Voor bedrijven met een doorlopende stroom aan inventaris, overtollig materieel of een liquidatie. Ivy coördineert en jij houdt de regie.</p>
        <div class="page-actions" style="margin-top:18px">
            <a class="btn btn-primary" href="{{ $registerUrl }}">Zakelijk account aanmaken <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            <a class="btn btn-outline-white" href="{{ route('sell') }}">Kavel plaatsen</a>
        </div>
        <div class="d-hero-trust">
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Gratis</b>&nbsp;plaatsen</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Alleen betalen</b>&nbsp;bij verkoop</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Alleen</b>&nbsp;voor bedrijven</span>
        </div>
    </x-slot:lead>
    <x-slot:actions>
        <x-marketing.hero-ivy
            question="We hebben 32 kavels uit een liquidatie. Hoe pakken we dat aan?"
            answer="Upload de foto's per object. Ik maak er kavels van, Atlas geeft per kavel een range en jij keurt goed. Afslag past als je een vaste einddatum hebt."
            :chips="['Wat kost het?', 'Hoe snel live?', 'Wie keurt goed?']" />
    </x-slot:actions>

    {{-- Voor wie. --}}
    <section class="d-sec" id="situaties" aria-labelledby="biz-for">
        <span class="d-pill">Voor wie</span>
        <h2 id="biz-for">Drie situaties, één aanpak.</h2>
        <p class="d-sec-lead">Of het nu om één machine gaat of om een complete inventaris: jij levert foto's en het akkoord, het team doet de rest.</p>
        <div class="d-photocards">
            @foreach ($situations as [$image, $title, $text])
                <article>
                    <img src="{{ asset('images/design/'.$image) }}" alt="" loading="lazy" width="760" height="420">
                    <div><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- In drie stappen. --}}
    <section class="d-stepsband d-bleed" aria-labelledby="biz-steps">
        <div class="d-stepsband-text">
            <span class="d-pill is-dark">In drie stappen</span>
            <h2 id="biz-steps">Zo word je <span class="g">verkoper.</span></h2>
            <p>Een zakelijk account is zo aangemaakt; de controle volgt bij de verificatie.</p>
            <div class="d-cta-actions">
                <a class="btn btn-primary" href="{{ $registerUrl }}">Zakelijk account aanmaken <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
                <a class="btn btn-outline-white" href="{{ route('selling.guide') }}">Bekijk de verkooproute</a>
            </div>
        </div>
        <ol class="d-numlist">
            <li><b>01</b><div><strong>Maak een zakelijk account aan</strong><span>Met je bedrijfsnaam en KVK-nummer. Een concept dat je al maakte, blijft bewaard.</span></div></li>
            <li><b>02</b><div><strong>Rond de verificatie af</strong><span>KVK-inschrijving, identiteit van de bevoegde persoon en je zakelijke rekening voor uitbetalingen.</span></div></li>
            <li><b>03</b><div><strong>Plaats je eerste kavel</strong><span>Ivy neemt het vanaf de foto over. Zonder jouw akkoord gaat er niets live.</span></div></li>
        </ol>
    </section>

    {{-- Het team. --}}
    <section class="d-sec" id="team" style="padding-top:88px" aria-labelledby="biz-team">
        <span class="d-pill">Het team</span>
        <h2 id="biz-team">Zes specialisten, één coördinator.</h2>
        <p class="d-sec-lead">Ivy verdeelt het werk en meldt alleen wat je moet weten. Elke stap is terug te zien in je dashboard.</p>
        <ul class="d-teamcards">
            @foreach ($team as [$slug, $name, $role])
                <li>
                    <img src="{{ asset('images/agents/'.$slug.'.jpg') }}" alt="{{ $name }}, AI-specialist" loading="lazy" width="48" height="48">
                    <div><strong>{{ $name }}</strong><span>{{ $role }}</span></div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Eerlijk vergeleken. --}}
    <section class="d-sec" aria-labelledby="biz-compare">
        <span class="d-pill">Eerlijk vergeleken</span>
        <h2 id="biz-compare" style="max-width:none">Opkoper, zelf adverteren of veilen?</h2>
        <x-marketing.compare />
    </section>

    {{-- Tarieven. --}}
    <section class="d-stepsband d-bleed" id="tarieven" aria-labelledby="biz-plans">
        <div class="d-stepsband-text">
            <span class="d-pill is-dark">Tarieven</span>
            <h2 id="biz-plans">Alleen betalen <span class="g">bij resultaat.</span></h2>
            <p>Plaatsen is gratis. De succesfee staat vooraf per kavel in de kostenopbouw. Een afwijkende afspraak voor grote volumes leggen we vast op je bedrijfsprofiel.</p>
            <div class="d-cta-actions">
                <a class="btn btn-primary" href="{{ route('pricing') }}">Bekijk de prijzen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            </div>
        </div>
        <div class="d-hero-table" style="max-width:none" aria-label="Wat je betaalt">
            <span class="title">Wat je betaalt</span>
            <div><span>Plaatsen</span><b>€ 0</b></div>
            <div><span>Niet verkocht</span><b>€ 0</b></div>
            <div><span>Verkocht</span><b>Succesfee</b></div>
        </div>
    </section>

    {{-- Zekerheid voor bedrijven. --}}
    <section class="d-sec d-band-grey d-bleed" id="zekerheid" aria-labelledby="biz-trust">
        <span class="d-pill">Zekerheid</span>
        <h2 id="biz-trust">Wat er geregeld is.</h2>
        <div class="d-cards3">
            @foreach ($checks as [$title, $text])
                <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
            @endforeach
        </div>
    </section>

    <x-marketing.cta text="Maak een zakelijk account aan, rond de verificatie af en plaats je eerste kavel."
        primary="Zakelijk account aanmaken" :primary-url="$registerUrl">
        Klaar om <span class="g">te beginnen?</span>
    </x-marketing.cta>
</x-layouts.public>
