/*
 | Het admin-overzicht ververst zichzelf: cijfers en de activiteitenfeed
 | komen elke paar seconden vers van de server, zonder reload. Zo ziet
 | beheer dezelfde levendigheid als een bezoeker op de kavelpagina.
 */
export default function adminLive(url) {
    return {
        init() {
            this._timer = setInterval(() => this.refresh(), 7000);
        },

        destroy() {
            clearInterval(this._timer);
        },

        async refresh() {
            if (document.hidden) return;

            try {
                const response = await fetch(url, { headers: { Accept: 'text/html' } });
                if (!response.ok) return;

                this.$root.innerHTML = await response.text();
            } catch {
                // Netwerkhik: volgende tick probeert het gewoon opnieuw.
            }
        },
    };
}
