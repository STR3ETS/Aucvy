@use('App\Enums\CompanyPlan')

{{--
    Variant B van de A/B-test op de home: het ontwerp uit preview v4
    (famous-cobbler-0c4971.netlify.app/preview), nagebouwd in Blade.

    Dynamisch zijn: de vier live kavelkaarten, de categoriechips, de tarieven
    (CompanyPlan) en alle link- en formulierdoelen. De overige tekst komt uit
    het ontwerp, zonder de claims die het platform vandaag niet waarmaakt.
--}}
<x-layouts.v4 :variant="$variant" :demo="$demo"
    description="Aucvy is het AI-commerceplatform voor zakelijke goederen. Upload een foto en het AI-team van Ivy herkent, schrijft en waardeert. Verkopen via veiling, afslag of koop direct, met geverifieerde verkopers en kosten vooraf - je betaalt alleen bij resultaat.">

{{-- ================= HERO ================= --}}
<header class="v4-dark" aria-labelledby="hero-title">
    <div class="v4-mark v4-anim-float" aria-hidden="true" style="right:-90px;top:-30px;opacity:.2;filter:drop-shadow(0 0 80px rgba(94,110,255,.55)) saturate(1.2)"><x-v4.logo :size="620" /></div>
    <div class="v4-mark v4-anim-float-slow" aria-hidden="true" style="left:-140px;bottom:-180px;opacity:.1;filter:blur(1px)"><x-v4.logo :size="420" /></div>
    <div class="v4-glow v4-glow--grad v4-anim-drift-a" aria-hidden="true" style="width:520px;height:520px;opacity:.16;top:-160px;right:4%"></div>
    <div class="v4-glow v4-glow--violet v4-anim-drift-b" aria-hidden="true" style="width:420px;height:420px;opacity:.14;bottom:-120px;left:-6%"></div>
    <div class="v4-dotgrid" aria-hidden="true"></div>
    <x-v4.particles :count="14" />

    <section style="margin:0 auto;max-width:1220px;padding:72px 24px 84px;position:relative">
        <div class="v4-hero-grid">
            <div>
                <span class="v4-pill"><x-v4.icon name="sparkles" :size="13" stroke="2.4" /> Het AI-commerceplatform</span>
                <h1 id="hero-title" style="margin:20px 0 0;font-size:clamp(40px,5.6vw,64px);font-weight:800;letter-spacing:-.03em;line-height:1.02">Upload.<br><span class="v4-grad-text">Ivy regelt de rest.</span></h1>
                <p style="margin:18px 0 0;font-size:17px;color:rgba(255,255,255,.68);line-height:1.6;max-width:46ch">Van foto's naar een professionele veiling, met een uitbetaling na de houdtermijn.</p>
                <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:28px">
                    <x-v4.button :href="route('sell')" size="lg" arrow>Plaats gratis een kavel</x-v4.button>
                    <x-v4.button :href="route('lots.index')" variant="ghost" size="lg">Ontdek veilingen</x-v4.button>
                </div>
                <div style="display:flex;align-items:center;gap:12px;margin-top:26px">
                    <div style="display:flex">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100&auto=format&fit=crop" alt="" width="30" height="30" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid #1a2440">
                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=100&auto=format&fit=crop" alt="" width="30" height="30" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid #1a2440;margin-left:-9px">
                        <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100&auto=format&fit=crop" alt="" width="30" height="30" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid #1a2440;margin-left:-9px">
                    </div>
                    <span style="font-size:12.5px;font-weight:700;color:rgba(255,255,255,.72)">Voor kopers en verkopers uit heel Nederland</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:18px;margin-top:18px">
                    <span class="v4-trust-item"><x-v4.check :size="16" />Geen opgeld voor kopers</span>
                    <span class="v4-trust-item"><x-v4.check :size="16" />Gratis plaatsen</span>
                    <span class="v4-trust-item"><x-v4.check :size="16" />Alleen betalen bij resultaat</span>
                </div>
            </div>

            <x-v4.hero-demo />
        </div>
    </section>
</header>

