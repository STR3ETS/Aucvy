/*
 | Een lange lijst rustig houden: de eerste rijen staan open, de rest zit
 | achter "Meer tonen". Filteren (een groep per rij) en zoeken gebeuren in de
 | browser, want de lijst is al geladen.
 |
 | rows: [{ id, g: groep of lijst van groepen, t: zoektekst in kleine letters }]
 | In de rij: x-show="ids[<id>]". De knop: x-show="hidden > 0" @click="all = true".
 */
export default function shortList({ rows = [], filter = 'alle', limit = 10 } = {}) {
    return {
        rows,
        filter,
        limit,
        q: '',
        all: false,
        ids: {},
        total: 0,

        init() {
            this.refresh();
            this.$watch('filter', () => {
                this.all = false;
                this.refresh();
            });
            this.$watch('q', () => this.refresh());
            this.$watch('all', () => this.refresh());
        },

        refresh() {
            const q = this.q.trim().toLowerCase();
            const inGroup = (row) => this.filter === 'alle' || (Array.isArray(row.g) ? row.g.includes(this.filter) : row.g === this.filter);
            const match = this.rows.filter((row) => inGroup(row) && (q === '' || (row.t ?? '').includes(q)));

            this.total = match.length;
            this.ids = Object.fromEntries(match.slice(0, this.all ? match.length : this.limit).map((row) => [row.id, true]));
        },

        // Hoeveel rijen nog achter "Meer tonen" zitten.
        get hidden() {
            return Math.max(0, this.total - Object.keys(this.ids).length);
        },

        // Een rij die uit de lijst verdwijnt (verborgen, verwijderd) telt niet meer mee.
        drop(id) {
            this.rows = this.rows.filter((row) => row.id !== id);
            this.refresh();
        },
    };
}
