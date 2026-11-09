@use('App\Support\Money\Money')
@use('App\Support\PlatformFacts')

@php
    // Termijnen en drempels komen uit dezelfde bron als de code die ze afdwingt.
    $facts = PlatformFacts::all();
    $betalen = (int) $facts['payment_deadline_hours'];
    $melden = (int) $facts['dispute_window_hours'];
    $reageren = (int) $facts['seller_response_hours'];
    $adminFee = (int) $facts['admin_fee_cents'];
    $borg = Money::format((int) config('aucvy.deposit.threshold_cents'));
    $slotVenster = (int) config('aucvy.auction.anti_snipe_window_seconds');
    $verlenging = (int) config('aucvy.auction.anti_snipe_extend_seconds');

    // Voorbeeldobjecten voor "Wat kan jouw voorraad opleveren?": vaste
    // voorbeeldbedragen in hele euro's, geen waarde-indicatie van een echte kavel.
    $examples = [
        ['id' => 'oven', 'name' => 'Rational combi-oven', 'low' => 2500, 'high' => 3500, 'image' => 'vb-combi-oven.jpg'],
        ['id' => 'espresso', 'name' => 'La Marzocco espressomachine', 'low' => 1000, 'high' => 1600, 'image' => 'vb-espressomachine.jpg'],
        ['id' => 'stoelen', 'name' => 'Partij 40 bureaustoelen', 'low' => 600, 'high' => 900, 'image' => 'vb-bureaustoelen.jpg'],
        ['id' => 'koelcel', 'name' => 'Horeca-koelcel 12 m³', 'low' => 4500, 'high' => 6500, 'image' => 'vb-koelcel.jpg'],
        ['id' => 'keuken', 'name' => 'Complete designkeuken', 'low' => 15000, 'high' => 19500, 'image' => 'vb-designkeuken.jpg'],
        ['id' => 'graver', 'name' => 'Minigraafmachine 3,5 ton', 'low' => 28000, 'high' => 34000, 'image' => 'cat-bouw-materieel.jpg'],
    ];
    $picked = ['oven', 'espresso', 'stoelen'];
    $euro = fn (int $amount): string => '€ '.number_format($amount, 0, ',', '.');
    $pickedItems = collect($examples)->whereIn('id', $picked);

    $steps = [
        ['Upload', 'Een paar foto\'s vanaf je telefoon zijn genoeg. Documenten kunnen later.'],
        ['Ivy herkent, Nova schrijft', 'Merk, model en conditie met een zekerheidsscore, plus een advertentie die klaarstaat.'],
        ['Atlas onderbouwt de waarde', 'Een range op basis van vergelijkbare verkopen, met de bron erbij.'],
        ['Jij kiest en keurt goed', 'Veiling, afslag of koop direct. Zonder jouw akkoord gaat niets live.'],
        ['Verkocht en uitbetaald', 'Betaling en uitbetaling lopen via het platform. Transport regel je met de koper.'],
    ];
    $team = [
        ['ivy', 'Ivy', 'Coördineert, herkent en adviseert.'],
        ['atlas', 'Atlas', 'Waarde-indicaties en marktdata.'],
        ['nova', 'Nova', 'Advertentietekst en promotie.'],
        ['finn', 'Finn', 'Prijsadvies en verkoopvorm.'],
        ['flow', 'Flow', 'Transport en planning.'],
        ['lex', 'Lex', 'Documenten en voorwaarden.'],
    ];
    $checks = [
        ['Geverifieerde verkopers', 'KVK-controle en identiteitscheck voordat een kavel live kan.'],
        ['Kosten vooraf zichtbaar', 'Bod, transport en btw-regeling staan bij elkaar vóórdat je biedt.'],
        ['Houdtermijn op uitbetaling', 'De verkoper ontvangt het geld nadat de koper heeft kunnen controleren.'],
        ['Geschilroute met bemiddeling', 'Niet zoals beschreven? Dan bemiddelt Aucvy tussen koper en verkoper.'],
        ['Biedhistorie vastgelegd', 'Elk bod krijgt een volgnummer en blijft controleerbaar bewaard.'],
        ['Reviews en de Trust Index', 'Koper en verkoper beoordelen elkaar. Samen met levering en documenten bouwt dat de Trust Index op.'],
    ];
    $buyerPoints = [
        ['Waarde-indicatie bij elke kavel', 'Een range van Atlas, met de bron erbij. Geen belofte, wel houvast.'],
        ['Zien wat hot is', 'Je ziet hoeveel mensen een kavel bekeken, opgeslagen en gedeeld hebben, en welke nu het meest leeft.'],
        ['Geen opgeld, geen verrassingen', 'Bod, btw en transport staan bij elkaar vóórdat je biedt.'],
    ];
    $questions = [
        'Kopen' => [
            ['Wat kost kopen op Aucvy?', 'Geen opgeld. Je betaalt je bod, de eventuele btw'.($adminFee > 0 ? ', '.Money::format($adminFee).' administratiekosten per bestelling' : '').' en transport als je dat afneemt. Je ziet de kostenopbouw vóórdat je biedt.'],
            ['Is een bod bindend?', 'Ja. Boven '.$borg.' vragen we eerst een borgreservering; win je niet, dan komt die automatisch vrij. Een bod in de laatste '.$slotVenster.' seconden verlengt de veiling met '.$verlenging.' seconden.'],
            ['Wat als een kavel niet klopt?', 'Je betaalt binnen '.$betalen.' uur na de hamerslag. Na levering heb je '.$melden.' uur om iets te melden. De verkoper reageert binnen '.$reageren.' uur, en daarna beoordeelt een medewerker van Aucvy.'],
        ],
        'Verkopen' => [
            ['Wat kost verkopen?', 'Plaatsen is gratis. Je betaalt alleen een succesfee over een geslaagde verkoop. Het tarief zie je vooraf per kavel.'],
            ['Wie mag verkopen?', 'Alleen bedrijven met een geverifieerd zakelijk account. Een concept maken kan al zonder account.'],
            ['Wanneer krijg ik mijn geld?', 'De koper betaalt aan het platform. Na levering heeft de koper '.$melden.' uur om iets te melden. Geen melding? Dan komt de uitbetaling vrij, minus de succesfee.'],
        ],
    ];