{{-- ================= ROUTES ================= --}}
<section id="routes" class="v4-section" style="padding:76px 24px 88px" aria-label="Verkopen of kopen">
    <div class="v4-two">
        <div class="v4-card v4-route v4-reveal">
            <div>
                <h2>Ik wil verkopen</h2>
                <p>Maak binnen minuten een professionele kavel.</p>
            </div>
            <ul>
                <li><x-v4.check />Ivy maakt advertentie, waardering en Engelse vertaling</li>
                <li><x-v4.check />Gratis plaatsen, succesfee alleen bij verkoop</li>
                <li><x-v4.check />Van bod tot uitbetaling begeleid</li>
            </ul>
            <div style="margin-top:auto"><x-v4.button :href="route('sell')" arrow>Plaats mijn kavel</x-v4.button></div>
        </div>
        <div class="v4-card v4-route v4-reveal" style="--d:.1s">
            <div>
                <h2>Ik wil kopen</h2>
                <p>Vertel Ivy wat je zoekt. Zij zoekt mee.</p>
            </div>
            <ul>
                <li><x-v4.check />Zoeken in gewone taal, matches met uitleg</li>
                <li><x-v4.check />Waarde-indicatie en aandachtspunten per kavel</li>
                <li><x-v4.check />Biedadvies en optioneel automatisch meestappen</li>
            </ul>
            <div style="margin-top:auto"><x-v4.button :href="route('lots.index')" variant="secondary">Zoek met Ivy</x-v4.button></div>
        </div>
    </div>
</section>

