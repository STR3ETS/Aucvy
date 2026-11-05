/*
 | Een geopend gesprek op de berichtenpagina: nieuwe berichten komen binnen
 | via Reverb als die er is, en anders elke tien seconden. Staat hier (en
 | niet meer in de pagina zelf) zodat wisselen tussen gesprekken zonder
 | herladen kan; zie messages-nav.js.
 */
export default function conversationThread(id, lastId, pollUrl) {
    return {
        lastId,
        timer: null,

        init() {
            if (window.Echo) {
                window.Echo.private(`conversation.${id}`).listen('.message.posted', () => this.fetch());
            }

            this.timer = setInterval(() => this.fetch(), 10000);

            // Het laatste bericht in beeld, zonder dat de hele pagina meeschuift.
            this.$nextTick(() => {
                const list = this.$refs.list;
                if (list) list.scrollTop = list.scrollHeight;
            });
        },

        async fetch() {
            try {
                const response = await fetch(`${pollUrl}?after=${this.lastId}`, { headers: { Accept: 'application/json' } });
                if (! response.ok) return;

                const data = await response.json();

                for (const message of data.messages) {
                    if (this.$refs.list.querySelector(`[data-message-id="${message.id}"]`)) continue;

                    this.$refs.list.insertAdjacentHTML('beforeend', message.html);
                    this.lastId = Math.max(this.lastId, message.id);
                }
            } catch (error) {
                // Een gemiste ronde is niet erg; de volgende haalt het in.
            }
        },

        destroy() {
            clearInterval(this.timer);
            window.Echo?.leave(`conversation.${id}`);
        },
    };
}
