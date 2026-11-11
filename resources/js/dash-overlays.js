/*
 | Twee lagen over het dashboard, geopend vanuit de bovenbalk:
 |
 | commandPalette: het zoekvenster (Ctrl K of een klik op het zoekveld). Eén
 | veld dat door pagina's, eigen kavels, bestellingen, veilingen en het
 | aanbod zoekt. Pijltjes kiezen, Enter opent, Escape sluit.
 |
 | notifDrawer: de meldingen als paneel dat rechts uitschuift. De inhoud komt
 | als fragment van de server, zodat de pictogrammen en de tijd daar kloppen.
 */

const headers = () => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
});

export function commandPalette(config) {
    return {
        open: false,
        q: '',
        groups: [],
        active: 0,
        loading: false,
        failed: false,
        timer: null,
        controller: null,

        init() {
            window.addEventListener('keydown', (event) => {
                if ((event.ctrlKey || event.metaKey) && ! event.shiftKey && ! event.altKey && event.key.toLowerCase() === 'k') {
                    event.preventDefault();
                    if (this.open) this.close(); else this.show();
                }
            });
        },

        // Alle treffers op een rij, zodat de pijltjes door de groepen heen lopen.
        get hits() {
            return this.groups.flatMap((group) => group.hits);
        },

        // De plek van een treffer in die rij.
        position(groupIndex, hitIndex) {
            return this.groups.slice(0, groupIndex).reduce((sum, group) => sum + group.hits.length, 0) + hitIndex;
        },

        show() {
            this.open = true;
            this.q = '';
            this.search();
            this.$nextTick(() => this.$refs.input?.focus());
        },

        close() {
            this.open = false;
            this.controller?.abort();
        },

        onInput() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.search(), 140);
        },

        async search() {
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            this.failed = false;

            try {
                const response = await fetch(config.url + '?q=' + encodeURIComponent(this.q.trim()), { headers: headers(), signal: this.controller.signal });
                if (! response.ok) throw new Error('zoeken mislukt');

                this.groups = (await response.json()).groups ?? [];
                this.active = 0;
            } catch (error) {
                if (error.name === 'AbortError') return;

                this.groups = [];
                this.failed = true;
            }

            this.loading = false;
        },

        move(step) {
            const count = this.hits.length;
            if (! count) return;

            this.active = (this.active + step + count) % count;
            this.$nextTick(() => this.$refs.list?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' }));
        },

        choose() {
            const hit = this.hits[this.active];
            if (hit) this.follow(hit);
        },

        // Een treffer opent zijn pagina; de vraag aan Ivy gaat naar het gesprek rechtsonder.
        follow(hit) {
            if (hit.ask) {
                this.close();
                window.dispatchEvent(new CustomEvent('ivy-open', { detail: { text: hit.ask, agent: 'ivy' } }));

                return;
            }

            // Meldingen is geen pagina meer: de treffer schuift de lade uit.
            if (hit.url && config.notifications && new URL(hit.url, window.location.href).pathname === config.notifications) {
                this.close();
                window.dispatchEvent(new CustomEvent('notifs-open'));

                return;
            }

            if (hit.url) window.location.href = hit.url;
        },

        icon(name) {
            return config.icons[name] ?? config.icons.layout ?? '';
        },
    };
}

export function notifDrawer(config) {
    return {
        open: false,
        loading: false,
        html: '',
        failed: false,
        more: false,
        loadingMore: false,

        init() {
            this.openFromUrl();
            document.addEventListener('dash-nav:swapped', () => this.openFromUrl());

            // Een link naar de oude pagina Meldingen opent de lade, zonder de pagina te verlaten.
            document.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

                const link = event.target.closest?.('a[href]');
                if (! link || link.target === '_blank') return;

                const url = new URL(link.href, window.location.href);
                if (url.origin !== window.location.origin || url.pathname !== config.page) return;

                event.preventDefault();
                event.stopPropagation();
                this.show();
            }, true);
        },

        // Wie via een oude link binnenkomt (?meldingen=1) krijgt de lade meteen open.
        openFromUrl() {
            const url = new URL(window.location.href);
            if (! url.searchParams.has('meldingen')) return;

            url.searchParams.delete('meldingen');
            window.history.replaceState(window.history.state, '', url);
            this.show();
        },

        async show() {
            this.open = true;
            this.loading = this.html === '';
            this.failed = false;

            await this.load(config.url);

            this.loading = false;
        },

        // De oudere meldingen erbij, in dezelfde lade.
        async showAll() {
            this.loadingMore = true;
            await this.load(config.url + '?alles=1');
            this.loadingMore = false;
        },

        async load(url) {
            try {
                const response = await fetch(url, { headers: { ...headers(), Accept: 'text/html' } });
                if (! response.ok) throw new Error('laden mislukt');

                this.html = await response.text();
                this.more = this.html.includes('data-more="1"');
            } catch (error) {
                this.failed = true;
            }
        },

        close() {
            this.open = false;
        },
    };
}
