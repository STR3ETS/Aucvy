{{--
    De live productdemo in de hero van preview v4: een venster dat in negen
    scenes laat zien wat Ivy doet, voor verkopen én voor kopen. De regie zit
    in v4Demo (v4.js): scene, voortgang 0..1, afspelen en pauzeren. Elementen
    verschijnen zodra de voortgang hun drempel passeert (at(.45)); tellers
    lopen op met lerp().

    De teksten en bedragen komen letterlijk uit de bundel van het ontwerp.
    Het is een demo: elk bedrag is fictief en staat ook zo onder het venster.
--}}
@php
    $sellerFiles = [['IMG_2841.jpg', .15], ['IMG_2842.jpg', .3], ['Aankoopfactuur.pdf', .45], ['Spraakbericht · 0:42', .6]];
    $channels = [['Facebook', '#1877f2', .2], ['Instagram', '#e1306c', .35], ['LinkedIn', '#0a66c2', .5], ['Google', '#34a853', .65], ['WhatsApp', '#25d366', .8]];
    $buyerCriteria = [['Aantal', '20+', .15], ['Categorie', 'bureaustoelen', .3], ['Budget', '€ 4.000 totaal', .45], ['Conditie', 'goed of beter', .6], ['Transport', 'gewenst', .72], ['Regio', 'Nederland', .84]];
    $buyerQuery = '"Ik zoek twintig ergonomische bureaustoelen voor maximaal € 4.000 inclusief transport."';
@endphp

