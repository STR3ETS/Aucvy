/*
 | Het biedformulier op de kavelpagina (voorheen inline x-data in
 | bid-panel.blade.php). Leeft binnen de auction()-scope en leest daar
 | state, isBlocked en de acties uit.
 |
 | De bedragvelden zijn tekstvelden die tijdens het typen meteen in het
 | Nederlandse geldformaat springen: 1000 wordt 1.000, een komma start de
 | centen. De cursor blijft daarbij op zijn plek. De bron van waarheid is
 | de getoonde tekst; amountCents en maxCents rekenen daaruit.
 */
export default function bidForm() {
    return {
        open: false,
        showMax: false,
        amountText: '',
        maxText: '',

        get amountCents() {
            return this.parseCents(this.amountText);
        },

        get maxCents() {
            return this.parseCents(this.maxText);
        },

        get tooLow() {
            return this.amountCents < this.state.minimum_bid_cents;
        },

        get needsDeposit() {
            return ! this.state.viewer?.deposit?.covered
                && this.amountCents > (this.state.viewer?.deposit?.threshold_cents ?? Infinity);
        },

        init() {
            this.setAmountCents(this.state.minimum_bid_cents);

            // Word je overboden, dan stijgt het minimum en zou je oude
            // bedrag het sleepspoor verbergen. Het veld springt mee naar
            // het nieuwe minimum - behalve terwijl je zelf aan het typen bent.
            this.$watch('state.minimum_bid_cents', (minimum) => {
                if (minimum == null) return;

                if (document.activeElement !== this.$refs.amountInput
                    && this.amountCents < minimum) {
                    this.setAmountCents(minimum);
                }
            });
        },

        setAmountCents(cents) {
            this.amountText = this.formatCentsPlain(cents ?? 0);
        },

        /*
         | Geldformaat tijdens het typen: alleen cijfers en één komma blijven
         | over, duizendtallen krijgen een punt, maximaal twee decimalen.
         | De cursor wordt teruggezet na hetzelfde aantal cijfers als waar
         | hij stond - anders springt hij naar het einde zodra er een punt
         | tussengevoegd wordt.
         */
        reformat(event, property) {
            const field = event.target;
            const before = field.value;
            const caret = field.selectionStart ?? before.length;
            const typedBeforeCaret = before.slice(0, caret).replace(/[^\d,]/g, '').length;

            const clean = before.replace(/[^\d,]/g, '');
            const commaIndex = clean.indexOf(',');
            let integerPart = commaIndex === -1 ? clean : clean.slice(0, commaIndex);
            const decimalPart = commaIndex === -1
                ? null
                : clean.slice(commaIndex + 1).replace(/,/g, '').slice(0, 2);
            integerPart = integerPart.replace(/^0+(?=\d)/, '');

            const grouped = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            const text = decimalPart === null ? grouped : grouped + ',' + decimalPart;

            this[property] = text;
            field.value = text;

            let seen = 0;
            let position = text.length;
            for (let index = 0; index < text.length; index++) {
                if (seen >= typedBeforeCaret) {
                    position = index;
                    break;
                }
                if (/[\d,]/.test(text[index])) seen++;
                position = index + 1;
            }
            field.setSelectionRange(position, position);
        },

        parseCents(text) {
            const value = Number.parseFloat((text || '').replace(/\./g, '').replace(',', '.'));

            return Number.isFinite(value) ? Math.round(value * 100) : 0;
        },

        formatCentsPlain(cents) {
            const euros = cents / 100;

            return euros.toLocaleString('nl-NL', {
                minimumFractionDigits: 0,
                maximumFractionDigits: Number.isInteger(euros) ? 0 : 2,
            });
        },
    };
}
