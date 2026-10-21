@use('App\Enums\CompanyPlan')
@use('App\Support\Money\Money')

@php
    $fee = fn (CompanyPlan $plan): string => rtrim(rtrim(number_format($plan->defaultFeePercentage(), 1, ',', '.'), '0'), ',').'%';
    $borg = Money::format((int) config('aucvy.deposit.threshold_cents'));
    $houdtermijn = (int) config('aucvy.orders.payout_hold_hours');
    $plans = [
        CompanyPlan::Start->value => ['Voor wie af en toe iets verkoopt.', 'Geen abonnement, geen plaatsingskosten.'],
        CompanyPlan::Pro->value => ['Voor bedrijven met een doorlopende stroom.', 'Lagere succesfee; afspraken via het zakelijke plan.'],
        CompanyPlan::Enterprise->value => ['Voor curatoren, leasemaatschappijen en grote partijen.', 'Laagste succesfee; maatwerkafspraken.'],
    ];
@endphp

{{--
    Prijzen (briefing sectie 1): alleen wat vaststaat. De succesfee komt uit
    CompanyPlan, de borgdrempel en houdtermijn uit de config. Pakketten en
    Ivy Boost zijn een besluit van Raphael en staan hier als "volgt".
--}}
<x-layouts.public title="Prijzen" eyebrow="Tarieven" heading="Plaatsen is gratis, je betaalt bij verkoop" align="center"
    intro="Geen abonnement en geen plaatsingskosten. Kopers betalen geen opgeld; verkopers betalen een succesfee over een geslaagde verkoop.">

    <section class="how-section" aria-labelledby="fees">
        <h2 id="fees" class="sr-only">Succesfee per plan</h2>
        <div class="fee-grid">
            @foreach ($plans as $plan => [$who, $note])
                <div class="fee-tile">
                    <b>{{ $fee(CompanyPlan::from($plan)) }}</b>
                    <span>{{ ucfirst($plan) }}</span>
                    <small>bij verkoop</small>
                    <p class="mt-3 text-sm">{{ $who }}</p>
                    <p class="text-xs text-aj-muted">{{ $note }}</p>
                </div>
            @endforeach
        </div>
        <p class="fee-note">De fee staat vooraf per kavel in de kostenopbouw en wordt alleen ingehouden op een geslaagde verkoop. Over de fee geldt btw.</p>
    </section>

    <section class="how-section" aria-labelledby="buyers">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Voor kopers</span>
                <h2 id="buyers">Wat je ziet, is wat je betaalt</h2>
                <p>Bod, btw-regeling en eventueel transport staan bij elkaar vóórdat je biedt. Geen opgeld.</p>
            </div>
        </div>
        <div class="biz-grid">
            <div><h3>Geen opgeld</h3><p>Het hamerbedrag is het bedrag. Alleen de btw-regeling van de kavel (marge of 21%) komt erbij, en die staat erbij.</p></div>
            <div><h3>Borg boven {{ $borg }}</h3><p>Bij een bod boven {{ $borg }} vraagt het platform een reservering als zekerheid dat de winnaar betaalt. Wie niet wint, krijgt de borg direct na de hamerslag terug.</p></div>
            <div><h3>Transport apart</h3><p>Ophalen is gratis. Kies je voor transport, dan zie je de indicatie vooraf en regel je het na de hamerslag.</p></div>
        </div>
    </section>

    <section class="how-section" aria-labelledby="sellers">
        <div class="sec-head">
            <div>
                <span class="eyebrow">Voor verkopers</span>
                <h2 id="sellers">Alles inbegrepen, tot de uitbetaling</h2>
                <p>Ivy herkent, schrijft en waardeert; de betaling loopt via het platform; de uitbetaling volgt na de houdtermijn.</p>
            </div>
        </div>
        <div class="biz-grid">
            <div><h3>Ivy inbegrepen</h3><p>Herkennen uit foto's, de advertentietekst en de waarde-indicatie zitten in elk plan. Geen extra kosten per kavel.</p></div>
            <div><h3>Betaling via het platform</h3><p>De koper betaalt aan het platform. Na levering en een houdtermijn van {{ $houdtermijn }} uur zonder melding wordt het bedrag minus de fee vrijgegeven voor uitbetaling.</p></div>
            <div><h3>Niet verkocht, geen kosten</h3><p>Haalt een kavel de reserveprijs niet of blijft hij zonder bod, dan betaal je niets en plaats je hem opnieuw.</p></div>
        </div>
        <div class="sec-cta">
            <p>Pakketten met extra zichtbaarheid en Ivy Boost volgen; die prijzen zijn nog niet vastgesteld. Tot die tijd geldt alleen de succesfee.</p>
            <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen</a>
            <a class="btn btn-ghost" href="{{ route('business') }}#tarieven">Zakelijk plan</a>
        </div>
    </section>
</x-layouts.public>
