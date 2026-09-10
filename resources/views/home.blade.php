{{--
    De home uit aucvy-home.html, 1-op-1 overgenomen.

    Dynamisch zijn alleen: de categoriechips in de hero-voet, de vier live
    kavelkaarten, en de formulier-/linkdoelen. Alle overige tekst en markup
    komt letterlijk uit het ontwerp - wijzigingen dáár horen eerst in het
    ontwerpbestand, dan hier.
--}}
<x-layouts.public over-hero
    description="Aucvy is het AI-commerceplatform voor zakelijke goederen. Upload een foto en het AI-team van Ivy herkent, schrijft en waardeert. Verkopen via veiling, afslag of koop direct, met geverifieerde verkopers en kosten vooraf - je betaalt alleen bij resultaat.">

<div class="home">

<!-- ================= HERO ================= -->
<section class="hero" id="top" aria-labelledby="hero-title">
  <canvas id="dots" aria-hidden="true"></canvas>
  <div class="wrap">
    <div class="hero-inner">
      <p class="kicker" data-hero>Het AI-commerceplatform voor zakelijke goederen</p>
      <h1 id="hero-title">
        <span class="w"><span>Upload.</span></span>
        <span class="w"><span>Ivy</span></span>
        <span class="w"><span>regelt</span></span>
        <span class="w"><span>de</span></span>
        <span class="w"><span>rest.</span></span>
      </h1>
      <p class="lead" data-hero>Maak een foto van wat je wilt verkopen. Ivy herkent het, Nova schrijft de advertentie en Atlas onderbouwt de waarde. Jij kiest de verkoopvorm en keurt goed.</p>
      <form class="ask" data-hero action="{{ route('lots.index') }}" method="get" role="search">
        <label>
          {{-- TOEVOEGING: Ivy's gezicht in plaats van het sterretje. --}}
          <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
          <span class="sr">Wat wil je vandaag verhandelen?</span>
          <input id="q" name="q" type="text" placeholder="Wat wil je vandaag verhandelen?" autocomplete="off">
        </label>
        <button class="btn btn-primary" type="submit">Vraag aan Ivy
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8h10M9 4l4 4-4 4"/></svg>
        </button>
      </form>
      <ul class="trust" data-hero>
        <li>Gratis plaatsen</li>
        <li>Geen opgeld voor kopers</li>
        <li>Alleen betalen bij resultaat</li>
        <li>Geverifieerde verkopers</li>
      </ul>
    </div>
  </div>
  <div class="hero-foot" data-hero>
    <div class="wrap">
      <nav class="cats" aria-label="Categorieën">
        @foreach ($categories as $category)
          <a href="{{ route('lots.category', $category) }}">{{ $category->name }}</a>
        @endforeach
      </nav>
      <div class="scroll-hint"><i></i>Live op Aucvy</div>
    </div>
  </div>
</section>

<!-- ================= LIVE ================= -->
<section class="live" id="live" aria-labelledby="live-title">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <span class="live-dot"><i></i>Nu live</span>
        <h2 id="live-title">De handel van dit moment</h2>
        <p>Kavels van geverifieerde verkopers, met de waarde-indicatie van Atlas erbij. Prijzen, bieders en kijkers lopen live mee.</p>
      </div>
      <a class="more" href="{{ route('lots.index') }}">Alles bekijken <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8h10M9 4l4 4-4 4"/></svg></a>
    </div>

    <div class="lots" aria-live="polite">
      @forelse ($featured as $lot)
        <x-lot.card :lot="$lot" class="rv" />
      @empty
        <p style="grid-column:1/-1;color:var(--muted)">Er loopt op dit moment niets. Zodra er een veiling start, staat hij hier.</p>
      @endforelse
    </div>
  </div>
</section>

<!-- ================= VERKOPERSREIS ================= -->
<section class="reis" id="reis" aria-labelledby="reis-title">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <h2 id="reis-title">Van foto tot uitbetaling in zes stappen</h2>
        <p>Jij levert de foto en het akkoord. Ivy en haar team doen het werk ertussen.</p>
      </div>
    </div>
    {{-- TOEVOEGING: gedeeld met de verkooproutepagina. --}}
    <x-marketing.seller-journey />
  </div>
</section>

<!-- ================= VERKOOPVORMEN ================= -->
<section class="vormen" id="vormen" aria-labelledby="vormen-title">
  <div class="wrap">
    <div class="sec-head rv">
      <div>
        <h2 id="vormen-title">Drie manieren om te verkopen</h2>
        <p>Elke kavel krijgt de vorm die bij het doel past. Je betaalt pas als er verkocht is.</p>
      </div>
    </div>
    <div class="forms">
      <article class="form rv">
        <span class="fee"><b>7,5%</b>bij verkoop</span>
        <div class="glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 18h16M6 18V9l6-4 6 4v9"/><path d="M12 5v13"/></svg></div>
        <h3>Veiling</h3>
        <p>Oplopend bieden met proxy-bieden en een verlengregel in de slotfase. De markt bepaalt de prijs.</p>
        <ul><li>Automatisch meebieden tot je maximum</li><li>Verlenging bij een bod in de laatste minuten</li><li>Geen opgeld voor de koper</li></ul>
      </article>
      <article class="form rv">
        <span class="fee"><b>3,5%</b>bij verkoop</span>
        <div class="glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6l7 7 4-4 5 5"/><path d="M20 10v4h-4"/></svg></div>
        <h3>Afslag</h3>
        <p>De prijs start hoog en daalt in vaste stappen. Wie als eerste afmijnt, koopt. Snel en transparant.</p>
        <ul><li>Daalt elke 20 seconden</li><li>Bodemprijs stel jij in</li><li>Ideaal bij een deadline</li></ul>
      </article>
      <article class="form rv">
        <span class="fee"><b>2,5%</b>bij verkoop</span>
        <div class="glyph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 7h14l-1.5 9h-11z"/><path d="M9 7V5a3 3 0 016 0v2"/></svg></div>
        <h3>Koop direct</h3>
        <p>Een vaste prijs, meteen te kopen. Voor kavels waar geen veiling nodig is.</p>
        <ul><li>Vaste prijs, geen wachttijd</li><li>Reserveren mogelijk met betaling</li><li>Transport direct te boeken</li></ul>
      </article>
    </div>
    <p class="fee-note rv">Plaatsen is gratis. Het tarief wordt alleen ingehouden op een geslaagde verkoop en staat vooraf per kavel in de kostenopbouw.</p>
  </div>
</section>

<!-- ================= ZEKERHEID ================= -->
<section class="zeker" id="zekerheid" aria-labelledby="zeker-title">
  <div class="wrap zeker-grid">
    <div class="rv">
      <h2 id="zeker-title">Wat er geregeld is</h2>
      <p class="intro">Handel tussen bedrijven vraagt om duidelijke afspraken. Dit is wat Aucvy vandaag voor elke transactie regelt - en waar we nog aan werken.</p>
      <a class="btn btn-dark" href="{{ route('assurance') }}">Lees hoe zekerheid werkt</a>
    </div>
    <div class="checks rv">
      <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Geverifieerde verkopers</h3><p>KVK-controle en identiteitscheck voordat een kavel live kan.</p></div>
      <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Kosten vooraf zichtbaar</h3><p>Bod, transport en btw-regeling staan bij elkaar vóórdat je biedt.</p></div>
      <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Houdtermijn op uitbetaling</h3><p>De verkoper ontvangt het geld nadat de koper heeft kunnen controleren.</p></div>
      <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Geschilroute met bemiddeling</h3><p>Niet zoals beschreven? Dan bemiddelt Aucvy tussen koper en verkoper.</p></div>
      <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Bezichtiging waar mogelijk</h3><p>Kijkdagen staan bij de kavel. Plan direct een moment in.</p></div>
      <div><h3><svg viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9.5l4 4 8-8"/></svg>Reviews en de Trust Index</h3><p>Koper en verkoper beoordelen elkaar. Samen met levering, documenten en geschilhistorie bouwt dat de Trust Index op het verkopersprofiel.</p></div>
      <div class="wip"><h3>Waar we nog aan werken</h3><p>Escrow via een gereguleerde betaaldienst en een onafhankelijke technische keuring. Zodra dat staat, staat het hier.</p></div>
    </div>
  </div>
</section>

<!-- ================= ZAKELIJK ================= -->
<section class="zakelijk" id="zakelijk" aria-labelledby="zakelijk-title">
  {{-- TOEVOEGING: het ademende stippenveld, wit op het donkere vlak. --}}
  <canvas class="dots" x-data="dotField({ strength: 0.6 })" aria-hidden="true"></canvas>
  <div class="wrap">
    <div class="rv">
      <h2 id="zakelijk-title">Een verkoopteam dat nooit slaapt.</h2>
      <p>Voor bedrijven met een doorlopende stroom aan inventaris, overtollig materieel of een liquidatie. Ivy coördineert zes specialisten en jij houdt de regie.</p>
      <div class="actions">
        <a class="btn btn-primary" href="{{ route('lots.index') }}">Zakelijk verkopen</a>
        <a class="btn btn-outline-white" href="{{ route('lots.index') }}">Plan een gesprek</a>
      </div>
    </div>
    {{-- TOEVOEGING: de echte gezichten van het team, ring in de teamkleur. --}}
    <div class="team rv">
      <div><img src="{{ asset('images/agents/ivy.jpg') }}" alt="" loading="lazy" style="box-shadow:0 0 0 2px var(--color-aj-ivy)"><div><b>Ivy</b><span>Coördineert, herkent en adviseert.</span></div></div>
      <div><img src="{{ asset('images/agents/atlas.jpg') }}" alt="" loading="lazy" style="box-shadow:0 0 0 2px var(--color-aj-atlas)"><div><b>Atlas</b><span>Waarde-indicaties en marktdata.</span></div></div>
      <div><img src="{{ asset('images/agents/nova.jpg') }}" alt="" loading="lazy" style="box-shadow:0 0 0 2px var(--color-aj-nova)"><div><b>Nova</b><span>Advertentietekst en promotie.</span></div></div>
      <div><img src="{{ asset('images/agents/finn.jpg') }}" alt="" loading="lazy" style="box-shadow:0 0 0 2px var(--color-aj-finn)"><div><b>Finn</b><span>Prijsadvies en verkoopvorm.</span></div></div>
      <div><img src="{{ asset('images/agents/flow.jpg') }}" alt="" loading="lazy" style="box-shadow:0 0 0 2px var(--color-aj-flow)"><div><b>Flow</b><span>Transport en planning.</span></div></div>
      <div><img src="{{ asset('images/agents/lex.jpg') }}" alt="" loading="lazy" style="box-shadow:0 0 0 2px var(--color-aj-lex)"><div><b>Lex</b><span>Documenten en voorwaarden.</span></div></div>
    </div>
  </div>
</section>

</div>

<div class="sticky-cta" role="group" aria-label="Kopen of verkopen">
  <a class="btn btn-ghost" href="#live">Kopen</a>
  <a class="btn btn-primary" href="{{ route('sell') }}">Kavel plaatsen</a>
</div>

</x-layouts.public>