{{-- ================= ZOEKEN ================= --}}
<section id="zoeken" style="position:relative;margin-bottom:88px" aria-labelledby="zoeken-title"
         x-data="{ group: 'populair', groups: {
            populair: ['20 bureaustoelen onder € 4.000', 'Complete horeca-inventaris', 'Sloep onder € 30k', 'Winkelinventaris kleding'],
            zakelijk: ['Machinepark metaalbewerking', 'Partij magazijnstellingen', 'Bestelwagens uit lease', 'Inventaris van een bakkerij'],
            thuis: ['Designkeuken met apparatuur', 'Herman Miller bureaustoel', 'Leica meetzoekercamera', 'Partij tuinmeubelen'],
         } }">
    <div class="v4-dark v4-dark--search">
        <div class="v4-mark v4-anim-float" aria-hidden="true" style="right:-80px;top:-40px;opacity:.17;filter:drop-shadow(0 0 70px rgba(94,110,255,.55))"><x-v4.logo :size="480" /></div>
        <div class="v4-glow v4-glow--grad v4-anim-drift-a" aria-hidden="true" style="width:440px;height:440px;opacity:.15;top:-140px;right:10%"></div>
        <div class="v4-glow v4-glow--violet v4-anim-drift-b" aria-hidden="true" style="width:340px;height:340px;opacity:.13;bottom:-120px;left:-4%"></div>
        <div class="v4-dotgrid" aria-hidden="true"></div>
        <x-v4.particles :count="10" />

        <div style="position:relative;margin:0 auto;max-width:1180px;padding:56px 24px 78px">
            <div class="v4-searchhero">
                <div>
                    <span class="v4-pill" style="backdrop-filter:none"><x-v4.icon name="sparkles" :size="13" stroke="2.4" /> Zoeken met Ivy</span>
                    <h2 id="zoeken-title" style="margin:18px 0 0;font-size:clamp(30px,4.2vw,46px);font-weight:800;letter-spacing:-.03em;line-height:1.06;max-width:16ch">Vertel wat je zoekt. <span class="v4-grad-text">Ivy doet de rest.</span></h2>
                    <p style="margin:14px 0 0;font-size:16px;color:rgba(255,255,255,.68);line-height:1.6;max-width:46ch">Geen filters aanvinken en geen trefwoorden gokken. Eén gewone zin is genoeg: zij vertaalt je vraag naar filters en zoekt.</p>
                    <form action="{{ route('lots.index') }}" method="get" role="search" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:26px;max-width:620px">
                        <div style="flex:1 1 0;min-width:280px;display:flex;align-items:center;gap:10px;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:rgba(255,255,255,.08);backdrop-filter:blur(12px);padding:0 20px;min-height:62px;box-shadow:0 18px 44px -20px rgba(0,0,0,.6)">
                            <x-v4.icon name="search" :size="18" stroke="2.4" style="flex-shrink:0;opacity:.8" />
                            <input name="q" placeholder="&quot;Ik zoek een complete designkeuken onder € 10.000.&quot;" aria-label="Vraag aan Ivy" autocomplete="off" style="flex:1 1 0;border:0;outline:none;background:transparent;font-size:15px;font-family:inherit;color:#fff;min-width:0">
                        </div>
                        <x-v4.button type="submit" size="lg" arrow>Zoeken</x-v4.button>
                    </form>
                    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:18px">
                        <span class="v4-trust-item v4-trust-item--bright"><x-v4.check :size="15" />Geen account nodig om te zoeken</span>
                        <span class="v4-trust-item v4-trust-item--bright"><x-v4.check :size="15" />Kopers betalen geen opgeld</span>
                        <span class="v4-trust-item v4-trust-item--bright"><x-v4.check :size="15" />Ivy meldt zich als er iets past</span>
                    </div>
                </div>
                <div class="v4-ivycol">
                    <div style="position:relative;width:260px">
                        <div class="v4-glow v4-glow--grad" aria-hidden="true" style="inset:10% 8% 14%;opacity:.3;filter:blur(46px)"></div>
                        <img src="{{ asset('images/v4/ivy-portrait.jpg') }}" alt="Ivy, jouw Online Veiling Assistent" loading="lazy" style="position:relative;width:100%;height:360px;object-fit:cover;object-position:center top;border-radius:26px;-webkit-mask-image:linear-gradient(#000 72%,transparent 99%);mask-image:linear-gradient(#000 72%,transparent 99%);filter:drop-shadow(0 24px 40px rgba(0,0,0,.45))">
                        <div style="position:absolute;left:6px;right:6px;bottom:0;border-radius:20px;padding:14px 16px;backdrop-filter:blur(18px);background:linear-gradient(160deg,rgba(255,255,255,.95),rgba(244,246,255,.88));border:1px solid rgba(255,255,255,.9);box-shadow:0 20px 44px -18px rgba(0,0,0,.45)">
                            <p style="margin:0;font-size:14.5px;font-weight:800;color:var(--aj-text);line-height:1.3">Hoi! Ik ben <span style="background:var(--aj-grad);-webkit-background-clip:text;background-clip:text;color:transparent">Ivy.</span></p>
                            <p style="margin:3px 0 0;font-size:12.5px;font-weight:600;color:var(--aj-muted);line-height:1.4">Waar ben je naar op zoek vandaag?</p>
                            <svg viewBox="0 0 120 14" aria-hidden="true" style="height:13px;width:104px;margin-top:8px"><path d="M2 7h6M12 7c2-5 4-5 6 0s4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0M112 7h6" fill="none" stroke="var(--aj-violet)" stroke-width="1.6" stroke-linecap="round" opacity="0.75"/></svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin:0 auto;max-width:1220px;padding:0 24px">
        <div class="v4-card v4-card--float" style="padding:22px 26px;margin-top:-34px;position:relative;z-index:2">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:14px">
                <span style="font-size:14.5px;font-weight:800">Waar anderen nu naar zoeken</span>
                <div role="tablist" aria-label="Suggestiegroepen" style="display:flex;gap:6px;flex-wrap:wrap">
                    <button type="button" role="tab" class="v4-chip v4-chip--sm" :class="{ 'v4-chip--on': group === 'populair' }" :aria-selected="group === 'populair'" @click="group = 'populair'">Populair deze week</button>
                    <button type="button" role="tab" class="v4-chip v4-chip--sm" :class="{ 'v4-chip--on': group === 'zakelijk' }" :aria-selected="group === 'zakelijk'" @click="group = 'zakelijk'">Zakelijk en partijen</button>
                    <button type="button" role="tab" class="v4-chip v4-chip--sm" :class="{ 'v4-chip--on': group === 'thuis' }" :aria-selected="group === 'thuis'" @click="group = 'thuis'">Voor thuis</button>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                <template x-for="query in groups[group]" :key="query">
                    <a class="v4-chip v4-chip--query" :href="'{{ route('lots.index') }}?q=' + encodeURIComponent(query)"><x-v4.icon name="search" :size="13" stroke="2.4" /> <span x-text="query"></span></a>
                </template>
            </div>
        </div>
    </div>
</section>