<div x-data="v4Demo()" style="position:relative">
    <div style="display:flex;justify-content:center;margin-bottom:14px">
        <div role="tablist" aria-label="Demo: verkopen of kopen" class="v4-seg">
            <button type="button" role="tab" :aria-selected="mode === 'seller'" @click="switchMode('seller')">Ik wil verkopen</button>
            <button type="button" role="tab" :aria-selected="mode === 'buyer'" @click="switchMode('buyer')">Ik wil kopen</button>
        </div>
    </div>

    <div class="v4-frame">
        <div class="v4-frame-bar">
            <i style="background:#ff5f57"></i><i style="background:#febc2e"></i><i style="background:#28c840"></i>
            <span class="title">aucvy.nl: live productdemo · <span x-text="mode === 'seller' ? 'verkopen' : 'kopen'"></span></span>
            <span class="step"><span x-text="scene + 1"></span>/<span x-text="total"></span> · <span x-text="label"></span></span>
        </div>

        <div class="v4-frame-body">

            {{-- ============ VERKOPEN ============ --}}

            <div x-show="mode === 'seller' && scene === 0" x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Sleep alles hierin: foto's, documenten, of vertel het me." />
                <div class="v4-box v4-box--dashed">
                    <div style="display:grid;gap:7px">
                        @foreach ($sellerFiles as [$file, $at])
                            <div class="v4-step" :class="{ 'is-on': at({{ $at }}) }"><div class="v4-file"><x-v4.check :size="16" />{{ $file }}</div></div>
                        @endforeach
                    </div>
                </div>
                <div class="v4-bar"><div :style="`width:${p * 100}%`"></div></div>
            </div>

            <div x-show="mode === 'seller' && scene === 1" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Ik herken je object, met zekerheid per onderdeel." />
                <div class="v4-box">
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.2) }"><span>Siemens apparatuur herkend</span><b class="blue">98%</b></div>
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.45) }"><span>Conditie: zeer goed</span><b class="blue">86%</b></div>
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.7) }"><span>Nieuwprijs circa € 18.400</span><b class="warn">schatting</b></div>
                </div>
            </div>

            <div x-show="mode === 'seller' && scene === 2" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Je advertentie staat klaar. Jij keurt goed." />
                <div class="v4-box">
                    <div class="v4-step" :class="{ 'is-on': at(.15) }"><span style="font-size:14px;font-weight:800;color:#fff">Complete designkeuken met Siemens-apparatuur</span></div>
                    <div class="v4-step" :class="{ 'is-on': at(.35) }"><p style="margin:8px 0 0;font-size:11.5px;line-height:1.55;color:rgba(255,255,255,.65)">Hoogglans greeploze keuken (2022) met kwartscomposiet blad, inductie, combi-oven en vaatwasser. Demontage en ophalen in overleg…</p></div>
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.55) }" style="margin-top:8px"><span>Specificaties</span><b class="blue">14 velden</b></div>
                    <div class="v4-row"><span>SEO-score</span><b class="blue v4-tnum" x-text="Math.round(lerp(0, 94, .5, .95))"></b></div>
                </div>
                <div class="v4-step" :class="{ 'is-on': at(.85) }" style="display:flex;gap:8px;flex-wrap:wrap">
                    <span class="v4-mini-chip">Pas aan</span><span class="v4-mini-chip">Vraag Ivy</span><span class="v4-mini-chip v4-mini-chip--grad">Dit klopt ✓</span>
                </div>
            </div>

            <div x-show="mode === 'seller' && scene === 3" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Mijn advies: veilen met startprijs € 2.500. Jij beslist." />
                <div class="v4-box">
                    <span class="v4-box-label">Ivy-startprijs · advies</span>
                    <div style="display:flex;align-items:baseline;gap:8px;margin:6px 0 10px">
                        <span class="v4-tnum" style="font-size:24px;font-weight:800;color:#fff;letter-spacing:-.02em" x-text="euro(lerp(1800, 2500, .25, .7))"></span>
                        <span style="font-size:11px;font-weight:700;color:#9db9ff">94% zekerheid · indicatie</span>
                    </div>
                    <div class="v4-range"><div class="band" style="left:12%;right:30%"></div><div class="dot" :style="`left:${lerp(8, 42, .25, .7)}%`"></div></div>
                    <div style="display:flex;justify-content:space-between;margin-top:5px;font-size:10px;color:rgba(255,255,255,.45);font-weight:700"><span>min € 1.800</span><span>ideaal</span><span>max € 3.200</span></div>
                </div>
                <div class="v4-step" :class="{ 'is-on': at(.8) }" style="display:flex;gap:8px;flex-wrap:wrap">
                    <span class="v4-mini-chip v4-mini-chip--grad">Veiling ✓</span><span class="v4-mini-chip">Vanafprijs</span><span class="v4-mini-chip">Direct kopen</span><span class="v4-mini-chip">Combinatie</span>
                </div>
            </div>

            <div x-show="mode === 'seller' && scene === 4" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Ik maak per kanaal een eigen uiting: tekst, beeld en format." />
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    @foreach ($channels as [$channel, $color, $at])
                        <div class="v4-step" :class="{ 'is-on': at({{ $at }}) }">
                            <div class="v4-box" style="padding:10px 13px;display:flex;align-items:center;gap:8px">
                                <span aria-hidden="true" style="width:10px;height:10px;border-radius:50%;background:{{ $color }}"></span>
                                <span style="font-size:11px;font-weight:800;color:rgba(255,255,255,.85)">{{ $channel }} klaar ✓</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="v4-step" :class="{ 'is-on': at(.9) }"><div class="v4-box" style="background:rgba(47,107,255,.12)"><span style="font-size:12px;font-weight:700;color:#9db9ff">5 kanaalformats + promovideo gegenereerd</span></div></div>
            </div>

            <div x-show="mode === 'seller' && scene === 5" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Live op Aucvy en zes kanalen. De kosten stonden vooraf vast." />
                <div class="v4-box">
                    <div class="v4-row"><span>Status</span><b class="ok">● Live</b></div>
                    <div class="v4-row"><span>Kanalen actief</span><b>Aucvy + 6</b></div>
                    <div class="v4-row"><span>Geschat bereik</span><b class="blue v4-tnum" x-text="Math.round(lerp(0, 27400, .3, .95)).toLocaleString('nl-NL')"></b></div>
                    <div class="v4-row"><span>Promotiekosten</span><b>€ 149 · vooraf bevestigd</b></div>
                </div>
            </div>

            <div x-show="mode === 'seller' && scene === 6" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Ik nodig de meest relevante kopers persoonlijk uit." />
                <div class="v4-box">
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.25) }"><span>Restauranteigenaar · Utrecht</span><span class="v4-match">96% match</span></div>
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.5) }"><span>Interieurbouwer · Breda</span><span class="v4-match">91% match</span></div>
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.75) }"><span>Particulier · Amsterdam</span><span class="v4-match">88% match</span></div>
                </div>
            </div>

            <div x-show="mode === 'seller' && scene === 7" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="De biedingen lopen op. De opbrengst ligt binnen de verwachte range." />
                <div class="v4-box">
                    <span class="v4-box-label">Huidig bod</span>
                    <div class="v4-box-big v4-tnum" x-text="euro(lerp(6500, 8900, .1, .85))"></div>
                    <div class="v4-row"><span>Bieders</span><b class="v4-tnum" x-text="Math.round(lerp(9, 24, .1, .85))"></b></div>
                    <div class="v4-row"><span>Views</span><b class="v4-tnum" x-text="Math.round(lerp(600, 1842, .1, .85)).toLocaleString('nl-NL')"></b></div>
                </div>
            </div>

            <div x-show="mode === 'seller' && scene === 8" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <div class="v4-box v4-box--ok">
                    <div class="v4-step" :class="{ 'is-on': at(.1) }">
                        <span style="display:inline-flex;width:40px;height:40px;border-radius:50%;background:var(--aj-success);align-items:center;justify-content:center;margin-bottom:8px;color:#fff"><x-v4.icon name="check" :size="20" stroke="3" /></span>
                        <div style="font-size:20px;font-weight:800;color:#fff">Verkocht voor € 9.400</div>
                    </div>
                    <div class="v4-step" :class="{ 'is-on': at(.45) }"><p style="margin:6px 0 0;font-size:12px;color:rgba(255,255,255,.7)">Betaling, factuur en transport begeleid. De uitbetaling is onderweg.</p></div>
                </div>
                <div class="v4-step" :class="{ 'is-on': at(.75) }" style="text-align:center"><a href="{{ route('sell') }}" class="v4-cta-pill">Plaats ook een kavel <x-v4.icon name="arrow-right" :size="13" stroke="2.8" /></a></div>
            </div>

            {{-- ============ KOPEN ============ --}}

            <div x-show="mode === 'buyer' && scene === 0" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Vertel me gewoon wat je zoekt. Eén zin is genoeg." />
                <div class="v4-box" style="min-height:84px">
                    <span style="font-size:14px;font-weight:700;color:#fff;line-height:1.5"><span x-text="typed(@js($buyerQuery), .05, .85)"></span><span style="color:#9db9ff" x-show="p < .95">▌</span></span>
                </div>
            </div>

            <div x-show="mode === 'buyer' && scene === 1" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Dit heb ik begrepen. Corrigeer me met één tik." />
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                    @foreach ($buyerCriteria as [$key, $value, $at])
                        <span class="v4-mini-chip v4-mini-chip--blue v4-step" :class="{ 'is-on': at({{ $at }}) }">{{ $key }}: <b>{{ $value }}</b></span>
                    @endforeach
                </div>
            </div>

            <div x-show="mode === 'buyer' && scene === 2" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="12 matches gevonden. Dit is de beste." />
                <div class="v4-step" :class="{ 'is-on': at(.3) }">
                    <div class="v4-box">
                        <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:6px"><span style="font-size:13.5px;font-weight:800;color:#fff">Herman Miller partij · 24 stuks</span><span class="v4-match">96% match</span></div>
                        <div class="v4-row"><span>Huidig bod</span><b>€ 2.750</b></div>
                        <div class="v4-row"><span>Transportindicatie</span><b>€ 340</b></div>
                    </div>
                </div>
                <div class="v4-step" :class="{ 'is-on': at(.7) }"><span style="font-size:11.5px;font-weight:700;color:rgba(255,255,255,.55)">+ 11 andere matches, gesorteerd op relevantie</span></div>
            </div>

            <div x-show="mode === 'buyer' && scene === 3" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Zij-aan-zij: prijs per stuk, conditie en transport." />
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                    <div class="v4-step" :class="{ 'is-on': at(.25) }"><div class="v4-box v4-box--selected"><span style="font-size:12px;font-weight:800;color:#fff;display:block;margin-bottom:6px">Herman Miller · 24 st</span><span style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.65);padding:2px 0">€ 115 / stuk</span><span style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.65);padding:2px 0">conditie goed</span><span style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.65);padding:2px 0">€ 340 transport</span></div></div>
                    <div class="v4-step" :class="{ 'is-on': at(.55) }"><div class="v4-box"><span style="font-size:12px;font-weight:800;color:#fff;display:block;margin-bottom:6px">Steelcase · 20 st</span><span style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.65);padding:2px 0">€ 160 / stuk</span><span style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.65);padding:2px 0">conditie nieuw</span><span style="display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.65);padding:2px 0">€ 90 transport</span></div></div>
                </div>
            </div>

            <div x-show="mode === 'buyer' && scene === 4" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Ik check documenten, verkoper en foto's. Ook wat niet perfect is." />
                <div class="v4-box">
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.2) }"><span>Verkoper geverifieerd</span><b class="ok">✓</b></div>
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.4) }"><span>Factuur aanwezig</span><b class="ok">✓</b></div>
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.55) }"><span>24 exemplaren zichtbaar</span><b class="ok">✓</b></div>
                    <div class="v4-row v4-step" :class="{ 'is-on': at(.78) }"><span>Aandachtspunt</span><b class="warn">3× gebruikssporen</b></div>
                </div>
            </div>

            <div x-show="mode === 'buyer' && scene === 5" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Marktwaarde en verwacht eindbod: indicaties, geen garanties." />
                <div class="v4-box">
                    <div class="v4-row"><span>Marktwaarde</span><b>€ 5.400 – € 7.200</b></div>
                    <div class="v4-row"><span>Verwacht eindbod</span><b class="blue v4-tnum">€ 3.500 – <span x-text="euro(lerp(3200, 4100, .3, .8))"></span></b></div>
                    <div class="v4-range" style="margin-top:12px"><div class="band" style="left:18%;right:22%"></div><div class="dot" :style="`left:${lerp(18, 44, .3, .85)}%`"></div></div>
                </div>
            </div>

            <div x-show="mode === 'buyer' && scene === 6" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Kies jouw modus. Ik blijf altijd binnen je limiet." />
                <div class="v4-box"><div class="v4-row"><span>Jouw maximumbod</span><b style="font-size:13px">€ 4.000</b></div></div>
                <div style="display:grid;gap:8px">
                    <div class="v4-step" :class="{ 'is-on': at(.35) }"><div class="v4-box v4-option"><i aria-hidden="true"></i>Zelf bieden</div></div>
                    <div class="v4-step" :class="{ 'is-on': at(.55) }"><div class="v4-box v4-option"><i aria-hidden="true"></i>Ivy waarschuwt mij</div></div>
                    <div class="v4-step" :class="{ 'is-on': at(.75) }"><div class="v4-box v4-option is-on"><i aria-hidden="true"></i>Automatisch meestappen tot limiet</div></div>
                </div>
            </div>

            <div x-show="mode === 'buyer' && scene === 7" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <x-v4.ivy-line text="Laatste minuten. Je bent hoogste bieder binnen je limiet." />
                <div class="v4-box">
                    <span class="v4-box-label">Huidig bod · jij</span>
                    <div class="v4-box-big v4-tnum" x-text="euro(lerp(2750, 3850, .15, .8))"></div>
                    <div class="v4-row"><span>Status</span><b class="ok">Hoogste bod ✓</b></div>
                    <div class="v4-row"><span>Resterend</span><b class="warn v4-tnum"><span x-text="Math.max(0, Math.round(lerp(140, 4, .1, .95)))"></span> sec</b></div>
                </div>
            </div>

            <div x-show="mode === 'buyer' && scene === 8" x-cloak x-transition.opacity.duration.400ms style="display:grid;gap:12px">
                <div class="v4-box v4-box--ok" style="padding:18px 16px">
                    <div class="v4-step" :class="{ 'is-on': at(.1) }">
                        <span style="display:inline-flex;width:40px;height:40px;border-radius:50%;background:var(--aj-success);align-items:center;justify-content:center;margin-bottom:8px;color:#fff"><x-v4.icon name="check" :size="20" stroke="3" /></span>
                        <div style="font-size:20px;font-weight:800;color:#fff">Gewonnen voor € 3.850</div>
                    </div>
                    <div class="v4-step" :class="{ 'is-on': at(.45) }"><p style="margin:6px 0 0;font-size:12px;color:rgba(255,255,255,.7)">Totaal inclusief € 340 transport, volledig getoond vóór betaling. Levering gepland.</p></div>
                </div>
                <div class="v4-step" :class="{ 'is-on': at(.75) }" style="text-align:center"><a href="{{ route('lots.index') }}" class="v4-cta-pill">Vraag Ivy om te zoeken <x-v4.icon name="arrow-right" :size="13" stroke="2.8" /></a></div>
            </div>
        </div>
    </div>

    <div style="display:flex;align-items:center;justify-content:center;gap:12px;margin-top:12px">
        <template x-if="reduce">
            <div style="display:flex;align-items:center;gap:12px">
                <button type="button" class="v4-icon-btn v4-icon-btn--sm" aria-label="Vorige stap" @click="previous()"><x-v4.icon name="arrow-left" :size="15" stroke="2.6" /></button>
                <span style="font-size:11.5px;font-weight:800;color:var(--aj-muted)">Stap <span x-text="scene + 1"></span> van <span x-text="total"></span></span>
                <button type="button" class="v4-icon-btn v4-icon-btn--sm" aria-label="Volgende stap" @click="next()"><x-v4.icon name="arrow-right" :size="15" stroke="2.6" /></button>
            </div>
        </template>
        <template x-if="! reduce">
            <div style="display:flex;align-items:center;gap:12px">
                <button type="button" class="v4-icon-btn v4-icon-btn--sm" :aria-label="playing ? 'Pauzeer demo' : 'Speel demo af'" @click="toggle()">
                    <x-v4.icon name="pause" :size="14" stroke="2.4" x-show="playing" />
                    <x-v4.icon name="play" :size="14" stroke="2.4" x-show="! playing" />
                </button>
                <div role="tablist" aria-label="Demo-stappen" class="v4-dots">
                    <template x-for="(name, index) in labels[mode]" :key="mode + index">
                        <button type="button" role="tab" :aria-selected="scene === index" :aria-label="(index + 1) + '. ' + name" @click="go(index)"></button>
                    </template>
                </div>
            </div>
        </template>
    </div>
    <p style="margin:8px 4px 0;font-size:11px;color:rgba(255,255,255,.5);text-align:center">Demo-weergave met fictieve bedragen · AI-inschattingen zijn indicaties, geen garanties</p>
</div>
