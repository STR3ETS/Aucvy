@use('App\Enums\CompanyPlan')
@use('App\Support\Money\Money')
@use('App\Support\PlatformFacts')

@php
    $percent = fn (float|int $value): string => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',').'%';
    $whole = fn (int $cents): string => '€ '.number_format($cents / 100, 0, ',', '.');
    $facts = PlatformFacts::all();
    $borg = $whole((int) config('aucvy.deposit.threshold_cents'));
    $houdtermijn = (int) config('aucvy.orders.payout_hold_hours');
    $adminFee = (int) $facts['admin_fee_cents'];

    // De succesfee: de staffel op omzet als die aan staat, anders het tarief per plan.
    $usesTiers = PlatformFacts::usesFeeTiers();
    $rates = $usesTiers
        ? collect(PlatformFacts::feeTiers())->map(fn (array $tier): array => [
            'label' => $tier['up_to_cents'] === null ? 'Daarboven' : 'Tot '.$whole((int) $tier['up_to_cents']),
            'percent' => (float) $tier['percent'],
        ])->values()->all()
        : collect(CompanyPlan::cases())->map(fn (CompanyPlan $plan): array => [
            'label' => ucfirst($plan->value),
            'percent' => (float) $plan->defaultFeePercentage(),
        ])->values()->all();
    $first = $rates[0];
    $last = $rates[count($rates) - 1];
@endphp