{{-- ================= LIVE ================= --}}
<section id="ontdek-live" class="v4-section" aria-labelledby="live-title">
    <div class="v4-head-row">
        <div class="v4-head v4-reveal">
            <span class="v4-kicker">Live op Aucvy</span>
            <h2 class="v4-h2" id="live-title">De handel van dit moment</h2>
            <p class="v4-sub">Echte kavels, echte timers, en Ivy's waarderange als indicatie bij elk bod.</p>
        </div>
        <a class="v4-more" href="{{ route('lots.index') }}">Alle veilingen <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px">
        <a class="v4-chip v4-chip--on" href="{{ route('lots.index') }}" aria-current="true">Nu live</a>
        <a class="v4-chip" href="{{ route('lots.index', ['toon' => 'nieuw']) }}">Nieuw</a>
        <a class="v4-chip" href="{{ route('lots.index', ['toon' => 'laatste-kans']) }}">Laatste kans</a>
        <a class="v4-chip" href="{{ route('lots.index', ['toon' => 'onder-marktwaarde']) }}">Onder marktwaarde</a>
    </div>
    <div class="v4-feed" aria-live="polite">
        @forelse ($featured as $index => $lot)
            <x-v4.lot-card :lot="$lot" class="v4-reveal" style="--d:{{ $index * 0.08 }}s" />
        @empty
            <p style="grid-column:1/-1;color:var(--aj-muted)">Er loopt op dit moment niets. Zodra er een veiling start, staat hij hier.</p>
        @endforelse
    </div>
</section>

{{-- ================= VERKOOPVORMEN ================= --}}
<section id="verkoopvormen" class="v4-section" aria-labelledby="vormen-title">
    <div class="v4-head v4-reveal">
        <span class="v4-kicker">Verkoopvormen</span>
        <h2 class="v4-h2" id="vormen-title">Bieden, afmijnen of gewoon direct kopen</h2>
        <p class="v4-sub">Niet elke kavel vraagt om dezelfde route. Daarom drie vormen, en bij elke staat vooraf precies wat je betaalt.</p>
    </div>
    <div class="v4-engine">
        <article class="v4-form v4-reveal">
            <span class="v4-glyph" style="background:var(--aj-blue)"><x-v4.icon name="gavel" :size="19" stroke="2.3" /></span>
            <h3>Veiling</h3>
            <p>De prijs loopt op. Het hoogste bod bij sluiting wint.</p>
            <div class="v4-form-points">
                <span><i class="ok">✓</i>Meeste marktwerking, vaak de hoogste opbrengst voor de verkoper.</span>
                <span><i class="warn">!</i>Je weet pas bij sluiting of je hem hebt.</span>
            </div>
            <a class="v4-link-btn" href="{{ route('selling.guide') }}" style="margin-top:14px;align-self:flex-start">Hoe werkt veiling? <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
        </article>
        <article class="v4-form v4-reveal" style="--d:.1s">
            <span class="v4-glyph" style="background:var(--aj-violet)"><x-v4.icon name="arrow-down" :size="19" stroke="2.3" /></span>
            <h3>Afslag</h3>
            <p>De prijs dáált. Wie als eerste toeslaat, heeft hem meteen.</p>
            <div class="v4-form-points">
                <span><i class="ok">✓</i>Snel duidelijk, en je weet exact wat je betaalt op het moment dat je klikt.</span>
                <span><i class="warn">!</i>Er is maar één winnaar en geen tweede kans: wie eerst klikt, heeft hem.</span>
            </div>
            <a class="v4-link-btn" href="{{ route('selling.guide') }}" style="margin-top:14px;align-self:flex-start">Hoe werkt afslag? <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
        </article>
        <article class="v4-form v4-reveal" style="--d:.2s">
            <span class="v4-glyph" style="background:var(--aj-success-text)"><x-v4.icon name="shopping-bag" :size="19" stroke="2.3" /></span>
            <h3>Koop direct</h3>
            <p>Vaste prijs. Nu kopen, klaar.</p>
            <div class="v4-form-points">
                <span><i class="ok">✓</i>Geen onzekerheid en geen wachttijd.</span>
                <span><i class="warn">!</i>Je betaalt de vaste prijs; via een veiling zou het lager (of hoger) kunnen uitvallen.</span>
            </div>
            <a class="v4-link-btn" href="{{ route('selling.guide') }}" style="margin-top:14px;align-self:flex-start">Hoe werkt koop direct? <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
        </article>
    </div>
