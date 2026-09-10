@use('App\Enums\CompanyPlan')
@use('App\Support\Money\Money')

{{--
    De verkooproute (briefing hfst. 2b: "de uitlegpagina voor verkopers, los
    van de funnel zelf"). Statisch, maar de tarieven komen uit CompanyPlan en
    de borgdrempel uit de config - tekst en code kunnen niet uiteenlopen.
--}}
<x-layouts.public title="Verkooproute">
    <div class="mx-auto max-w-3xl">
        <p class="text-sm font-bold text-aj-blue-text">Verkopen op Aucvy</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-[-0.03em]">Van foto tot uitbetaling</h1>
        <p class="mt-3 text-aj-soft">
            Jij levert de foto's en het akkoord; Ivy en haar team doen het werk ertussen.
            Plaatsen is gratis, je betaalt alleen een succesfee als er echt verkocht is.
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('sell') }}" class="btn btn-primary">Kavel plaatsen</a>
            <a href="{{ route('faq') }}" class="btn btn-ghost">Veelgestelde vragen</a>
        </div>

        {{-- De zes stappen, met wie het werk doet. --}}
        <section class="mt-10" aria-label="De zes stappen">
            <h2 class="text-lg font-extrabold">Zo werkt het, in zes stappen</h2>
            <ol class="mt-4 space-y-4">
                @foreach ([
                    ['n' => 1, 'agent' => null, 'title' => 'Upload je foto\'s', 'body' => 'Een paar foto\'s vanaf je telefoon zijn genoeg om te beginnen. Documenten zoals een aankoopfactuur kunnen later, en een account heb je pas nodig bij het publiceren.'],
                    ['n' => 2, 'agent' => 'ivy', 'title' => 'Ivy herkent je object', 'body' => 'Merk, model, bouwjaar en conditie, met een zekerheidsscore per onderdeel zodat je weet wat je moet nakijken. Klopt iets niet, dan pas je het gewoon aan.'],
                    ['n' => 3, 'agent' => 'nova', 'title' => 'Nova schrijft de advertentie', 'body' => 'Titel, omschrijving en specificaties staan klaar, eerlijk over gebreken. Jij past aan of keurt goed; zonder jouw akkoord gaat er niets live.'],
                    ['n' => 4, 'agent' => 'atlas', 'title' => 'Atlas geeft een waarde-indicatie', 'body' => 'Een range op basis van vergelijkbare verkopen, altijd met de zekerheid erbij. Een indicatie is geen belofte; bij lage zekerheid zeggen we dat er ook bij.'],
                    ['n' => 5, 'agent' => 'finn', 'title' => 'Finn adviseert de verkoopvorm', 'body' => 'Veiling als de markt de prijs mag bepalen, afslag als het snel moet, koop direct bij een vaste prijs. Finn adviseert, jij beslist.'],
                    ['n' => 6, 'agent' => 'flow', 'title' => 'Flow handelt af', 'body' => 'Betaling, transport en uitbetaling worden geregeld. Na levering geldt een korte houdtermijn waarin de koper kan controleren; daarna wordt er uitbetaald.'],
                ] as $step)
                    <li class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                        <span class="tabular flex size-8 shrink-0 items-center justify-center rounded-aj-sm
                                     bg-aj-blue/10 text-sm font-extrabold text-aj-blue-text">{{ $step['n'] }}</span>
                        <div class="min-w-0">
                            <p class="flex items-center gap-2 text-sm font-extrabold">
                                @if ($step['agent'])
                                    <x-ui.agent-avatar :agent="$step['agent']" class="size-6" />
                                @endif
                                {{ $step['title'] }}
                            </p>
                            <p class="mt-1 text-sm text-aj-muted">{{ $step['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Tarieven, rechtstreeks uit CompanyPlan. --}}
        <section class="mt-10" aria-label="Tarieven" id="tarieven">
            <h2 class="text-lg font-extrabold">Wat het kost</h2>
            <p class="mt-1 text-sm text-aj-muted">
                Plaatsen is gratis en kopers betalen geen opgeld. Alleen bij een geslaagde
                verkoop wordt de succesfee ingehouden op de uitbetaling.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                @foreach ([
                    [CompanyPlan::Start, 'Start', 'Voor wie af en toe iets verkoopt.'],
                    [CompanyPlan::Pro, 'Pro', 'Voor een doorlopende stroom kavels.'],
                    [CompanyPlan::Enterprise, 'Enterprise', 'Voor liquidaties en volume, met eigen afspraken.'],
                ] as [$plan, $label, $body])
                    <div class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                        <p class="text-sm font-extrabold">{{ $label }}</p>
                        <p class="tabular mt-1 text-2xl font-extrabold">
                            {{ rtrim(rtrim(number_format($plan->defaultFeePercentage(), 1, ',', '.'), '0'), ',') }}%
                        </p>
                        <p class="text-xs text-aj-muted">succesfee bij verkoop</p>
                        <p class="mt-2 text-sm text-aj-muted">{{ $body }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-aj-muted">
                Het exacte bedrag staat vóór publicatie per kavel in de kostenopbouw - hetzelfde
                getal dat straks op de afrekening staat.
            </p>
        </section>

        {{-- Wat er geregeld is rond geld en zekerheid. --}}
        <section class="mt-10" aria-label="Goed geregeld">
            <h2 class="text-lg font-extrabold">Goed om te weten</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li class="flex items-start gap-2.5">
                    <svg class="mt-0.5 size-4 shrink-0 text-aj-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
                    </svg>
                    Bieders boven {{ Money::format((int) config('aucvy.deposit.threshold_cents')) }} hebben eerst een borg gereserveerd - dat filtert vrijblijvende bieders eruit.
                </li>
                <li class="flex items-start gap-2.5">
                    <svg class="mt-0.5 size-4 shrink-0 text-aj-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
                    </svg>
                    Een bod in de slotfase verlengt de veiling automatisch, zodat niemand wint door te snipen.
                </li>
                <li class="flex items-start gap-2.5">
                    <svg class="mt-0.5 size-4 shrink-0 text-aj-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
                    </svg>
                    Reserveprijs, looptijd en bodemprijs stel je zelf in; wat je invult zie je terug vóór je publiceert.
                </li>
                <li class="flex items-start gap-2.5">
                    <svg class="mt-0.5 size-4 shrink-0 text-aj-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
                    </svg>
                    Hoe zekerheid en de geschilroute werken staat op de
                    <a href="{{ route('assurance') }}" class="font-bold text-aj-blue-text underline">zekerheidspagina</a>.
                </li>
            </ul>
        </section>

        <div class="mt-10 rounded-aj-s border border-aj-border bg-aj-surface p-6 text-center">
            <p class="text-lg font-extrabold">Klaar om te beginnen?</p>
            <p class="mt-1 text-sm text-aj-muted">Je ziet eerst wat Ivy van je spullen maakt; een account komt pas bij het publiceren.</p>
            <a href="{{ route('sell') }}" class="btn btn-primary mt-4">Kavel plaatsen</a>
        </div>
    </div>
</x-layouts.public>