{{--
    Prijzen (briefing sectie 1): alleen wat vaststaat. De succesfee komt uit
    dezelfde bron als de afrekening (de staffel van 2 november 2026), de
    borgdrempel, de administratiekosten en de houdtermijn uit de instellingen.
    Pakketten en Ivy Boost zijn een besluit van Raphael en staan hier als
    "volgt".
--}}
<x-layouts.public title="Prijzen" eyebrow="Tarieven"
    intro="Geen abonnement en geen plaatsingskosten. Kopers betalen geen opgeld; verkopende bedrijven een succesfee over een geslaagde verkoop.">
    <x-slot:headline>Plaatsen is gratis. <span class="g">Je betaalt bij verkoop.</span></x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">Geen abonnement en geen plaatsingskosten. Kopers betalen geen opgeld; verkopende bedrijven een succesfee over een geslaagde verkoop.</p>
        <div class="page-actions" style="margin-top:18px">
            <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            <a class="btn btn-outline-white" href="{{ route('business') }}">Zakelijk verkopen</a>
        </div>
        <div class="d-hero-trust">
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Geen</b>&nbsp;abonnement</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Ivy</b>&nbsp;inbegrepen</span>
            <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>Niet verkocht,</b>&nbsp;geen kosten</span>
        </div>
    </x-slot:lead>
    <x-slot:actions>
        <div class="d-hero-table" aria-label="Wat kost het?">
            <span class="title">Wat kost het?</span>
            <div><span>Plaatsen</span><b>€ 0</b></div>
            <div><span>Opgeld voor kopers</span><b>€ 0</b></div>
            <div><span>Niet verkocht</span><b>€ 0</b></div>
            <div><span>Verkocht</span><b>Succesfee</b></div>
        </div>
    </x-slot:actions>

    <section class="d-sec" aria-labelledby="fees"
             x-data="{ price: 5000, rate: 0, rates: {{ Js::from($rates) }}, get percent() { return this.rates[this.rate].percent; }, get fee() { return Math.round(this.price * this.percent) / 100; }, euro(value) { return '€ ' + value.toLocaleString('nl-NL', { maximumFractionDigits: 2 }); } }">
        <span class="d-pill">Voor verkopers</span>
        <h2 id="fees">De succesfee.</h2>
        <p class="d-sec-lead">De fee staat vooraf per kavel in de kostenopbouw en wordt alleen ingehouden op een geslaagde verkoop. Over de fee geldt btw.</p>

        @if ($usesTiers)
            {{-- De staffel: hoe meer omzet op Aucvy in de laatste twaalf maanden, hoe lager het tarief. --}}
            <div class="d-rate-grid">
                <div class="d-rate is-first">
                    <strong>Je begint op</strong>
                    <b>{{ $percent($first['percent']) }}</b>
                    <span class="note">bij verkoop</span>
                    <p>Voor elk bedrijf dat start, tot {{ $whole((int) PlatformFacts::feeTiers()[0]['up_to_cents']) }} omzet op Aucvy. Geen abonnement, geen plaatsingskosten.</p>
                </div>
                <div class="d-rate is-dark">
                    <strong>Het zakt met je omzet</strong>
                    <b>{{ $percent($rates[2]['percent'] ?? $last['percent']) }}</b>
                    <span class="note">bijvoorbeeld {{ mb_strtolower($rates[2]['label'] ?? $last['label']) }}</span>
                    <p>Het tarief volgt je omzet op Aucvy in de laatste twaalf maanden. Je hoeft er niets voor aan te vragen.</p>
                </div>
                <div class="d-rate">
                    <strong>Het laagste tarief</strong>
                    <b>{{ $percent($last['percent']) }}</b>
                    <span class="note">bij verkoop</span>
                    <p>Voor curatoren, leasemaatschappijen en andere grote partijen met een doorlopende stroom.</p>
                </div>
            </div>

            <details class="d-rate-all">
                <summary>Bekijk de hele staffel</summary>
                <ul class="d-tiers">
                    @foreach ($rates as $rate)
                        <li><span>{{ $rate['label'] }} omzet in twaalf maanden</span><strong>{{ $percent($rate['percent']) }}</strong></li>
                    @endforeach
                </ul>
                <p class="d-hint">Het tarief ligt vast op het moment van verkoop en wordt nooit achteraf verrekend.</p>
            </details>
        @else
            <div class="d-rate-grid">
                @foreach ($rates as $index => $rate)
                    <div class="d-rate{{ $index === 1 ? ' is-dark' : '' }}">
                        <strong>{{ $rate['label'] }}</strong>
                        <b>{{ $percent($rate['percent']) }}</b>
                        <span class="note">bij verkoop</span>
                    </div>
                @endforeach
            </div>
        @endif

        <h3 class="d-site-h2" style="margin-top:36px">Reken het zelf uit</h3>
        <div class="d-calc">
            <div>
                <span class="d-calc-label">Verkoopprijs</span>
                <div class="d-chips" role="group" aria-label="Verkoopprijs">
                    @foreach ([1000, 5000, 15000, 50000] as $amount)
                        <button type="button" class="d-chip" :class="price === {{ $amount }} && 'is-on'" :aria-pressed="price === {{ $amount }}" @click="price = {{ $amount }}">{{ $whole($amount * 100) }}</button>
                    @endforeach
                </div>
                <span class="d-calc-label" style="margin-top:18px">{{ $usesTiers ? 'Je omzet op Aucvy in twaalf maanden' : 'Plan' }}</span>
                <div class="d-chips" role="group" aria-label="{{ $usesTiers ? 'Omzet' : 'Plan' }}">
                    @foreach ($rates as $index => $rate)
                        <button type="button" class="d-chip" :class="rate === {{ $index }} && 'is-on'" :aria-pressed="rate === {{ $index }}" @click="rate = {{ $index }}">{{ $rate['label'] }}</button>
                    @endforeach
                </div>
            </div>
            <div class="d-calc-result" aria-live="polite">
                <div class="d-row"><span x-text="'Succesfee (' + percent.toString().replace('.', ',') + '%)'">Succesfee ({{ $percent($first['percent']) }})</span><strong x-text="euro(fee)">{{ $whole((int) round(5000 * $first['percent'])) }}</strong></div>
                <div class="total"><span>Jij houdt over</span><b x-text="euro(price - fee)">{{ $whole((int) round(500000 - 5000 * $first['percent'])) }}</b></div>
                <p class="d-hint">Rekenvoorbeeld, nog zonder de btw over de fee.</p>
            </div>
        </div>
    </section>

    <section class="d-sec d-band-grey d-bleed" aria-labelledby="buyers">
        <span class="d-pill">Voor kopers</span>
        <h2 id="buyers">Wat je ziet, is wat je betaalt.</h2>
        <p class="d-sec-lead">Bod, btw-regeling, administratiekosten en eventueel transport staan bij elkaar vóórdat je biedt. Geen opgeld.</p>
        <div class="d-cards3">
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Geen opgeld</h3><p>Je betaalt je bod, de btw-regeling van de kavel (marge of 21%) en {{ $adminFee > 0 ? Money::format($adminFee).' administratiekosten per bestelling, exclusief btw' : 'geen extra kosten' }}. Alles staat erbij voordat je biedt.</p></div>
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Borg boven {{ $borg }}</h3><p>Bij een bod boven {{ $borg }} vraagt het platform een reservering als zekerheid dat de winnaar betaalt. Wie niet wint, krijgt de borg direct na de hamerslag terug.</p></div>
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Transport apart</h3><p>Ophalen is gratis. Kies je voor transport, dan zie je de indicatie vooraf en regel je het na de hamerslag.</p></div>
        </div>
    </section>

    <section class="d-sec" aria-labelledby="sellers">
        <span class="d-pill">Inbegrepen</span>
        <h2 id="sellers">Alles inbegrepen, tot de uitbetaling.</h2>
        <p class="d-sec-lead">Ivy herkent, schrijft en waardeert; de betaling loopt via het platform; de uitbetaling volgt na de houdtermijn.</p>
        <div class="d-cards3">
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Ivy inbegrepen</h3><p>Herkennen uit foto's, de advertentietekst en de waarde-indicatie zitten erbij voor elke verkoper. Geen extra kosten per kavel.</p></div>
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Betaling via het platform</h3><p>De koper betaalt aan het platform. Na levering en een houdtermijn van {{ $houdtermijn }} uur zonder melding wordt het bedrag minus de fee vrijgegeven voor uitbetaling.</p></div>
            <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>Niet verkocht, geen kosten</h3><p>Haalt een kavel de reserveprijs niet of blijft hij zonder bod, dan betaal je niets en plaats je hem opnieuw.</p></div>
        </div>
        <p class="d-callout-info" style="margin-top:14px;padding:14px 18px"><span><strong style="display:inline;font-size:inherit;margin:0">Later meer.</strong> Pakketten met extra zichtbaarheid en Ivy Boost volgen; die prijzen zijn nog niet vastgesteld. Tot die tijd geldt alleen de succesfee.</span></p>
    </section>

    <x-marketing.cta />
</x-layouts.public>
