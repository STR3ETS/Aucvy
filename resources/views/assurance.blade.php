@php
    // De belofte met deadlines (13.6): deze getallen komen uit dezelfde
    // config die orders:advance afdwingt. Verandert de termijn, dan verandert
    // deze pagina automatisch mee - tekst en code kunnen niet uiteenlopen.
    $melden = (int) config('aucvy.orders.dispute_window_hours');
    $reageren = (int) config('aucvy.orders.seller_response_hours');
    $betalen = (int) config('aucvy.orders.payment_deadline_hours');
@endphp

<x-layouts.public title="Zekerheid" heading="Bij elke kavel geregeld" eyebrow="Zekerheid" align="center"
    intro="Wat er bij elke kavel vaststaat, met de termijnen erbij. En wat er nog niet is.">
    <div class="mx-auto max-w-3xl">
        <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <p class="text-sm font-extrabold">Verkoper gecontroleerd</p>
                <p class="mt-1 text-sm text-aj-muted">Identiteit en - zakelijk - de KVK-inschrijving, vóór een kavel live kan.</p>
            </div>
            <div class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <p class="text-sm font-extrabold">Kosten vóór het bieden</p>
                <p class="mt-1 text-sm text-aj-muted">De volledige kostenopbouw staat op de kavelpagina - hetzelfde bedrag dat je straks betaalt.</p>
            </div>
            <div class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <p class="text-sm font-extrabold">Biedhistorie vastgelegd</p>
                <p class="mt-1 text-sm text-aj-muted">Elk bod krijgt een volgnummer en blijft controleerbaar bewaard.</p>
            </div>
            <div class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <p class="text-sm font-extrabold">Bezichtiging mogelijk</p>
                <p class="mt-1 text-sm text-aj-muted">Waar de verkoper dat aanbiedt, plan je een kijkdag vóór je biedt.</p>
            </div>
        </div>

        <section class="mt-10" aria-label="Als het misgaat">
            <h2 class="text-lg font-extrabold">Als het misgaat - met termijnen</h2>
            <ol class="mt-3 space-y-3">
                <li class="flex gap-3 text-sm">
                    <span class="tabular font-extrabold text-aj-blue-text">1</span>
                    <span>Je meldt het <strong>binnen {{ $melden }} uur</strong> na levering - vanaf dat moment wordt de uitbetaling aan de verkoper aangehouden.</span>
                </li>
                <li class="flex gap-3 text-sm">
                    <span class="tabular font-extrabold text-aj-blue-text">2</span>
                    <span>De verkoper reageert met eigen bewijs, <strong>binnen {{ $reageren }} uur</strong>.</span>
                </li>
                <li class="flex gap-3 text-sm">
                    <span class="tabular font-extrabold text-aj-blue-text">3</span>
                    <span>Een <strong>medewerker</strong> beoordeelt - geen algoritme.</span>
                </li>
                <li class="flex gap-3 text-sm">
                    <span class="tabular font-extrabold text-aj-blue-text">4</span>
                    <span>Je krijgt een uitkomst mét motivering: alsnog leveren, een prijscorrectie of terugdraaien.</span>
                </li>
            </ol>
        </section>

        {{-- Eerlijk over wat er nog niet is - geen escrow-claim (hfst. 9/18). --}}
        <section class="mt-8 rounded-aj-sm border border-aj-warn/30 bg-aj-warn/5 p-4" aria-label="In ontwikkeling">
            <p class="text-sm font-extrabold">⚠ Waar we nog aan werken</p>
            <p class="mt-1 text-sm text-aj-muted">
                Derdengelden-afwikkeling, technische keuring en verzekerd transport.
                Dat staat hier pas als het er echt is - niet eerder.
            </p>
        </section>

        <section class="mt-10" aria-label="Veelgestelde vragen">
            <h2 class="text-lg font-extrabold">Veelgestelde vragen</h2>
            <div class="mt-3 space-y-2">
                <details class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                    <summary class="cursor-pointer text-sm font-bold">Wanneer moet ik betalen?</summary>
                    <p class="mt-2 text-sm text-aj-muted">
                        Binnen {{ $betalen }} uur na de hamerslag. Betaal je niet, dan vervalt de koop automatisch.
                    </p>
                </details>
                <details class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                    <summary class="cursor-pointer text-sm font-bold">Wanneer krijgt de verkoper zijn geld?</summary>
                    <p class="mt-2 text-sm text-aj-muted">
                        Na levering loopt eerst de meldtermijn van {{ $melden }} uur. Geen melding? Dan komt de uitbetaling vrij.
                        Loopt er een geschil, dan staat de uitbetaling stil tot het is afgehandeld.
                    </p>
                </details>
                <details class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                    <summary class="cursor-pointer text-sm font-bold">Waarom moet ik me verifiëren voor ik bied?</summary>
                    <p class="mt-2 text-sm text-aj-muted">
                        Geen nepbiedingen en geen wanbetaling: iedereen die biedt is echt en aanspreekbaar.
                        Rondkijken en volgen kan zonder verificatie.
                    </p>
                </details>
                <details class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                    <summary class="cursor-pointer text-sm font-bold">Wat gebeurt er met een borg?</summary>
                    <p class="mt-2 text-sm text-aj-muted">
                        Boven {{ \App\Support\Money\Money::format((int) config('aucvy.deposit.threshold_cents')) }} vragen we
                        een reservering vóór je eerste bod. Win je niet, dan komt hij direct vrij; win je wel, dan wordt hij
                        verrekend met je betaling.
                    </p>
                </details>
            </div>
        </section>
    </div>
</x-layouts.public>
