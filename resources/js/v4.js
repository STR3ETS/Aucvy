/*
 | De interactie van variant B van de A/B-test op de home (preview v4): de
 | productdemo in de hero, de twee verhalen, de Ivy-tabs, de Ivy-dock, het
 | navigatiemenu en de reveals bij het scrollen.
 |
 | Het ontwerp is een React-bundel met framer-motion; dit is dezelfde regie
 | in Alpine. Elk component heeft één klok: de voortgang van de huidige
 | scene loopt van 0 naar 1, en elementen verschijnen zodra de voortgang hun
 | drempel passeert (at(.45)). Tellers interpoleren tussen twee waarden over
 | een deel van de scene (lerp(1800, 2500, .25, .7)).
 |
 | Bij verminderde beweging staat alles stil, is elke scene compleet
 | zichtbaar en bladert de bezoeker zelf.
 */

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/*
 | Een klok uitbreiden met eigen velden en getters. Niet met spread of
 | Object.assign: die lezen een getter uit op het moment van kopiëren en
 | zetten de uitkomst (0) als vaste waarde neer. De voortgang zou dan nooit
 | doorkomen in de view.
 */
const extend = (base, extension) => Object.defineProperties(base, Object.getOwnPropertyDescriptors(extension));

/**
 * De gedeelde klok: scene, voortgang, afspelen en pauzeren. Loopt alleen
 * terwijl het component in beeld is; een demo onder de vouw kost niets.
 */
function sceneClock({ total, sceneMs, holdMs = 0 }) {
    return {
        scene: 0,
        progress: 0,
        playing: true,
        reduce: false,
        visible: false,
        frame: null,
        last: null,
        total,

        init() {
            this.reduce = reducedMotion();

            if (this.reduce) {
                this.playing = false;
                this.progress = 1;

                return;
            }

            if ('IntersectionObserver' in window) {
                new IntersectionObserver((entries) => {
                    this.visible = entries[0].isIntersecting;
                }, { threshold: 0.3 }).observe(this.$el);
            } else {
                this.visible = true;
            }

            this.last = performance.now();

            const tick = (now) => {
                if (this.playing && this.visible) {
                    this.progress += (now - this.last) / sceneMs;

                    if (this.progress >= 1 + holdMs / sceneMs) {
                        this.scene = (this.scene + 1) % this.total;
                        this.progress = 0;
                    }
                }

                this.last = now;
                this.frame = requestAnimationFrame(tick);
            };

            this.frame = requestAnimationFrame(tick);
        },

        destroy() {
            if (this.frame !== null) cancelAnimationFrame(this.frame);
        },

        get p() {
            return this.reduce ? 1 : Math.min(1, this.progress);
        },

        at(threshold) {
            return this.p >= threshold;
        },

        lerp(from, to, start, end) {
            const k = Math.min(1, Math.max(0, (this.p - start) / (end - start)));

            return from + (to - from) * k;
        },

        euro(amount) {
            return '€ ' + Math.round(amount).toLocaleString('nl-NL');
        },

        // Een stap zelf kiezen pauzeert: wie klikt, wil kijken.
        go(index) {
            this.scene = index;
            this.progress = this.reduce ? 1 : 0;
            this.playing = false;
        },

        toggle() {
            if (this.reduce) return;

            this.playing = !this.playing;
        },

        next() {
            this.go((this.scene + 1) % this.total);
        },

        previous() {
            this.go((this.scene - 1 + this.total) % this.total);
        },
    };
}

/**
 * De live productdemo in de hero: negen scenes voor verkopen, negen voor
 * kopen. De inhoud per scene staat in de Blade-view; hier alleen de regie.
 */
export function v4Demo() {
    const labels = {
        seller: ['Upload', 'Herkenning', 'Advertentie', 'Waarde & strategie', 'Social formats', 'Publiceren', 'Kopers matchen', 'Biedingen', 'Verkocht'],
        buyer: ['Zoekvraag', 'Criteria', 'Matches', 'Vergelijken', 'Onderzoek', 'Voorspelling', 'Biedstrategie', 'Live advies', 'Betalen & ontvangen'],
    };

    return extend(sceneClock({ total: 9, sceneMs: 4200, holdMs: 1600 }), {
        mode: 'seller',
        labels,

        get label() {
            return this.labels[this.mode][this.scene];
        },

        switchMode(mode) {
            this.mode = mode;
            this.scene = 0;
            this.progress = this.reduce ? 1 : 0;
            this.playing = !this.reduce;
        },

        typed(text, start, end) {
            return text.slice(0, Math.round(this.lerp(0, text.length, start, end)));
        },
    });
}

