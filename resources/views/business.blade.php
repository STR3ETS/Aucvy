@use('App\Enums\CompanyPlan')

@php
    $fee = fn (CompanyPlan $plan): string => rtrim(rtrim(number_format($plan->defaultFeePercentage(), 1, ',', '.'), '0'), ',').'%';
    $team = [
        ['ivy', 'Ivy', 'Coördineert, herkent en adviseert.'],
        ['atlas', 'Atlas', 'Waarde-indicaties en marktdata.'],
        ['nova', 'Nova', 'Advertentietekst en promotie.'],
        ['finn', 'Finn', 'Prijsadvies en verkoopvorm.'],
        ['flow', 'Flow', 'Transport en planning.'],
        ['lex', 'Lex', 'Documenten en voorwaarden.'],
    ];
@endphp

<x-layouts.public title="Zakelijk" heading="Een verkoopteam dat nooit slaapt." eyebrow="Zakelijk"
    intro="Voor bedrijven met een doorlopende stroom aan inventaris, overtollig materieel of een liquidatie. Ivy coördineert zes specialisten en jij houdt de regie.">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('register') }}">Zakelijk account aanmaken</a>
        <a class="btn btn-ghost" href="{{ route('sell') }}">Kavel plaatsen</a>
    </x-slot:actions>

    {{-- Voor wie. --}}
    <section class="how-section" id="situaties" aria-labelledby="biz-for">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Voor wie</span>
                <h2 id="biz-for">Drie situaties, één aanpak</h2>
                <p>Of het nu om één machine gaat of om een complete inventaris: jij levert foto's en het akkoord, het team doet de rest.</p>
            </div>
        </div>
        <div class="biz-grid">
            <article class="biz-card">
                <div class="glyph"><x-v4.icon name="truck" :size="22" stroke="1.8" /></div>
                <h3>Doorlopende stroom</h3>
                <p>Vervangen materieel, retouren of overtollige voorraad. Plaats per kavel of in series, met vaste afspraken over vorm en planning.</p>
            </article>
            <article class="biz-card">
                <div class="glyph"><x-v4.icon name="building" :size="22" stroke="1.8" /></div>
                <h3>Verhuizing of sluiting</h3>
                <p>Een complete inventaris in één keer. Ivy herkent per object, Nova schrijft, Atlas waardeert. Jij keurt goed.</p>
            </article>
            <article class="biz-card">
                <div class="glyph"><x-v4.icon name="gavel" :size="22" stroke="1.8" /></div>
                <h3>Liquidatie</h3>
                <p>Snel en controleerbaar: afslag of veiling met een duidelijke bodem, de biedhistorie vastgelegd, uitbetaling na de houdtermijn.</p>
            </article>
        </div>
        <div class="sec-cta">
            <p>Herken je je situatie? Een zakelijk account is zo aangemaakt; de KVK-controle volgt bij de verificatie.</p>
            <a class="btn btn-primary" href="{{ route('register') }}">Zakelijk account aanmaken</a>
            <a class="btn btn-ghost" href="{{ route('sell') }}">Kavel plaatsen</a>
        </div>
    </section>

    {{-- Het team. --}}
    <section class="how-section" id="team" aria-labelledby="biz-team">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Het team</span>
                <h2 id="biz-team">Zes specialisten, één coördinator</h2>
                <p>Ivy verdeelt het werk en meldt alleen wat je moet weten. Elke stap is terug te zien in je dashboard.</p>
            </div>
        </div>
        <ul class="team-grid">
            @foreach ($team as [$slug, $name, $role])
                <li>
                    <x-ui.agent-avatar :agent="$slug" class="size-14" />
                    <b>{{ $name }}</b>
                    <span>{{ $role }}</span>
                </li>
            @endforeach
        </ul>
        <div class="sec-cta">
            <p>Wil je zien wat het team per stap doet? De uitleg laat het hele proces zien.</p>
            <a class="btn btn-primary" href="{{ route('how-it-works') }}#reis">Hoe het werkt</a>
            <a class="btn btn-ghost" href="{{ route('selling.guide') }}">Bekijk de verkooproute</a>
        </div>
    </section>

    {{-- Tarieven, uit CompanyPlan. --}}
    <section class="how-section" id="tarieven" aria-labelledby="biz-plans">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Tarieven</span>
                <h2 id="biz-plans">Alleen betalen bij resultaat</h2>
                <p>Plaatsen is gratis. De succesfee hangt af van je plan en staat vooraf per kavel in de kostenopbouw.</p>
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
        <p class="fee-note">Een afwijkende afspraak voor grote volumes leggen we vast op je bedrijfsprofiel, zodat het tarief per kavel klopt zonder uitzonderingen achteraf.</p>
        <div class="sec-cta">
            <p>Plaatsen is gratis. Je ziet het tarief vooraf in de kostenopbouw van elke kavel.</p>
            <a class="btn btn-primary" href="{{ route('register') }}">Zakelijk account aanmaken</a>
            <a class="btn btn-ghost" href="{{ route('faq') }}">Vragen over tarieven</a>
        </div>
    </section>

    {{-- Zekerheid voor bedrijven. --}}
    <section class="how-section" id="zekerheid" aria-labelledby="biz-trust">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Zekerheid</span>
                <h2 id="biz-trust">Wat er geregeld is</h2>
                <p>Handel tussen bedrijven vraagt om duidelijke afspraken. Dit regelt Aucvy bij elke transactie.</p>
            </div>
        </div>
        <div class="checks">
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>KVK-controle en verificatie</h3><p>Identiteit en, zakelijk, de KVK-inschrijving vóór een kavel live kan.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Kostenopbouw vooraf</h3><p>Bod, transport en btw-regeling staan bij elkaar vóórdat een koper biedt.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Houdtermijn op uitbetaling</h3><p>Je ontvangt het geld nadat de koper heeft kunnen controleren.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Geschilroute met bemiddeling</h3><p>Een melding binnen de termijn, jouw reactie met eigen bewijs, een medewerker die beoordeelt.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Biedhistorie vastgelegd</h3><p>Elk bod krijgt een volgnummer en blijft controleerbaar bewaard.</p></div>
            <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Trust Index op je profiel</h3><p>Afgeronde transacties, documentkwaliteit en levering bouwen je score op; kopers zien hem bij elke kavel.</p></div>
        </div>
        <div class="sec-cta">
            <p>De zekerheidspagina legt de geschilroute uit, met de termijnen erbij.</p>
            <a class="btn btn-primary" href="{{ route('assurance') }}">Lees hoe zekerheid werkt</a>
            <a class="btn btn-ghost" href="{{ route('faq') }}">Veelgestelde vragen</a>
        </div>
    </section>

    {{-- Beginnen. --}}
    <section class="cta-band" aria-labelledby="biz-start">
        <canvas class="dots" x-data="dotField({ strength: 0.6 })" aria-hidden="true"></canvas>
        <div class="cta-band-text">
            <h2 id="biz-start">Klaar om te beginnen?</h2>
            <p>Maak een zakelijk account aan, rond de verificatie af en plaats je eerste kavel. Ivy neemt het vanaf de foto over.</p>
        </div>
        <div class="cta-band-actions">
            <a class="btn btn-white" href="{{ route('register') }}">Zakelijk account aanmaken</a>
            <a class="btn btn-outline-white" href="{{ route('how-it-works') }}">Hoe het werkt</a>
        </div>
    </section>
</x-layouts.public>