</section>

{{-- ================= IVY ================= --}}
<x-v4.ivy-engine />

{{-- ================= CATEGORIEËN ================= --}}
<section id="ontdek-categorieen" class="v4-section" aria-labelledby="cat-title">
    <div class="v4-head-row">
        <div class="v4-head v4-reveal">
            <span class="v4-kicker">Categorieën</span>
            <h2 class="v4-h2" id="cat-title">Van losse kavel tot compleet bedrijf</h2>
        </div>
        <a class="v4-more" href="{{ route('lots.index') }}">Alle categorieën <x-v4.icon name="arrow-right" :size="14" stroke="2.4" /></a>
    </div>
    @php
        $tiles = [
            ['q' => 'keuken', 'photo' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?q=80&w=900&auto=format&fit=crop', 'lead' => 'Designer keukens.', 'rest' => 'Voor een fractie van de prijs.', 'points' => ['Topmerken', 'Zo goed als nieuw', 'Direct beschikbaar'], 'cta' => 'Bekijk keukens'],
            ['q' => 'bureaustoel', 'photo' => 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?q=80&w=900&auto=format&fit=crop', 'lead' => 'Bureaus en kantoor.', 'rest' => 'Sterk in kwaliteit, scherp in prijs.', 'points' => ['Ergonomisch', 'Duurzaam', 'Direct leverbaar'], 'cta' => 'Bekijk bureaus'],
            ['q' => 'boot', 'photo' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?q=80&w=900&auto=format&fit=crop', 'lead' => 'Boten en jachten.', 'rest' => 'Klaar voor nieuwe avonturen.', 'points' => ['Documenten en bezichtiging', 'Scherpe veilingen', 'Van sloep tot jacht'], 'cta' => 'Bekijk boten'],
        ];
    @endphp
    <div class="v4-adrow">
        @foreach ($tiles as $index => $tile)
            <a href="{{ route('lots.index', ['q' => $tile['q']]) }}" class="v4-ad v4-reveal" style="--d:{{ $index * 0.1 }}s">
                <div class="photo" aria-hidden="true" style="background-image:linear-gradient(#0c1326 0%,rgba(12,19,38,.25) 40%,rgba(12,19,38,.15)),url('{{ $tile['photo'] }}')"></div>
                <div class="brand"><x-v4.logo :size="22" />Aucvy</div>
                <h3><span>{{ $tile['lead'] }}</span> {{ $tile['rest'] }}</h3>
                <div class="points">
                    @foreach ($tile['points'] as $point)
                        <span><x-v4.check :size="15" outline />{{ $point }}</span>
                    @endforeach
                </div>
                <span class="cta" style="margin-top:auto">{{ $tile['cta'] }}<i aria-hidden="true"><x-v4.icon name="arrow-right" :size="13" stroke="2.6" /></i></span>
            </a>
        @endforeach
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:18px">
        @foreach ($categories as $category)
            <a class="v4-chip v4-chip--link" href="{{ route('lots.category', $category) }}">{{ $category->name }}</a>
        @endforeach
    </div>
</section>

{{-- ================= VERKOPERS EN KOPERS ================= --}}
@php
    // De stappen van de twee verhalen. Teksten uit het ontwerp; bedragen fictief.
    $sellerSteps = [
        ['icon' => 'upload', 'title' => 'Upload', 'text' => 'Foto\'s, video, documenten of een spraakbericht: wat je hebt is genoeg.', 'panel' => ['label' => 'Upload', 'rows' => [['l' => '12 foto\'s toegevoegd', 'done' => true], ['l' => 'Aankoopfactuur.pdf', 'done' => true], ['l' => 'Spraakbericht 0:42', 'done' => true]], 'footer' => 'Geen account nodig vóór de eerste analyse.']],
        ['icon' => 'camera', 'title' => 'Herkend', 'text' => 'Ivy herkent object, merk en conditie, met zekerheid per onderdeel.', 'panel' => ['label' => 'Herkenning', 'rows' => [['l' => 'Siemens apparatuur', 'v' => '98%', 'bar' => .98], ['l' => 'Conditie: zeer goed', 'v' => '86%', 'bar' => .86], ['l' => 'Nieuwprijs ± € 18.400', 'v' => 'schatting', 'tone' => 'warn']], 'footer' => 'Zekerheid per onderdeel. Een schatting blijft een schatting.']],
        ['icon' => 'file-text', 'title' => 'Gemaakt', 'text' => 'Titel, beschrijving, specificaties en SEO staan klaar om goed te keuren.', 'panel' => ['label' => 'Advertentie', 'rows' => [['l' => '“Complete designkeuken · Siemens · 2021”', 'quote' => true], ['l' => 'Specificaties', 'v' => '14 velden'], ['l' => 'SEO-score', 'v' => '94', 'bar' => .94]], 'footer' => 'Jij keurt goed: pas aan · vraag Ivy · dit klopt.']],
        ['icon' => 'megaphone', 'title' => 'Promotie', 'text' => 'Vindbaar op Aucvy en in Google, met een Engelse versie. Meer kanalen volgen.', 'panel' => ['label' => 'Promotie', 'rows' => [['l' => 'Aucvy + 6 kanalen', 'v' => 'actief', 'tone' => 'ok'], ['l' => 'Geschat bereik', 'v' => '24k – 31k', 'tone' => 'warn'], ['l' => 'Totale kosten', 'v' => '€ 149 vooraf']], 'footer' => 'De kosten staan vast vóórdat je bevestigt.']],
        ['icon' => 'trending-up', 'title' => 'Biedingen', 'text' => 'Ivy volgt de veiling en geeft bieders advies op het juiste moment.', 'panel' => ['label' => 'Biedingen', 'rows' => [['l' => 'Views', 'v' => '1.842'], ['l' => 'Bieders', 'v' => '24'], ['l' => 'Huidig bod', 'v' => '€ 8.900', 'tone' => 'ok']], 'footer' => 'Ivy adviseert; jij beslist over elke actie.']],
        ['icon' => 'banknote', 'title' => 'Betaling', 'text' => 'Betaling via het platform, uitbetaling na de houdtermijn: zonder verrassingen.', 'panel' => ['label' => 'Afhandeling', 'rows' => [['l' => 'Verkocht voor', 'v' => '€ 9.400', 'tone' => 'ok'], ['l' => 'Succesfee', 'v' => 'vooraf bekend'], ['l' => 'Factuur en transport', 'done' => true]], 'footer' => 'Alles op één plek: je weet vooraf wat er onder de streep overblijft.']],
    ];

    $buyerSteps = [
        ['icon' => 'message-circle', 'title' => 'Vertel Ivy', 'text' => 'Eén zin in gewone taal is genoeg om te starten.', 'panel' => ['label' => 'Vertel Ivy', 'rows' => [['l' => '“20 bureaustoelen, max € 4.000”', 'quote' => true], ['l' => 'Criteria herkend', 'v' => '6'], ['l' => 'Regio', 'v' => 'Nederland']], 'footer' => 'Klopt er iets niet? Corrigeer haar interpretatie met één tik.']],
        ['icon' => 'target', 'title' => 'Match', 'text' => 'Relevante kavels met matchscore en uitleg waarom.', 'panel' => ['label' => 'Matches', 'rows' => [['l' => 'Herman Miller · 24 stuks', 'v' => '96%', 'bar' => .96], ['l' => 'Steelcase · 20 stuks', 'v' => '88%', 'bar' => .88], ['l' => 'Binnen jouw budget', 'v' => '9 van 12']], 'footer' => 'Bij elke match staat waarom hij past.']],
        ['icon' => 'scan-search', 'title' => 'Vergelijk', 'text' => 'Zij-aan-zij op prijs, conditie en transport.', 'panel' => ['label' => 'Vergelijking', 'rows' => [['l' => 'Prijs per stuk', 'v' => '€ 115 vs € 160'], ['l' => 'Conditie', 'v' => 'goed vs nieuw'], ['l' => 'Transport', 'v' => '€ 340 vs € 90']], 'footer' => 'Zij-aan-zij, zonder ruis.']],
        ['icon' => 'shield-check', 'title' => 'Onderzoek', 'text' => 'Documenten op leesbaarheid gecontroleerd, verkoper geverifieerd.', 'panel' => ['label' => 'Onderzoek', 'rows' => [['l' => 'Verkoper geverifieerd', 'done' => true], ['l' => 'Aankoopfactuur aanwezig', 'done' => true], ['l' => 'Aandachtspunt', 'v' => '3× gebruikssporen', 'tone' => 'warn']], 'footer' => 'Ook wat níet perfect is, staat er gewoon bij.']],
        ['icon' => 'sparkles', 'title' => 'Advies', 'text' => 'Waarde-indicatie met zekerheid en nuchter biedadvies.', 'panel' => ['label' => 'Advies', 'rows' => [['l' => 'Marktwaarde', 'v' => '€ 5.4k – € 7.2k', 'tone' => 'warn'], ['l' => 'Verwacht eindbod', 'v' => '€ 3.5k – € 4.1k', 'tone' => 'warn'], ['l' => 'Openingsbod', 'v' => '€ 2.900']], 'footer' => 'Indicaties, nooit garanties.']],
        ['icon' => 'trending-up', 'title' => 'Bied', 'text' => 'Zelf bieden of automatisch meestappen tot jouw maximum.', 'panel' => ['label' => 'Bieden', 'rows' => [['l' => 'Jouw maximum', 'v' => '€ 4.000'], ['l' => 'Modus', 'v' => 'automatisch meestappen'], ['l' => 'Status', 'v' => 'hoogste bod', 'tone' => 'ok']], 'footer' => 'Nooit meer dan jouw limiet. Jij zet hem, jij haalt hem weg.']],
        ['icon' => 'truck', 'title' => 'Ontvang', 'text' => 'Betalen via het platform; transport of ophalen regel je met de verkoper.', 'panel' => ['label' => 'Ontvangst', 'rows' => [['l' => 'Gewonnen voor', 'v' => '€ 3.850', 'tone' => 'ok'], ['l' => 'Transport', 'v' => '€ 90'], ['l' => 'Totaal', 'v' => '€ 3.940']], 'footer' => 'Het complete bedrag staat er vóórdat je betaalt.']],
    ];
@endphp

<x-v4.story id="verkopersstory" kicker="Voor verkopers" title="Van foto's naar verkocht"
    sub="Zes stappen, en Ivy doet ze allemaal. Kijk mee terwijl ze de kavel opbouwt, of klik een stap aan."
    :steps="$sellerSteps" />

<x-v4.story id="kopersstory" kicker="Voor kopers" title="Van zoekvraag naar gewonnen"
    sub="Van één gewone zin tot bezorging. Elke stap laat zien wat Ivy op dat moment voor je uitzoekt."
    :steps="$buyerSteps" />

{{-- ================= VERTROUWEN ================= --}}
<div class="v4-dark v4-dark--navy">
    <div class="v4-glow v4-glow--grad v4-anim-drift-b" aria-hidden="true" style="width:460px;height:460px;opacity:.14;top:-140px;right:-80px"></div>
    <x-v4.particles :count="10" />
    <section id="vertrouwen" style="margin:0 auto;max-width:1220px;padding:76px 24px;position:relative" aria-labelledby="vertrouwen-title">
        <div class="v4-head">
            <span class="v4-kicker v4-kicker--dark">Vertrouwen</span>
            <h2 class="v4-h2" id="vertrouwen-title">Zekerheid bij iedere transactie</h2>
            <p class="v4-sub v4-sub--dark">Geen kleine lettertjes: dit is wat er bij elke kavel geregeld en vastgelegd is, zichtbaar vlak bij elke bied- en betaalactie.</p>
        </div>
        <div class="v4-trust v4-reveal">
            @foreach (['Identiteit verkoper gecontroleerd', 'Eigendomsverklaring en objectinformatie', 'Betalen via de betaalprovider', 'Transparante kosten vóór het bieden', 'Biedhistorie vastgelegd', 'Documenten bij de kavel', 'Transport- en ophaalopties', 'Support bij geschillen'] as $item)
                <div class="v4-trust-card"><x-v4.check :size="18" /><span>{{ $item }}</span></div>
            @endforeach
        </div>
        <div style="margin-top:22px"><x-v4.button :href="route('assurance')" variant="ghost">Lees hoe zekerheid werkt, inclusief wat er nog niet is</x-v4.button></div>
    </section>
</div>

{{-- ================= ZAKELIJK ================= --}}
<section id="zakelijk" class="v4-section" style="padding:88px 24px" aria-labelledby="zakelijk-title">
    <div class="v4-two v4-two--wide">
        <div>
            <div class="v4-head v4-reveal">
                <span class="v4-kicker">Zakelijk</span>
                <h2 class="v4-h2" id="zakelijk-title">Verkoop één object of een compleet bedrijf</h2>
                <p class="v4-sub">Voor curatoren, retail, lease en remarketing en overheden: Ivy verwerkt complete inventarissen, van bulkimport tot afgeronde veiling met rapportage.</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:12px">
                <x-v4.button :href="route('sell')" arrow>Zelf starten</x-v4.button>
                <x-v4.button :href="route('sell')" variant="secondary">Veilingvoorstel aanvragen</x-v4.button>
            </div>
        </div>
        <div class="v4-biz">
            @foreach (['Losse kavel', 'Voorraad of partij', 'Complete winkel', 'Faillissement', 'Machinepark', 'White-label veiling'] as $item)
                <div class="v4-biz-item"><x-v4.icon name="building" :size="16" stroke="2.2" style="color:var(--aj-blue)" />{{ $item }}</div>
            @endforeach
        </div>
    </div>
</section>

{{-- ================= TARIEVEN ================= --}}
@php
    // De percentages komen uit CompanyPlan. Maandbedragen voor Pro en
    // Enterprise zijn nog niet vastgesteld (zie /prijzen): die staan hier
    // dus niet, ook niet als ontwerpvoorbeeld.
    $plans = [
        ['plan' => CompanyPlan::Start, 'price' => '€ 0', 'per' => 'per maand', 'for' => 'Voor losse kavels', 'hot' => false],
        ['plan' => CompanyPlan::Pro, 'price' => 'Volgt', 'per' => 'prijs nog niet vastgesteld', 'for' => 'Voor structurele verkopers', 'hot' => false],
        ['plan' => CompanyPlan::Enterprise, 'price' => 'Volgt', 'per' => 'prijs nog niet vastgesteld', 'for' => 'Voor voorraden en complete veilingen', 'hot' => false],
    ];
@endphp
<section id="tarieven" class="v4-section" aria-labelledby="tarieven-title">
    <div class="v4-head v4-reveal">
        <span class="v4-kicker">Prijzen</span>
        <h2 class="v4-h2" id="tarieven-title">Gratis starten. Groeien wanneer jij dat wilt.</h2>
        <p class="v4-sub">Geen opstartkosten en geen opgeld voor kopers. Je ziet elke fee vóór je publiceert of biedt.</p>
    </div>
    <div class="v4-pricing">
        @foreach ($plans as $index => $item)
            <div class="v4-price v4-reveal {{ $item['hot'] ? 'v4-price--hot' : '' }}" style="--d:{{ $index * 0.1 }}s">
                @if ($item['hot'])<span class="v4-price-tag">Meest gekozen</span>@endif
                <span class="v4-price-name">{{ $item['plan']->label() }}</span>
                <div class="v4-price-amount"><b class="v4-tnum">{{ $item['price'] }}</b><span>{{ $item['per'] }}</span></div>
                <span class="v4-price-fee">{{ number_format($item['plan']->defaultFeePercentage(), 1, ',', '.') }}% bij verkoop</span>
                <span class="v4-price-for">{{ $item['for'] }}</span>
            </div>
        @endforeach
    </div>
    <div class="v4-card" style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:14px;margin-top:18px;border-radius:var(--aj-radius-m);padding:18px 24px">
        <span style="font-size:14.5px;font-weight:800">Complete bedrijfs- of faillissementsveiling?</span>
        <x-v4.button :href="route('sell')" variant="secondary">Ontvang een voorstel</x-v4.button>
    </div>
    <p style="margin:14px 4px 0;font-size:12px;color:var(--aj-muted)">Volledige tarieven en termijnen staan op de <a href="{{ route('faq') }}" style="color:var(--aj-blue-text);font-weight:700">pagina met veelgestelde vragen</a>.</p>
</section>

{{-- ================= START ================= --}}
<x-v4.cta-start />

</x-layouts.v4>
