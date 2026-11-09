/*
 | De twee probeerblokken op de home (ontwerp van november). Allebei een
 | voorbeeld met vaste bedragen: er gaat niets naar de server en er wordt
 | niets gekocht.
 */
const euro = (amount) => '€ ' + amount.toLocaleString('nl-NL');

/**
 * "Wat kan jouw voorraad opleveren?": voorbeeldobjecten aantikken en de
 * waarde-indicaties bij elkaar optellen.
 *
 * @param {Array<{id: string, low: number, high: number}>} items
 * @param {Array<string>} picked
 */
export function valueTry(items, picked) {
    return {
        items,
        picked,

        has(id) {
            return this.picked.includes(id);
        },

        toggle(id) {
            this.picked = this.has(id) ? this.picked.filter((other) => other !== id) : [...this.picked, id];
        },

        get count() {
            return this.picked.length === 1 ? '1 object geselecteerd' : this.picked.length + ' objecten geselecteerd';
        },

        get total() {
            const chosen = this.items.filter((item) => this.has(item.id));

            if (chosen.length === 0) {
                return 'Tik iets aan';
            }

            return euro(chosen.reduce((sum, item) => sum + item.low, 0)) + ' – ' + euro(chosen.reduce((sum, item) => sum + item.high, 0));
        },
    };
}

const FORMS = {
    afslag: {
        badge: 'Afslag',
        text: 'Afslag past als je een datum hebt. De prijs daalt in stappen tot iemand afmijnt, en jij bepaalt de bodem. Probeer het maar.',
        steps: 16,
        button: 'Mijn af',
        stamp: 'Afgemijnd',
    },
    veiling: {
        badge: 'Veiling',
        text: 'Veiling past als je een paar weken hebt. Bieders drijven de prijs op, en een laat bod verlengt de veiling.',
        steps: 14,
        button: 'Sluit de veiling',
        stamp: 'Verkocht',
    },
    direct: {
        badge: 'Koop direct',
        text: 'Koop direct past als je weet wat je wilt krijgen. Eén vaste prijs, zonder wachten op een sluitdatum.',
        steps: 0,
        button: 'Koop direct',
        stamp: 'Verkocht',
    },
};

/**
 * "Drie manieren om te verkopen": één voorbeeldkavel in de drie vormen. Bij
 * afslag daalt de prijs, bij veiling loopt hij op; de knop rondt het af.
 */
export function saleFormDemo() {
    return {
        form: 'afslag',
        step: 0,
        done: false,
        timer: null,

        init() {
            // Bij verminderde beweging loopt de prijs niet vanzelf.
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            this.timer = setInterval(() => {
                if (! this.done && this.step < FORMS[this.form].steps) {
                    this.step++;
                }
            }, 1500);
        },

        destroy() {
            clearInterval(this.timer);
        },

        pick(form) {
            this.form = form;
            this.step = 0;
            this.done = false;
        },

        act() {
            if (this.done) {
                this.step = 0;
            }

            this.done = ! this.done;
        },

        get current() {
            return FORMS[this.form];
        },

        get label() {
            if (this.form === 'afslag') {
                return this.done ? 'Afgemijnd voor' : 'Prijs nu';
            }

            if (this.form === 'veiling') {
                return this.done ? 'Hoogste bod' : 'Huidig bod';
            }

            return 'Vaste prijs';
        },

        get price() {
            if (this.form === 'afslag') {
                return euro(3800 - this.step * 100);
            }

            return euro(this.form === 'veiling' ? 1500 + this.step * 100 : 3100);
        },

        get bar() {
            if (this.form === 'afslag') {
                return 100 - (this.step / 16) * 100 + '%';
            }

            return this.form === 'veiling' ? (this.step / 14) * 100 + '%' : '100%';
        },

        get note() {
            if (this.form === 'afslag') {
                return this.step >= 16 ? 'Bodemprijs bereikt' : 'Daalt € 100 per stap · bodem € 2.200';
            }

            if (this.form === 'veiling') {
                return 'Start € 1.500 · ' + (this.step + 1) + (this.step === 0 ? ' bod' : ' biedingen');
            }

            return 'Meteen te kopen, geen wachttijd';
        },

        get button() {
            return this.done ? 'Nog een keer' : this.current.button;
        },
    };
}
