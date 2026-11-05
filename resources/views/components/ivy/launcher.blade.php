{{--
    IvyLauncher en IvyPanel (Ivy-briefing hfst. 9): op elke pagina, start
    ingeklapt, rechtsonder; op mobiel boven de sticky biedbalk, nooit
    eroverheen. Desktop een kolom naast de pagina, mobiel volledig scherm.
    Behoudt het gesprek tussen pagina's. Zegt in de kop dat ze AI is.

    Het gedrag (stroom met stappen, uittypen, stoppen, opnieuw, kopiëren,
    voorlezen, inspreken) staat in resources/js/ivy-panel.js, de opmaak in
    resources/css/ivy.css. De opmaak volgt de Ivy-canvassen uit het ontwerp
    van november.
--}}
@props(['lot' => null, 'page' => 'onbekend'])

@php $ivyEnabled = (bool) config('aucvy.ai.enabled') && \App\Ivy\Chat\IvyFlags::on('ivy.enabled'); @endphp

@if ($ivyEnabled)
<div x-data="ivyPanel({{ Js::from(['show' => route('ivy.show'), 'send' => route('ivy.send'), 'reset' => route('ivy.reset'), 'upload' => route('ivy.upload'), 'lot' => $lot?->slug, 'page' => $page, 'name' => auth()->user()?->name]) }})"
     class="ivy-root" @ivy-open.window="open($event.detail?.text)">

    <button type="button" class="ivy-fab" @click="toggle()" :aria-expanded="opened" aria-controls="ivy-panel" aria-label="Open Ivy, de AI-assistent">
        <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
        <span>Ivy</span>
        <span class="count" x-show="openCount > 0" x-text="openCount" x-cloak></span>
    </button>

    <section id="ivy-panel" class="ivy-panel" :class="{ 'is-wide': wide, 'is-drop': dropping }" x-show="opened" x-cloak aria-label="Ivy, AI-assistent" role="dialog"
             @dragover.prevent="dropping = true" @dragleave.prevent="dropping = false"
             @drop.prevent="dropping = false; upload($event.dataTransfer.files[0])">
        <header class="ivy-head">
            <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
            <div class="ivy-head-text">
                <strong>Ivy <span>AI-assistent</span></strong>
                <p>
                    <i class="ivy-dot" :class="busy && 'is-busy'" aria-hidden="true"></i>
                    <span x-text="busy ? 'Is ermee bezig…' : (lotTitle ? 'Kijkt mee op ' + lotTitle : 'Kijkt mee op ' + pageTitle)">Kijkt mee op deze pagina</span>
                </p>
            </div>
            <a x-show="inboxUrl && openCount > 0" :href="inboxUrl" class="ivy-head-link" x-cloak>Inbox <b x-text="openCount"></b></a>
            <button type="button" class="ivy-head-btn" x-show="messages.length" x-cloak @click="restart()" :disabled="busy" title="Nieuw gesprek" aria-label="Nieuw gesprek">
                <x-v4.icon name="plus" :size="18" stroke="2.2" />
            </button>
            <button type="button" class="ivy-head-btn ivy-wide-btn" @click="wide = ! wide; scroll(true)" :title="wide ? 'Kleiner' : 'Groter'" :aria-label="wide ? 'Paneel kleiner maken' : 'Paneel groter maken'">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path x-show="! wide" d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                    <path x-show="wide" x-cloak d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/>
                </svg>
            </button>
            <button type="button" class="ivy-head-btn" @click="close()" aria-label="Sluiten">
                <x-v4.icon name="x" :size="18" stroke="2.2" />
            </button>
        </header>

        <div class="ivy-log" x-ref="log" @scroll.passive="onScroll()" aria-live="polite">

            {{-- Nog geen berichten: Ivy begroet, zegt wat er wacht en geeft een paar vragen om mee te beginnen. --}}
            <div class="ivy-start" x-show="loaded && ! messages.length" x-cloak>
                <div class="ivy-answer">
                    <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
                    <div class="ivy-answer-body">
                        <div class="ivy-bubble"><strong x-text="hello + '.'"></strong> <span x-text="greeting ?? 'Ik ben Ivy, de AI-assistent van Aucvy. Waar kan ik mee helpen?'"></span></div>
                    </div>
                </div>
                <a class="ivy-attention" x-show="inboxUrl && openCount > 0" :href="inboxUrl" x-cloak>
                    <i aria-hidden="true"></i>
                    <span x-text="openCount === 1 ? 'Eén voorstel wacht op je ja' : openCount + ' voorstellen wachten op je ja'"></span>
                </a>
                <p class="ivy-label" x-show="enabled && chips.length">Sneller met Ivy</p>
                <div class="ivy-suggest" x-show="enabled && chips.length">
                    <template x-for="chip in chips" :key="chip">
                        <button type="button" @click="send(chip)">
                            <span x-text="chip"></span>
                            <x-v4.icon name="arrow-right" :size="14" stroke="2.2" />
                        </button>
                    </template>
                </div>
                <p class="ivy-doubt" x-show="enabled">Twijfel? <button type="button" @click="send('Ik wil een medewerker spreken')">Vraag een medewerker</button></p>
            </div>

            <template x-for="message in messages" :key="message.id">
                <div style="display:contents">
                    <template x-if="message.sender === 'user'">
                        <div class="ivy-user" x-text="message.body"></div>
                    </template>

                    <template x-if="message.sender !== 'user'">
                        <div class="ivy-answer" :class="message.id === lastId && 'is-last'">
                            <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
                            <div class="ivy-answer-body">

                                {{-- Wat Ivy aan het doen is, terwijl ze het doet. --}}
                                <div class="ivy-steps" x-show="message.steps.length" x-cloak>
                                    <template x-for="step in message.steps" :key="step.id">
                                        <span class="ivy-step" :class="'is-' + step.state">
                                            <i class="ivy-spin" x-show="step.state === 'bezig'" aria-hidden="true"></i>
                                            <svg x-show="step.state === 'klaar'" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                                            <span x-text="step.text"></span>
                                        </span>
                                    </template>
                                </div>

                                <div class="ivy-thinking" x-show="message.pending && ! message.steps.some((step) => step.state === 'bezig')" x-cloak>
                                    Denkt na <span aria-hidden="true"><i></i><i></i><i></i></span>
                                </div>

                                <div class="ivy-bubble ivy-md" :class="message.typing && 'is-typing'" x-show="message.shown" x-html="render(message)"></div>

                                <template x-for="(card, index) in message.cards.filter((item) => item.type !== 'link')" :key="message.id + '-c' + index">
                                    <div x-show="! message.typing && ! message.pending">
                                        <template x-if="card.type === 'lot'">
                                            <a class="ivy-card ivy-lot" :href="card.url">
                                                <template x-if="card.image"><img :src="card.image" alt="" loading="lazy"></template>
                                                <template x-if="! card.image"><span class="thumb" aria-hidden="true"></span></template>
                                                <span class="text">
                                                    <strong x-text="card.title"></strong>
                                                    <span x-text="[card.meta, card.ends ? 'sluit ' + card.ends : null, card.badge].filter(Boolean).join(' · ')"></span>
                                                </span>
                                                <span class="right" x-show="card.price"><b x-text="card.price"></b><span x-text="card.price_label"></span></span>
                                            </a>
                                        </template>
                                        <template x-if="card.type === 'proposal'">
                                            <div class="ivy-card ivy-proposal">
                                                <strong x-text="[card.title, card.amount].filter(Boolean).join(' ')"></strong>
                                                <span>Je bevestigt op een scherm met het actuele bedrag en alle kosten. Niets gebeurt zonder jouw ja.</span>
                                                <a :href="card.url" class="ivy-card-btn">Bekijk en bevestig</a>
                                            </div>
                                        </template>
                                        <template x-if="card.type === 'done'">
                                            <div class="ivy-card ivy-done">
                                                <i class="mark" aria-hidden="true"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></i>
                                                <span x-text="card.text"></span>
                                                <a x-show="card.url" :href="card.url">Bekijk</a>
                                            </div>
                                        </template>
                                        <template x-if="card.type === 'example'">
                                            <div class="ivy-card ivy-example"><span class="tag" x-text="card.label"></span><p x-text="card.body"></p></div>
                                        </template>
                                    </div>
                                </template>

                                <div class="ivy-links" x-show="! message.typing && message.cards.some((item) => item.type === 'link')" x-cloak>
                                    <template x-for="(card, index) in message.cards.filter((item) => item.type === 'link')" :key="message.id + '-l' + index">
                                        <a class="ivy-pill is-primary" :href="card.url">
                                            <span x-text="card.label"></span>
                                            <x-v4.icon name="arrow-right" :size="14" stroke="2.4" />
                                        </a>
                                    </template>
                                </div>

                                <div class="ivy-error" x-show="message.error" x-cloak role="alert">
                                    <span x-text="message.error"></span>
                                    <button type="button" x-show="message.id === lastId" @click="retry(message)">Opnieuw</button>
                                </div>
                                <span class="ivy-stopped" x-show="message.stopped && ! message.body" x-cloak>Gestopt. Het antwoord staat er de volgende keer dat je Ivy opent.</span>

                                <div class="ivy-tools" x-show="message.body && ! message.typing" x-cloak>
                                    <button type="button" @click="copy(message)" x-text="copied === message.id ? 'Gekopieerd' : 'Kopiëren'">Kopiëren</button>
                                    <button type="button" x-show="canSpeak" @click="speak(message)" x-text="speaking === message.id ? 'Stop voorlezen' : 'Voorlezen'">Voorlezen</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div class="ivy-foot">
            {{-- Vervolgvragen als pilknoppen, zoals in het ontwerp: onder de lijn, met evenveel lucht boven als onder. --}}
            <div class="ivy-chips" x-show="enabled && messages.length && chips.length && ! busy" x-cloak>
                <template x-for="chip in chips" :key="chip"><button type="button" class="ivy-pill" @click="send(chip)" x-text="chip"></button></template>
            </div>
            <form class="ivy-form" @submit.prevent="busy ? stop() : send(draft)">
                <label class="ivy-icon-btn" title="Stuur een foto">
                    <input type="file" accept="image/*" class="sr-only" :disabled="busy || ! enabled" @change="upload($event.target.files[0]); $event.target.value = ''">
                    <x-v4.icon name="camera" :size="18" stroke="2" />
                    <span class="sr-only">Stuur een foto</span>
                </label>
                <textarea x-ref="input" x-model="draft" rows="1" maxlength="2000" :disabled="! enabled"
                          :placeholder="listening ? 'Ik luister…' : 'Vraag Ivy iets'" title="Typ je vraag, of sleep een foto in dit venster" aria-label="Je bericht aan Ivy"
                          @input="resize()" @keydown.enter="onEnter($event)"></textarea>
                <button type="button" class="ivy-icon-btn" :class="listening && 'is-on'" x-show="canDictate" x-cloak @click="dictate()" :disabled="busy || ! enabled"
                        :title="listening ? 'Stop met luisteren' : 'Inspreken'" :aria-label="listening ? 'Stop met luisteren' : 'Spreek je vraag in'" :aria-pressed="listening">
                    <x-v4.icon name="mic" :size="18" stroke="2" />
                </button>
                <button type="submit" class="ivy-send" :class="busy && 'is-stop'" :disabled="! busy && (! draft.trim() || ! enabled)" :aria-label="busy ? 'Stop' : 'Verstuur'">
                    <i x-show="busy" x-cloak aria-hidden="true"></i>
                    <span x-show="! busy" style="display:flex"><x-v4.icon name="arrow-right" :size="17" stroke="2.6" /></span>
                </button>
            </form>
            <p class="ivy-note">
                <span x-show="enabled">Ivy is een AI-assistent. Een bod of aankoop bevestig je altijd zelf op een scherm met het actuele bedrag en alle kosten. <kbd x-text="shortcut"></kbd> opent en sluit.</span>
                <span x-show="! enabled" x-cloak>Ivy staat even uit. Bieden, zoeken en je meldingen werken gewoon.</span>
            </p>
        </div>
    </section>
</div>
@endif
