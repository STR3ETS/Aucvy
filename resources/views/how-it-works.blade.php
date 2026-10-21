@use('App\Enums\CompanyPlan')

@php
    // Dezelfde bronnen als de code die het afdwingt: verandert een termijn of
    // een tarief, dan verandert deze pagina automatisch mee.
    $melden = (int) config('aucvy.orders.dispute_window_hours');
    $betalen = (int) config('aucvy.orders.payment_deadline_hours');
    $fee = fn (CompanyPlan $plan): string => rtrim(rtrim(number_format($plan->defaultFeePercentage(), 1, ',', '.'), '0'), ',').'%';
@endphp

<x-layouts.public title="Hoe het werkt" eyebrow="Uitleg"
    intro="Kopen en verkopen op Aucvy, stap voor stap. Jij levert de foto's en het akkoord; Ivy en haar team doen het werk ertussen.">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen</a>
        <a class="btn btn-ghost" href="{{ route('lots.index') }}">Naar de veilingen</a>
    </x-slot:actions>

    {{-- Kopen: vier stappen. --}}
    <section class="how-section" id="kopen" aria-labelledby="how-buy">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Kopen</span>
                <h2 id="how-buy">Van zoeken tot ophalen</h2>
                <p>Geen opgeld, geen verrassingen: wat je ziet vóór je biedt, is wat je betaalt.</p>
            </div>
        </div>
        <ol class="how-steps">
            <li>
                <span class="n">1</span>
                <h3>Zoek, of vraag het Ivy</h3>
                <p>Filter op categorie, conditie en plaats, of beschrijf in je eigen woorden wat je zoekt. Ivy leest de vraag en zet hem om in filters die je kunt corrigeren.</p>
            </li>
            <li>
                <span class="n">2</span>
                <h3>Bekijk de kavel</h3>
                <p>Foto's, specificaties, de waarde-indicatie van Ivy en de volledige kostenopbouw. Kijkdagen plan je bij de kavel.</p>
            </li>
            <li>
                <span class="n">3</span>
                <h3>Bied, mijn af of koop direct</h3>
                <p>Bij een veiling bied je automatisch mee tot je maximum en verlengt een bod in de slotfase de veiling. Bij afslag koop je zodra je afmijnt.</p>
            </li>
            <li>
                <span class="n">4</span>
                <h3>Betaal en haal op</h3>
                <p>Je betaalt binnen {{ $betalen }} uur. Transport regel je bij de kavel, of je haalt zelf op. Niet zoals beschreven? Meld het binnen {{ $melden }} uur na levering.</p>
            </li>
        </ol>
        <div class="sec-cta">
            <p>Klaar om te kijken? Het aanbod staat live, met de waarde-indicatie van Ivy erbij.</p>
            <a class="btn btn-primary" href="{{ route('lots.index') }}">Bekijk de veilingen</a>
            <a class="btn btn-ghost" href="{{ route('categories.index') }}">Alle categorieën</a>
        </div>
    </section>

    {{-- Verkopen: de zes stappen, gedeeld met de home. home.js haakt aan op #reis. --}}
    <section class="how-section reis-page" id="reis" aria-labelledby="how-sell">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Verkopen</span>
                <h2 id="how-sell">Van foto tot uitbetaling in zes stappen</h2>
                <p>Jij levert de foto en het akkoord. Ivy en haar team doen het werk ertussen.</p>
            </div>
        </div>
        <x-marketing.seller-journey />
        <div class="sec-cta">
            <p>Geen account nodig om te beginnen: je ziet eerst wat Ivy voor je maakt.</p>
            <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen</a>
            <a class="btn btn-ghost" href="{{ route('selling.guide') }}">Bekijk de verkooproute</a>
        </div>
    </section>

    {{-- De drie verkoopvormen. --}}
    <section class="how-section" id="vormen" aria-labelledby="how-forms">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Verkoopvormen</span>
                <h2 id="how-forms">Drie manieren om te verkopen</h2>
                <p>Elke kavel krijgt de vorm die bij het doel past. Jij kiest de vorm; Atlas geeft de waarde-indicatie mee.</p>
            </div>
        </div>
        <div class="forms">
            <article class="form">
                <div class="glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 18h16M6 18V9l6-4 6 4v9"/><path d="M12 5v13"/></svg></div>
                <h3>Veiling</h3>
                <p>Oplopend bieden met proxy-bieden en een verlengregel in de slotfase. De markt bepaalt de prijs.</p>
                <ul><li>Automatisch meebieden tot je maximum</li><li>Verlenging bij een bod in de laatste minuten</li><li>Geen opgeld voor de koper</li></ul>
            </article>
            <article class="form">
                <div class="glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6l7 7 4-4 5 5"/><path d="M20 10v4h-4"/></svg></div>
                <h3>Afslag</h3>
                <p>De prijs start hoog en daalt in vaste stappen. Wie als eerste afmijnt, koopt. Snel en transparant.</p>
                <ul><li>Daalt in vaste stappen</li><li>Bodemprijs stel jij in</li><li>Ideaal bij een deadline</li></ul>
            </article>
            <article class="form">
                <div class="glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7h14l-1.5 9h-11z"/><path d="M9 7V5a3 3 0 016 0v2"/></svg></div>
                <h3>Koop direct</h3>
                <p>Een vaste prijs, meteen te kopen. Voor kavels waar geen veiling nodig is.</p>
                <ul><li>Vaste prijs, geen wachttijd</li><li>Betalen via het platform</li><li>Transportindicatie vooraf</li></ul>
            </article>
        </div>
        <div class="sec-cta">
            <p>Twijfel je welke vorm past? Bij het plaatsen zie je de waarde-indicatie van Atlas; jij kiest de vorm.</p>
            <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen</a>
            <a class="btn btn-ghost" href="{{ route('faq') }}">Veelgestelde vragen</a>
        </div>
    </section>

    {{-- Kosten: de succesfee per plan, uit CompanyPlan. --}}
    <section class="how-section" id="kosten" aria-labelledby="how-costs">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Kosten</span>
                <h2 id="how-costs">Gratis plaatsen, betalen bij resultaat</h2>
                <p>Kopers betalen geen opgeld. Verkopers betalen alleen een succesfee over een geslaagde verkoop, afhankelijk van het plan.</p>
            </div>
        </div>
        <div class="fee-grid">
            @foreach (CompanyPlan::cases() as $plan)
                <div class="fee-tile">
                    <b>{{ $fee($plan) }}</b>
                    <span>{{ ucfirst($plan->value) }}</span>
                    <small>bij verkoop</small>
                </div>
            @endforeach
        </div>
        <p class="fee-note">Het tarief staat vooraf per kavel in de kostenopbouw en wordt alleen ingehouden op een geslaagde verkoop.</p>
        <div class="sec-cta">
            <p>Grotere volumes of een doorlopende stroom? Dan is het zakelijke plan interessant.</p>
            <a class="btn btn-primary" href="{{ route('business') }}#tarieven">Zakelijke tarieven</a>
            <a class="btn btn-ghost" href="{{ route('sell') }}">Kavel plaatsen</a>
        </div>
    </section>

    {{-- Zekerheid, kort. --}}
    <section class="how-section" id="zekerheid" aria-labelledby="how-trust">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Zekerheid</span>
                <h2 id="how-trust">Wat er geregeld is</h2>
                <p>Handel tussen bedrijven vraagt om duidelijke afspraken. Dit regelt Aucvy bij elke transactie.</p>
            </div>
        </div>
        <div class="checks">
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Geverifieerde verkopers</h3><p>KVK-controle en identiteitscheck voordat een kavel live kan.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Kosten vooraf zichtbaar</h3><p>Bod, transport en btw-regeling staan bij elkaar vóórdat je biedt.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Houdtermijn op uitbetaling</h3><p>De verkoper ontvangt het geld nadat de koper heeft kunnen controleren.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Geschilroute met bemiddeling</h3><p>Niet zoals beschreven? Dan bemiddelt Aucvy tussen koper en verkoper.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Biedhistorie vastgelegd</h3><p>Elk bod krijgt een volgnummer en blijft controleerbaar bewaard.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Reviews en de Trust Index</h3><p>Koper en verkoper beoordelen elkaar. Samen met levering en documenten bouwt dat de Trust Index op het verkopersprofiel.</p></div>
        </div>
        <div class="sec-cta">
            <p>De zekerheidspagina legt de geschilroute uit, met de termijnen erbij.</p>
            <a class="btn btn-primary" href="{{ route('assurance') }}">Lees hoe zekerheid werkt</a>
            <a class="btn btn-ghost" href="{{ route('faq') }}">Veelgestelde vragen</a>
        </div>
    </section>

    {{-- Verder lezen. --}}
    <section class="cta-band" aria-labelledby="how-more">
        <canvas class="dots" x-data="dotField({ strength: 0.6 })" aria-hidden="true"></canvas>
        <div class="cta-band-text">
            <h2 id="how-more">Nog vragen?</h2>
            <p>De veelgestelde vragen gaan dieper in op bieden, betalen en btw. De verkooproute laat het hele systeem in het klein zien.</p>
        </div>
        <div class="cta-band-actions">
            <a class="btn btn-white" href="{{ route('faq') }}">Veelgestelde vragen</a>
            <a class="btn btn-outline-white" href="{{ route('selling.guide') }}">Bekijk de verkooproute</a>
        </div>
    </section>
</x-layouts.public>