/**
 * De verhalen (verkopers, kopers): stappen links, het paneel rechts. De
 * stappen komen als data uit Blade; het paneel rendert de rijen van de
 * actieve stap.
 */
export function v4Story({ steps = [] } = {}) {
    return extend(sceneClock({ total: steps.length, sceneMs: 5200 }), {
        steps,

        get step() {
            return this.steps[this.scene];
        },
    });
}

/**
 * De Ivy-tabs: per taak een vraag, een antwoord en een resultaatkaart.
 * Dezelfde timing als het ontwerp: vraag meteen, typen tussen 10% en 30%,
 * antwoord vanaf 30%, resultaat vanaf 38%.
 */
export function v4Engine({ scenes = [] } = {}) {
    return extend(sceneClock({ total: scenes.length, sceneMs: 7200 }), {
        scenes,

        get current() {
            return this.scenes[this.scene];
        },

        get showAsk() {
            return this.reduce || !this.playing || this.p > 0.04;
        },

        get showTyping() {
            return !this.reduce && this.playing && this.p > 0.1 && this.p < 0.3;
        },

        get showReply() {
            return this.reduce || !this.playing || this.p >= 0.3;
        },

        get showResult() {
            return this.reduce || !this.playing || this.p >= 0.38;
        },
    });
}

/**
 * De Ivy-dock rechtsonder. In deze demo beantwoordt hij de vier vaste
 * vragen met een vast antwoord; vrije tekst stuurt naar het zoekveld van de
 * veilingenpagina, waar Ivy de vraag wél uitlegt.
 */
export function v4Dock({ searchUrl = '/veilingen' } = {}) {
    return {
        open: false,
        draft: '',
        messages: [],
        answers: {
            'Hoe werkt verkopen?': 'Je uploadt foto’s, Ivy herkent het object, schrijft de advertentie en geeft een waarde-indicatie. Jij kiest de verkoopvorm en keurt goed. Plaatsen is gratis, je betaalt alleen bij verkoop.',
            'Wat kost het?': 'Plaatsen is gratis en kopers betalen geen opgeld. Bij een verkoop houden we een succesfee in; het percentage staat vooraf per kavel in de kostenopbouw.',
            'Zoek iets voor mij': 'Typ wat je zoekt in gewone taal, bijvoorbeeld “20 bureaustoelen onder € 4.000”. Ik vertaal dat naar filters op de veilingenpagina.',
            'Is bieden veilig?': 'Verkopers zijn geverifieerd (KVK en identiteit), kosten staan vooraf bij elkaar en bij een geschil bemiddelt Aucvy. Wat er nog niet geregeld is, staat eerlijk op de zekerheidspagina.',
        },

        init() {
            if (this.messages.length === 0) {
                this.messages.push({ from: 'ivy', text: 'Hoi! Waar kan ik je mee helpen?' });
            }
        },

        ask(question) {
            this.messages.push({ from: 'user', text: question });
            this.messages.push({ from: 'ivy', text: this.answers[question] });
            this.scrollLog();
        },

        send() {
            const text = this.draft.trim();

            if (text === '') return;

            if (this.answers[text]) {
                this.draft = '';
                this.ask(text);

                return;
            }

            window.location.href = searchUrl + '?q=' + encodeURIComponent(text);
        },

        scrollLog() {
            this.$nextTick(() => {
                const log = this.$refs.log;

                if (log) log.scrollTop = log.scrollHeight;
            });
        },
    };
}

/**
 * De navigatie: één uitklapmenu tegelijk op desktop, een sheet op mobiel.
 */
export function v4Nav() {
    return {
        menu: null,
        sheet: false,

        toggleMenu(key) {
            this.menu = this.menu === key ? null : key;
        },

        close() {
            this.menu = null;
        },
    };
}

/**
 * Scroll-reveals: zodra een .v4-reveal in beeld komt, krijgt hij is-in en
 * schuift hij omhoog. Eén keer; terugscrollen laat niets opnieuw bewegen.
 */
export function initV4Reveal() {
    const elements = document.querySelectorAll('.v4-reveal');

    if (elements.length === 0) return;

    if (reducedMotion() || !('IntersectionObserver' in window)) {
        elements.forEach((element) => element.classList.add('is-in'));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px' });

    elements.forEach((element) => observer.observe(element));
}
