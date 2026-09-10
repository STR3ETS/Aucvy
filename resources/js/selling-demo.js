/*
 | De systeemdemo op de verkooproute: zes scenes die de echte interface in
 | het klein naspelen. Dit component regelt alleen de regie: welke scene
 | speelt, de voortgang, en de tel- en typeffecten die CSS niet kan.
 |
 | De micro-animaties per scene zijn CSS-keyframes (app.css, .demo-*);
 | doordat scenes via template x-if opnieuw gemonteerd worden, spelen ze
 | bij elke beurt opnieuw af. Bij verminderde beweging staat alles stil en
 | bladert de bezoeker zelf.
 */
const SCENE_MS = 7000;

export default function sellingDemo() {
    return {
        scene: 0,
        total: 6,
        playing: true,
        reduce: false,
        progress: 0,
        price: 15000,
        payout: 0,
        typedTitle: '',
        frame: null,
        last: null,
        fx: [],

        init() {
            this.reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (this.reduce) {
                this.playing = false;

                return;
            }

            this.enter(0);
            this.last = performance.now();

            const tick = (now) => {
                if (this.playing) {
                    this.progress += ((now - this.last) / SCENE_MS) * 100;

                    if (this.progress >= 100) {
                        this.go((this.scene + 1) % this.total, true);
                    }
                }

                this.last = now;
                this.frame = requestAnimationFrame(tick);
            };

            this.frame = requestAnimationFrame(tick);
        },

        destroy() {
            if (this.frame !== null) cancelAnimationFrame(this.frame);
            this.clearFx();
        },

        go(index, automatic = false) {
            this.scene = index;
            this.progress = 0;
            this.enter(index);

            if (! automatic && this.reduce) {
                // Handmatig bladeren zonder beweging: toon de eindstand.
                this.typedTitle = 'CNC-freesbank Haas VF-2 (2019)';
                this.price = 16750;
                this.payout = 15494;
            }
        },

        toggle() {
            this.playing = ! this.playing;
            this.last = performance.now();
        },

        enter(index) {
            this.clearFx();

            if (index === 2) this.type('CNC-freesbank Haas VF-2 (2019)', 600);
            if (index === 4) this.count('price', 15000, 16750, 2400, 2200);
            if (index === 5) this.count('payout', 0, 15494, 1800, 1600);
        },

        clearFx() {
            this.fx.forEach(clearTimeout);
            this.fx = [];
            this.typedTitle = '';
            this.price = 15000;
            this.payout = 0;
        },

        type(text, delay) {
            let length = 0;

            const step = () => {
                this.typedTitle = text.slice(0, ++length);

                if (length < text.length) {
                    this.fx.push(setTimeout(step, 35));
                }
            };

            this.fx.push(setTimeout(step, delay));
        },

        count(property, from, to, durationMs, delay) {
            const start = () => {
                const begin = performance.now();

                const step = () => {
                    const fraction = Math.min(1, (performance.now() - begin) / durationMs);
                    const eased = 1 - Math.pow(1 - fraction, 3);
                    this[property] = Math.round(from + (to - from) * eased);

                    if (fraction < 1) {
                        this.fx.push(setTimeout(step, 40));
                    }
                };

                step();
            };

            this.fx.push(setTimeout(start, delay));
        },

        euro(amount) {
            return '€ ' + amount.toLocaleString('nl-NL');
        },
    };
}
