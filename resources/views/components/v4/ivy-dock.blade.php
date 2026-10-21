{{--
    De Ivy-dock rechtsonder uit preview v4. In deze demo beantwoordt hij de
    vier vaste vragen met een vast antwoord; een vrije vraag gaat naar het
    zoekveld op de veilingenpagina, waar Ivy hem wél uitlegt.
--}}
<div class="v4-ivy-dock" x-data="v4Dock({ searchUrl: '{{ route('lots.index') }}' })" @keydown.escape.window="open = false">
    <section role="dialog" aria-label="Ivy, jouw AI-hulp" class="v4-dock-panel" x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-4"
             x-trap.noscroll.inert="open">
        <div class="v4-dock-head">
            <img src="{{ asset('images/v4/ivy-portrait.jpg') }}" alt="">
            <div class="intro">
                <p>Hoi! Ik ben <span class="v4-grad-text" style="background:var(--aj-grad);-webkit-background-clip:text;background-clip:text">Ivy.</span></p>
                <p>Jouw Online Veiling Assistent, altijd hier rechtsonder.</p>
                <svg viewBox="0 0 120 14" aria-hidden="true" style="height:13px;width:104px;margin-top:8px"><path d="M2 7h6M12 7c2-5 4-5 6 0s4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0 4 5 6 0 4-5 6 0M112 7h6" fill="none" stroke="var(--aj-violet)" stroke-width="1.6" stroke-linecap="round" opacity="0.7"/></svg>
            </div>
            <button type="button" class="v4-dock-close" aria-label="Ivy minimaliseren" @click="open = false"><x-v4.icon name="x" :size="14" stroke="2.4" /></button>
        </div>

        <div class="v4-dock-log" aria-live="polite" x-ref="log">
            <template x-for="(message, index) in messages" :key="index">
                <div class="v4-dock-msg" :class="message.from === 'ivy' ? 'v4-dock-msg--ivy' : 'v4-dock-msg--user'" x-text="message.text"></div>
            </template>
        </div>

        <div class="v4-dock-quick">
            <template x-for="question in Object.keys(answers)" :key="question">
                <button type="button" x-text="question" @click="ask(question)"></button>
            </template>
        </div>

        <form class="v4-dock-form" @submit.prevent="send()">
            <input x-model="draft" placeholder="Stel je vraag aan Ivy…" aria-label="Stel je vraag aan Ivy">
            <button type="submit" class="v4-dock-send" aria-label="Verstuur"><x-v4.icon name="send" :size="13" stroke="2.4" /></button>
        </form>
    </section>

    <button type="button" class="v4-dock-open" aria-label="Open Ivy, jouw AI-hulp" :aria-expanded="open" @click="open = ! open; open && scrollLog()">
        <img src="{{ asset('images/v4/ivy-avatar.jpg') }}" alt="">
        <i aria-hidden="true"></i>
    </button>
</div>
