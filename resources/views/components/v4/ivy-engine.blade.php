{{--
    "Jouw persoonlijke commerce-assistent" uit preview v4: zes taken als tabs,
    per taak een vraag, Ivy's antwoord en een resultaatkaart. Speelt vanzelf
    af (v4Engine in v4.js); een tab kiezen pauzeert.

    Teksten komen uit de bundel van het ontwerp; de bedragen zijn fictief.
--}}
@php
    $scenes = [
        ['icon' => 'wand-sparkles', 'key' => 'Maakt', 'ask' => 'Maak de advertentie voor deze keuken.', 'reply' => 'Klaar. Titel, beschrijving en 14 specificaties staan er, plus een social post en een korte video. Jij keurt goed.', 'result' => ['title' => 'Advertentie · concept', 'rows' => [['Titel', 'Complete designkeuken · Siemens'], ['Specificaties', '14 velden ingevuld'], ['Kanalen klaar', 'Aucvy · social · Google']], 'note' => 'Niets gaat live voordat jij het goedkeurt.']],
        ['icon' => 'scan-search', 'key' => 'Onderzoekt', 'ask' => 'Klopt deze kavel wel?', 'reply' => 'Verkoper en factuur kloppen. Eén ding om te weten: op drie foto\'s zie ik gebruikssporen die niet in de tekst staan.', 'result' => ['title' => 'Onderzoek · kavel 8842', 'rows' => [['Verkoper geverifieerd', 'ja'], ['Aankoopfactuur', 'aanwezig'], ['Aandachtspunt', '3× gebruikssporen']], 'note' => 'Ook wat níet klopt, krijg je te zien.']],
        ['icon' => 'trending-up', 'key' => 'Optimaliseert', 'ask' => 'Wanneer kan ik het beste laten sluiten?', 'reply' => 'Donderdagavond levert in deze categorie de meeste eindbiedingen op. Ik kan het verzetten, dan verlengt de looptijd met een dag.', 'result' => ['title' => 'Voorstel · sluitmoment', 'rows' => [['Nu gepland', 'dinsdag 14:00'], ['Advies', 'donderdag 20:00'], ['Basis', 'laatste 90 dagen, zelfde categorie']], 'note' => 'Indicatie op basis van eerdere veilingen, geen voorspelling.']],
        ['icon' => 'target', 'key' => 'Matcht', 'ask' => 'Ik zoek 20 bureaustoelen onder € 4.000.', 'reply' => 'Twaalf partijen passen. De beste zit op 96%: Herman Miller, 24 stuks, inclusief transportprijs.', 'result' => ['title' => 'Matches · 12 gevonden', 'rows' => [['Beste match', '96%'], ['Herman Miller · 24 st.', '€ 2.750 nu'], ['Binnen jouw budget', '9 van 12']], 'note' => 'Bij elke match staat waarom hij past.']],
        ['icon' => 'handshake', 'key' => 'Begeleidt', 'ask' => 'Hoeveel moet ik bieden?', 'reply' => 'De markt zit tussen € 5.4k en € 7.2k. Ik zou op € 2.900 openen en je limiet op € 4.000 laten meestappen.', 'result' => ['title' => 'Biedplan · jouw limiet leidt', 'rows' => [['Waarde-indicatie', '€ 5.400 – € 7.200'], ['Verwacht eindbod', '€ 3.500 – € 4.100'], ['Jouw maximum', '€ 4.000']], 'note' => 'Indicaties, nooit garanties. Jij beslist elk bod.']],
        ['icon' => 'banknote', 'key' => 'Handelt af', 'ask' => 'En wat kost het mij in totaal?', 'reply' => 'Alles bij elkaar € 3.940. Geen opgeld voor jou als koper; transport en btw staan er los in.', 'result' => ['title' => 'Kosten · vóór je bevestigt', 'rows' => [['Bod', '€ 3.850'], ['Transport', '€ 90'], ['Opgeld koper', '€ 0']], 'note' => 'Het totaal staat vast voordat je betaalt.']],
    ];
@endphp

<section id="ivy-engine" class="v4-section" aria-labelledby="ivy-engine-title" x-data="v4Engine({ scenes: @js($scenes) })">
    <div class="v4-head-row v4-head-row--tight">
        <div class="v4-head">
            <span class="v4-kicker">Ivy</span>
            <h2 class="v4-h2" id="ivy-engine-title">Jouw persoonlijke commerce-assistent</h2>
            <p class="v4-sub" style="margin-top:10px;font-size:15.5px;max-width:none">Geen chatbot die vragen beantwoordt, maar een assistent die het werk uitvoert. Vandaag: herkennen, schrijven, waarderen, documenten controleren, biedadvies en vertalen. Promotie, planning en matchen zijn in ontwikkeling.</p>
        </div>
        <button type="button" class="v4-play" :aria-label="playing ? 'Pauzeer de doorloop' : 'Speel de doorloop af'" @click="toggle()" x-show="! reduce">
            <x-v4.icon name="pause" :size="13" stroke="2.6" x-show="playing" />
            <x-v4.icon name="play" :size="13" stroke="2.6" x-show="! playing" />
            <span x-text="playing ? 'Pauze' : 'Speel af'"></span>
        </button>
    </div>

    <div class="v4-ivylive">
        <div class="v4-ivylive-fig">
            <div class="v4-glow v4-glow--grad" aria-hidden="true" style="left:10px;top:40px;width:240px;height:240px;filter:blur(56px);opacity:.3"></div>
            <img src="{{ asset('images/v4/ivy-portrait.jpg') }}" alt="Ivy, jouw Online Veiling Assistent" loading="lazy">
            <span class="v4-ivylive-badge"><i aria-hidden="true"></i>Ivy is online</span>
        </div>

        <div style="position:relative;padding:clamp(24px,2.8vw,34px)">
            <div role="tablist" aria-label="Wat Ivy voor je doet" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px">
                @foreach ($scenes as $index => $sceneItem)
                    <button type="button" role="tab" class="v4-tab" :aria-selected="scene === {{ $index }}" @click="go({{ $index }})">
                        <x-v4.icon :name="$sceneItem['icon']" :size="14" stroke="2.3" />{{ $sceneItem['key'] }}
                        <span class="track" aria-hidden="true" x-show="scene === {{ $index }}" :style="`width:${p * 100}%`"></span>
                    </button>
                @endforeach
            </div>

            <div style="min-height:296px">
                <div style="display:grid;gap:12px">
                    <div class="v4-bubble-user v4-step" :class="{ 'is-on': showAsk }" x-text="current.ask"></div>
                    <div class="v4-bubble-ivy">
                        <span class="v4-typing" role="status" aria-label="Ivy denkt na" x-show="showTyping"><i></i><i></i><i></i></span>
                        <span x-show="showReply" x-transition.opacity.duration.300ms x-text="current.reply"></span>
                    </div>
                    <div class="v4-result v4-step" :class="{ 'is-on': showResult }">
                        <span class="label" x-text="current.result.title"></span>
                        <div class="rows">
                            <template x-for="(row, index) in current.result.rows" :key="scene + '-' + index">
                                <div><span x-text="row[0]"></span><b x-text="row[1]"></b></div>
                            </template>
                        </div>
                        <p x-text="current.result.note"></p>
                    </div>
                </div>
            </div>
            <p style="margin:18px 0 0;font-size:12.5px;color:rgba(255,255,255,.55);line-height:1.6">Ivy adviseert, jij beslist. Elke schatting staat als schatting in beeld, nooit als garantie.</p>
        </div>
    </div>
</section>