@endphp

{{--
    De home (variant C, de standaard) volgens het ontwerp van november: de
    kop met de vraag aan Ivy, een wit vel met de categorieën en de handel van
    dit moment, twee probeerblokken, de route in vijf stappen, het team, de
    vergelijking, de afspraken in getallen, zekerheid, de koperskant en de
    vragen. Aantallen, termijnen en drempels komen uit de code; de bedragen
    in de probeerblokken zijn voorbeelden en staan er zo bij.
--}}
<x-layouts.public over-hero theme="c"
    description="Aucvy is het AI-commerceplatform voor zakelijke goederen. Upload een foto en het AI-team van Ivy herkent, schrijft en waardeert. Verkopen via veiling, afslag of koop direct, met geverifieerde verkopers en kosten vooraf - je betaalt alleen bij resultaat.">

    {{-- De kop. --}}
    <section class="page-hero d-home-hero" id="top" aria-labelledby="hero-title">
        <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>
        <div class="wrap">
            <div class="d-home-hero-text">
                <p class="d-home-kicker">Het AI-commerceplatform voor zakelijke goederen</p>
                <h1 id="hero-title">Upload.<br>Ivy regelt de rest.</h1>
                <p class="lead">Bedrijven maken een foto van wat ze willen verkopen. Ivy herkent het, Nova schrijft de advertentie en Atlas onderbouwt de waarde. Kopers zien wat ze betalen vóórdat ze bieden.</p>
                {{-- De vraag opent het paneel van Ivy; zonder JavaScript wordt het een zoekopdracht. --}}
                <form class="ask" action="{{ route('lots.index') }}" method="get" role="search" onsubmit="if (window.Alpine) { window.dispatchEvent(new CustomEvent('ivy-open', { detail: { text: this.q.value } })); return false; }">
                    <label>
                        <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
                        <span class="sr">Wat zoek je?</span>
                        <input id="q" name="q" type="text" placeholder="Wat zoek je? Bijvoorbeeld: een koelcel in Utrecht" autocomplete="off">
                    </label>
                    <button class="btn btn-primary" type="submit">Vraag Ivy</button>
                </form>
                <div class="d-hero-trust">
                    @foreach (['Gratis plaatsen', 'Geen opgeld voor kopers', 'Alleen betalen bij resultaat', 'Geverifieerde verkopers'] as $point)
                        <span><i aria-hidden="true"><x-v4.icon name="check" :size="10" stroke="3.4" /></i><b>{{ $point }}</b></span>
                    @endforeach
                </div>
            </div>
            <img class="d-home-hero-photo" src="{{ asset('images/v4/footer-gavel.jpg') }}" alt="" width="408" height="685">
        </div>
    </section>

    {{-- Het witte vel over de kop: de categorieën en de handel van dit moment. --}}
    <div class="d-home-top">
        <div class="d-home-sheet">
            <section aria-labelledby="cats-title">
                <h2 id="cats-title">Waar ben je naar op zoek?</h2>
                <div class="d-home-cats">
                    @foreach ($categories as $category)
                        @php
                            $photo = 'images/design/cat-'.$category->slug.'.jpg';
                            $total = (int) ($categoryTotals[$category->id] ?? 0);
                        @endphp
                        <a href="{{ route('lots.category', $category) }}">
                            @if (file_exists(public_path($photo)))
                                <img src="{{ asset($photo) }}" alt="" loading="lazy" width="560" height="350">
                            @else
                                <span class="empty" aria-hidden="true"><x-v4.icon :name="$category->slug === 'boten-recreatie' ? 'sailboat' : 'box'" :size="36" stroke="1.8" /></span>
                            @endif
                            <span class="row"><strong>{{ $category->name }}</strong><span>{{ $total }} {{ $total === 1 ? 'kavel' : 'kavels' }}</span></span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="d-home-live" id="live" aria-labelledby="live-title">
                <span class="d-home-label">Nu live</span>
                <h2 id="live-title">De handel van dit moment</h2>
                <p>Kavels van geverifieerde verkopers, met de waarde-indicatie van Atlas erbij.</p>
                <div class="lots" aria-live="polite">
                    @forelse ($featured as $lot)
                        <x-lot.card :lot="$lot" />
                    @empty
                        <p class="d-home-none">Er loopt op dit moment niets. Zodra er een veiling start, staat hij hier.</p>
                    @endforelse
                </div>
                <a class="d-btn d-btn-secondary d-btn-sq" href="{{ route('lots.index') }}">Alle veilingen bekijken</a>
            </section>
        </div>
    </div>

    <div class="wrap page-body d-home-body">

        {{-- Probeer het: voorbeeldobjecten optellen. --}}
        <section class="d-home-try d-bleed" aria-labelledby="try-title" x-data="valueTry(@js(collect($examples)->map(fn (array $item): array => ['id' => $item['id'], 'low' => $item['low'], 'high' => $item['high']])->all()), @js($picked))">
            <span class="d-pill is-dark">Probeer het</span>
            <h2 id="try-title">Wat kan jouw voorraad <span class="g">opleveren?</span></h2>
            <p class="d-home-try-lead">Tik aan wat er bij jou staat, van één machine tot een hele partij, en zie de waarde-indicatie.</p>
            <div class="d-home-try-grid">
                <div class="d-home-try-items">
                    @foreach ($examples as $item)
                        <button type="button" @class(['is-on' => in_array($item['id'], $picked, true)])
                            :class="{ 'is-on': has('{{ $item['id'] }}') }" :aria-pressed="has('{{ $item['id'] }}')"
                            aria-pressed="{{ in_array($item['id'], $picked, true) ? 'true' : 'false' }}"
                            @click="toggle('{{ $item['id'] }}')">
                            <img src="{{ asset('images/design/'.$item['image']) }}" alt="" loading="lazy" width="420" height="300">
                            <i aria-hidden="true"><x-v4.icon name="check" :size="13" stroke="3.2" class="yes" /><x-v4.icon name="plus" :size="13" stroke="3.2" class="no" /></i>
                            <span class="text"><strong>{{ $item['name'] }}</strong><span>{{ $euro($item['low']) }} – {{ $euro($item['high']) }}</span></span>
                        </button>
                    @endforeach
                </div>
                <aside class="d-home-try-sum" aria-live="polite">
                    <span x-text="count">{{ count($picked) }} objecten geselecteerd</span>
                    <strong x-text="total">{{ $euro((int) $pickedItems->sum('low')) }} – {{ $euro((int) $pickedItems->sum('high')) }}</strong>
                    <p>Voorbeeldbedragen, geen belofte. Bij jouw kavel rekent Atlas met jouw foto's en vergelijkbare verkopen, en de markt bepaalt de eindprijs.</p>
                    <a class="d-btn d-btn-sq" href="{{ route('sell') }}">Laat Ivy het echt bekijken</a>
                </aside>
            </div>
        </section>

        {{-- Jij kiest: de drie verkoopvormen op één voorbeeldkavel. --}}
        <section class="d-home-forms" id="vormen" aria-labelledby="vormen-title" x-data="saleFormDemo">
            <div>
                <span class="d-pill">Jij kiest</span>
                <h2 id="vormen-title">Drie manieren om te verkopen.</h2>
                <p class="d-sec-lead" x-text="current.text">Afslag past als je een datum hebt. De prijs daalt in stappen tot iemand afmijnt, en jij bepaalt de bodem. Probeer het maar.</p>
                <div class="d-home-tabs" role="group" aria-label="Verkoopvorm">
                    @foreach (['afslag' => 'Afslag', 'veiling' => 'Veiling', 'direct' => 'Koop direct'] as $form => $label)
                        <button type="button" @class(['is-on' => $form === 'afslag']) :class="{ 'is-on': form === '{{ $form }}' }"
                            :aria-pressed="form === '{{ $form }}'" @click="pick('{{ $form }}')">{{ $label }}</button>
                    @endforeach
                </div>
                <p class="d-hint" style="margin-top:18px">Plaatsen is gratis. De succesfee staat vooraf per kavel in de kostenopbouw, zie <a href="{{ route('pricing') }}">de prijzen</a>.</p>
            </div>
            <div class="d-home-demo" aria-live="polite">
                <div class="pic">
                    <img src="{{ asset('images/design/cat-horeca-keuken.jpg') }}" alt="" loading="lazy" width="720" height="360">
                    <span class="badge" x-text="current.badge">Afslag</span>
                    <span class="stamp" x-show="done" x-cloak x-text="current.stamp"></span>
                </div>
                <div class="body">
                    <span class="what">Voorbeeldkavel: combi-oven</span>
                    <div class="price"><span x-text="label">Prijs nu</span><strong x-text="price">€ 3.800</strong></div>
                    <div class="bar" aria-hidden="true"><i style="width:100%" :style="{ width: bar }"></i></div>
                    <span class="note" x-text="note">Daalt € 100 per stap · bodem € 2.200</span>
                    <button type="button" class="d-btn d-btn-sq" @click="act()" x-text="button">Mijn af</button>
                </div>
            </div>
        </section>

        {{-- Zo gaat het: de route in vijf stappen. --}}
        <section class="d-stepsband d-bleed" id="reis" aria-labelledby="reis-title">
            <div class="d-stepsband-text">
                <span class="d-pill is-dark">Zo gaat het</span>
                <h2 id="reis-title">Jij een foto. <span class="g">Ivy de rest.</span></h2>
                <video class="d-home-clip" controls loop muted playsinline preload="none" poster="{{ asset('videos/verhaal/verkoop-2.jpg') }}" aria-label="Ivy herkent een heftruck op een foto">
                    <source src="{{ asset('videos/verhaal/verkoop-2.mp4') }}" type="video/mp4">
                </video>
                <div class="d-cta-actions">
                    <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
                    <a class="btn btn-outline-white" href="{{ route('selling.guide') }}">Bekijk de verkooproute</a>
                </div>
            </div>
            <ol class="d-numlist">
                @foreach ($steps as $index => [$title, $text])
                    <li><b>{{ sprintf('%02d', $index + 1) }}</b><div><strong>{{ $title }}</strong><span>{{ $text }}</span></div></li>
                @endforeach
            </ol>
        </section>

        {{-- Je team. --}}
        <section class="d-sec" id="zakelijk" aria-labelledby="zakelijk-title">
            <span class="d-pill">Je team</span>
            <h2 id="zakelijk-title">Een verkoopteam dat nooit slaapt.</h2>
            <p class="d-sec-lead">Voor bedrijven met een doorlopende stroom aan inventaris, overtollig materieel of een liquidatie. Ivy coördineert en jij houdt de regie.</p>
            <ul class="d-teamcards">
                @foreach ($team as [$slug, $name, $role])
                    <li>
                        <img src="{{ asset('images/agents/'.$slug.'.jpg') }}" alt="{{ $name }}, AI-specialist" loading="lazy" width="48" height="48">
                        <div><strong>{{ $name }}</strong><span>{{ $role }}</span></div>
                    </li>
                @endforeach
            </ul>
            <div class="d-home-actions">
                <a class="btn btn-dark" href="{{ route('business') }}">Zakelijk verkopen</a>
                <a class="btn btn-ghost" href="{{ route('how-it-works') }}">Hoe het werkt</a>
            </div>
        </section>

        {{-- Eerlijk vergeleken. --}}
        <section class="d-sec" aria-labelledby="compare-title">
            <span class="d-pill">Eerlijk vergeleken</span>
            <h2 id="compare-title" style="max-width:none">Opkoper, zelf adverteren of veilen?</h2>
            <x-marketing.compare />
        </section>

        {{-- Afspraken in getallen. --}}
        <section class="d-home-facts d-bleed" aria-labelledby="facts-title">
            <h2 id="facts-title">Afspraken met <span class="g">een getal erachter.</span></h2>
            <div class="d-facts-grid">
                <div><b>€ 0</b><span>opgeld voor de koper, en gratis plaatsen voor jou</span></div>
                <div><b>{{ $betalen }} u</b><span>om te betalen na de hamerslag</span></div>
                <div><b>{{ $melden }} u</b><span>meldtermijn voor de koper na levering</span></div>
                <div><b>{{ $reageren }} u</b><span>voor jouw reactie als er iets gemeld wordt</span></div>
            </div>
        </section>

        {{-- Zekerheid. --}}
        <section class="d-sec d-band-grey d-bleed" id="zekerheid" aria-labelledby="zeker-title">
            <span class="d-pill">Zekerheid</span>
            <h2 id="zeker-title" style="max-width:none">Wat er geregeld is. <span class="soft">En wat nog niet.</span></h2>
            <div class="d-cards3">
                @foreach ($checks as [$title, $text])
                    <div><i aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></i><h3>{{ $title }}</h3><p>{{ $text }}</p></div>
                @endforeach
            </div>
            {{-- Eerlijk over wat er nog niet is - geen escrow-claim (hfst. 9/18). --}}
            <div class="d-home-wip">
                <p><strong>Waar we nog aan werken.</strong> Derdengelden-afwikkeling, technische keuring en verzekerd transport. Dat staat hier pas als het er echt is, niet eerder.</p>
                <a href="{{ route('assurance') }}">Lees hoe zekerheid werkt</a>
            </div>
        </section>

        {{-- Voor kopers. --}}
        <section class="d-sec d-home-buyers" id="kopen" aria-labelledby="buyers-title">
            <div class="pic">
                <img src="{{ asset('images/design/vb-designkeuken.jpg') }}" alt="" loading="lazy" width="720" height="408">
                <div class="card">
                    <span>Voorbeeld · huidig bod · 9 bieders</span>
                    <strong>€ 12.850</strong>
                    <em>14% onder waarde-indicatie</em>
                </div>
            </div>
            <div>
                <span class="d-pill">Voor kopers</span>
                <h2 id="buyers-title">Slim bieden. Weten waar je aan toe bent.</h2>
                <ul class="d-home-points">
                    @foreach ($buyerPoints as [$title, $text])
                        <li><strong>{{ $title }}</strong><span>{{ $text }}</span></li>
                    @endforeach
                </ul>
                <div class="d-home-actions">
                    <a class="btn btn-primary" href="{{ route('lots.index') }}">Ontdek de veilingen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
                </div>
            </div>
        </section>

        {{-- Vragen. --}}
        <section class="d-sec d-band-grey d-bleed" id="vragen" aria-labelledby="faq-title">
            <span class="d-pill">Vragen</span>
            <h2 id="faq-title" style="max-width:none">Vragen die we vaak krijgen</h2>
            <div class="d-home-faq">
                @foreach ($questions as $group => $items)
                    <div>
                        <h3 class="d-faq-label">{{ $group }}</h3>
                        @foreach ($items as [$question, $answer])
                            <details class="d-faq-item">
                                <summary>{{ $question }}<i aria-hidden="true"><x-v4.icon name="plus" :size="13" stroke="3" /></i></summary>
                                <p>{{ $answer }}</p>
                            </details>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <div class="d-home-actions">
                <a class="btn btn-primary" href="{{ route('faq') }}">Alle vragen <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            </div>
        </section>

        <x-marketing.cta secondary="Ontdek de veilingen" :secondary-url="route('lots.index')" />
    </div>

    <div class="sticky-cta" role="group" aria-label="Kopen of verkopen">
        <a class="btn btn-ghost" href="#live">Kopen</a>
        <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen</a>
    </div>

</x-layouts.public>
