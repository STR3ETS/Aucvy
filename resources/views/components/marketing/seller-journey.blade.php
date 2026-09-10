{{--
    De geanimeerde verkopersreis uit het ontwerp: zes klikbare stappen met
    een meespelende scène ernaast. Afspelen, pauzeren en de voortgangsbalkjes
    komen uit home.js (sectie VERKOPERSREIS); dat script draait op elke
    pagina en haakt aan op #steps, #stage, #reisToggle en #reis.

    Gebruikt op home en op de verkooproute - één bron, twee plekken.
--}}
<div class="reis-grid">
  <div class="rv">
    <ol class="steps" id="steps">
      <li class="on"><span class="n">1</span><h3>Upload</h3><p>Een paar foto's vanaf je telefoon zijn genoeg. Documenten kunnen later.</p><span class="bar"></span></li>
      <li><span class="n">2</span><h3><img src="{{ asset('images/agents/ivy.jpg') }}" alt="">Ivy herkent</h3><p>Merk, model, bouwjaar en conditie - met een zekerheidsscore, zodat je weet wat je moet nakijken.</p><span class="bar"></span></li>
      <li><span class="n">3</span><h3><img src="{{ asset('images/agents/nova.jpg') }}" alt="">Advertentie</h3><p>Titel, omschrijving en specificaties staan klaar - geschreven door Nova. Jij past aan of keurt goed.</p><span class="bar"></span></li>
      <li><span class="n">4</span><h3><img src="{{ asset('images/agents/atlas.jpg') }}" alt="">Waarde-indicatie</h3><p>Een range van Atlas op basis van vergelijkbare verkopen, met de bron erbij.</p><span class="bar"></span></li>
      <li><span class="n">5</span><h3><img src="{{ asset('images/agents/finn.jpg') }}" alt="">Kies de verkoopvorm</h3><p>Veiling, afslag of koop direct. Finn adviseert, jij beslist.</p><span class="bar"></span></li>
      <li><span class="n">6</span><h3><img src="{{ asset('images/agents/flow.jpg') }}" alt="">Verkocht &amp; uitbetaald</h3><p>Betaling, transport en uitbetaling handelt Flow voor je af.</p><span class="bar"></span></li>
    </ol>
    <div class="reis-ctrl">
      <button class="btn btn-ghost" id="reisToggle" aria-pressed="false">
        <svg id="icoPause" viewBox="0 0 16 16" fill="currentColor"><rect x="3" y="2" width="3.5" height="12"/><rect x="9.5" y="2" width="3.5" height="12"/></svg>
        <svg id="icoPlay" viewBox="0 0 16 16" fill="currentColor" style="display:none"><path d="M4 2l10 6-10 6z"/></svg>
        <span id="reisLabel">Pauze</span>
      </button>
      <span>Speelt automatisch af</span>
    </div>
  </div>

  <div class="stage rv" id="stage" aria-hidden="true">
    <div class="grid"></div>
    <div class="scene on">
      <div class="art"><div class="frame">Sleep foto's hierheen</div><span class="tag b" style="left:8%;top:10%">IMG_4821.jpg</span><span class="tag b" style="right:8%;top:16%">IMG_4822.jpg</span><span class="tag b" style="left:14%;bottom:12%">IMG_4823.jpg</span></div>
      <div class="cap"><small>Stap 1</small>Drie foto's, gemaakt in de werkplaats.</div>
    </div>
    <div class="scene">
      <div class="art"><span class="tag" style="left:8%;top:12%">Haas VF-2</span><span class="tag" style="left:8%;top:28%">CNC-freesbank</span><span class="tag" style="left:8%;top:44%">Bouwjaar 2019</span><span class="tag c" style="right:8%;top:12%">Conditie: gebruikt-goed</span><div class="conf"><i data-conf="82"></i></div></div>
      <div class="cap"><small>Stap 2</small>Ivy herkent met 82% zekerheid. Bouwjaar nakijken.</div>
    </div>
    <div class="scene">
      <div class="art"><span class="line s" style="left:8%;top:14%;width:60%"></span><span class="line" style="left:8%;top:26%;width:80%"></span><span class="line" style="left:8%;top:34%;width:72%"></span><span class="line" style="left:8%;top:42%;width:78%"></span><span class="line" style="left:8%;top:50%;width:40%"></span><span class="tag g" style="right:8%;bottom:10%">Klaar om te keuren</span></div>
      <div class="cap"><small>Stap 3</small>Advertentie geschreven. Eerlijk over de twee krassen op de tafel.</div>
    </div>
    <div class="scene">
      <div class="art"><div class="big" style="left:8%;top:22%">€ 15.000 – € 19.500</div><span class="tag b" style="left:8%;top:52%">Zekerheid: midden</span><span class="tag" style="left:8%;top:66%">Bron: 14 vergelijkbare verkopen</span></div>
      <div class="cap"><small>Stap 4</small>Een range, geen belofte. Met de bron erbij.</div>
    </div>
    <div class="scene">
      <div class="art"><div class="card" style="left:8%;top:14%;width:40%"><b>Veiling</b><span>7 dagen</span></div><div class="card" style="right:8%;top:14%;width:40%;border-color:#2E5CC6"><b>Afslag</b><span>Daalt elke 20s</span></div><div class="card" style="left:8%;bottom:14%;width:40%"><b>Koop direct</b><span>Vaste prijs</span></div><span class="tag b" style="right:8%;bottom:16%">Ivy adviseert: afslag</span></div>
      <div class="cap"><small>Stap 5</small>Snel geld nodig? Dan is afslag de verstandige keuze.</div>
    </div>
    <div class="scene">
      <div class="art"><div class="big" style="left:8%;top:22%">€ 13.750</div><span class="tag g" style="left:8%;top:52%">Verkocht</span><span class="tag" style="left:8%;top:66%;background:#1c2540">Uitbetaling na houdtermijn</span><span class="tag c" style="right:8%;top:52%">Transport ingepland</span></div>
      <div class="cap"><small>Stap 6</small>Afgemijnd op € 13.750. Uitbetaald na de houdtermijn.</div>
    </div>
  </div>
</div>
