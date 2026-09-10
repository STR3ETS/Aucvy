/**
 * De veilingstand, op één plek.
 *
 * Componenten lezen hieruit; niemand houdt een eigen kopie bij. Dat voorkomt
 * de klassieke fout waarbij het biedpaneel iets anders toont dan de teller
 * ernaast (hfst. 11).
 */
export default function auction(initialState) {
    return {
        state: initialState,
        history: initialState.history ?? [],

        /** live | closing | closed | reconnecting | stale */
        connection: 'live',

        /**
         * Verschil tussen server- en clientklok, in milliseconden.
         *
         * Een telefoon die tien seconden voorloopt is normaal; een aftelling
         * die daardoor fout staat is dat niet.
         */
        clockOffset: 0,

        countdownLabel: '',
        pendingBid: null,
        error: null,
        announcement: '',

        _lastAnnouncedAt: 0,
        _pollTimer: null,
        _failedReconnects: 0,

        init() {
            this.clockOffset = new Date(this.state.server_time).getTime() - Date.now();

            this.tick();
            setInterval(() => this.tick(), 1000);

            this.subscribe();
            this.startPresence();
        },

        /*
         | De kijkershartslag: dit tabblad meldt zich elke dertig seconden met
         | een willekeurig token. De teller komt terug en vervangt de stand -
         | geen namen, alleen een aantal (hfst. 7).
         */
        startPresence() {
            const token = crypto.randomUUID?.() ?? Math.random().toString(36).slice(2, 18);

            const beat = async () => {
                if (document.hidden) return;

                try {
                    const response = await fetch(`/api/auctions/${this.state.auction_id}/presence`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                        },
                        body: JSON.stringify({ token }),
                    });

                    if (response.ok) {
                        this.state.watching_now = (await response.json()).watching_now;
                    }
                } catch {
                    // Stilte is prima: de teller is nice-to-have, geen kernfunctie.
                }
            };

            beat();
            setInterval(beat, 30_000);
        },

        // Tijd ---------------------------------------------------------------

        now() {
            return Date.now() + this.clockOffset;
        },

        secondsLeft() {
            return Math.max(0, Math.floor((new Date(this.state.ends_at).getTime() - this.now()) / 1000));
        },

        get status() {
            if (this.state.status === 'closed') return 'closed';

            return this.secondsLeft() <= this.state.anti_snipe_window_seconds ? 'closing' : 'live';
        },

        tick() {
            this.countdownLabel = this.formatCountdown(this.secondsLeft());

            // Bij een afslag telt de client zelf af met dezelfde formule als de
            // server. Er wordt niets per tik weggeschreven (hfst. 6).
            if (this.state.dutch && this.state.status !== 'closed') {
                this.state.price_cents = this.dutchPriceNow();
            }

            // Op nul overgekomen: de sluiting komt via de websocket, maar als
            // die weg is willen we niet eeuwig "nog 0 seconden" tonen.
            if (this.secondsLeft() === 0 && this.state.status !== 'closed') {
                this.refreshState();
            }
        },

        formatCountdown(seconds) {
            if (this.state.status === 'closed') return 'Gesloten';

            const days = Math.floor(seconds / 86400);
            if (days >= 1) return `nog ${days} ${days === 1 ? 'dag' : 'dagen'}`;

            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            if (hours >= 1) return `nog ${hours}u ${String(minutes).padStart(2, '0')}m`;
            if (minutes >= 1) return `nog ${minutes}:${String(secs).padStart(2, '0')}`;

            return `nog ${secs}s`;
        },

        /**
         * Dezelfde formule als de server:
         *
         *     max(bodem, start − stap × floor((nu − start_tijd) / interval))
         *
         * De server rekent bij acceptatie opnieuw en dát bedrag geldt; dit is
         * alleen wat het scherm toont (hfst. 6).
         */
        dutchPriceNow() {
            const d = this.state.dutch;
            const elapsed = Math.floor((this.now() - new Date(d.starts_at).getTime()) / 1000);

            if (elapsed <= 0) return d.start_cents;

            const steps = Math.floor(elapsed / d.interval_seconds);

            return Math.max(d.floor_cents, d.start_cents - steps * d.step_cents);
        },

        // Verbinding ---------------------------------------------------------

        subscribe() {
            if (typeof window.Echo === 'undefined') {
                // Zonder websocket blijft alles werken, alleen trager.
                this.startPolling();
                return;
            }

            window.Echo.channel(`auction.${this.state.auction_id}`)
                .listen('.bid.placed', (event) => this.applyEvent(event, () => {
                    this.state.price_cents = event.amount_cents;
                    this.state.ends_at = event.ends_at;
                    this.state.bid_count = event.bid_count;
                    this.state.bidder_count = event.bidder_count;
                    this.history = [event, ...this.history];
                    this.announce(`Nieuw bod: ${this.formatCents(event.amount_cents)}`);
                }))
                .listen('.auction.extended', (event) => this.applyEvent(event, () => {
                    this.state.ends_at = event.ends_at;
                    this.state.extension_count = event.extension_count;
                    this.announce('De veiling is verlengd door een bod in de slotfase.');
                }))
                .listen('.buynow.disabled', (event) => this.applyEvent(event, () => {
                    if (this.state.buy_now) this.state.buy_now.active = false;
                }))
                .listen('.auction.closed', (event) => this.applyEvent(event, () => {
                    this.state.status = 'closed';
                    this.announce('De veiling is gesloten.');
                    this.refreshState();
                }));
        },

        /**
         * Elk event draagt een volgnummer. Komt er 434 binnen terwijl je 431
         * had, dan ontbreken er twee en klopt de stand niet meer.
         */
        applyEvent(event, apply) {
            const expected = this.state.sequence + 1;

            if (event.sequence === expected) {
                this.state.sequence = event.sequence;
                apply();
                return;
            }

            if (event.sequence <= this.state.sequence) {
                // Al verwerkt; dit gebeurt na een replay.
                return;
            }

            this.resync();
        },

        /**
         * Het gat dichten. Lukt dat niet, dan gaat de stand naar `stale` en
         * wordt het biedpaneel geblokkeerd. Blokkeren voelt streng en is
         * precies goed: bieden op een verouderde stand kost geld (hfst. 11).
         */
        async resync() {
            try {
                const response = await fetch(
                    `/api/auctions/${this.state.auction_id}/events?after=${this.state.sequence}`,
                    { headers: { Accept: 'application/json' } },
                );

                const data = await response.json();

                if (data.reload_required) {
                    await this.refreshState();
                    return;
                }

                await this.refreshState();
            } catch {
                this.connection = 'stale';
            }
        },

        async refreshState() {
            try {
                const response = await fetch(`/api/auctions/${this.state.auction_id}/state`, {
                    headers: { Accept: 'application/json' },
                });

                if (! response.ok) throw new Error('state');

                this.state = await response.json();
                this.history = this.state.history ?? [];
                this.connection = 'live';
                this._failedReconnects = 0;
            } catch {
                this._failedReconnects++;

                // Na drie mislukte pogingen omschakelen naar pollen, met een
                // zichtbare melding. Nooit stilzwijgend een bevroren scherm.
                this.connection = this._failedReconnects >= 3 ? 'stale' : 'reconnecting';
                this.startPolling();
            }
        },

        startPolling() {
            if (this._pollTimer) return;

            this._pollTimer = setInterval(() => this.refreshState(), 3000);
        },

        get isBlocked() {
            return this.connection === 'stale';
        },

        // Aankondigen --------------------------------------------------------

        /**
         * Afgeknepen tot één melding per drie seconden. Elke bieding voorlezen
         * maakt de pagina onbruikbaar met een schermlezer (hfst. 11).
         */
        announce(message) {
            const now = Date.now();

            if (now - this._lastAnnouncedAt < 3000) return;

            this._lastAnnouncedAt = now;
            this.announcement = message;
        },

        // Acties -------------------------------------------------------------

        async placeBid(amountCents) {
            this.error = null;

            // Wie al bovenaan staat en nógmaals sleept, hoort dat meteen -
            // zonder rondje server.
            if (this.state.viewer?.state === 'highest') {
                this.error = { reason: 'already_highest', message: 'Je bent al de hoogste bieder.' };

                return;
            }

            // Je eigen bod verschijnt direct als "wordt verwerkt". De getóónde
            // prijs verandert pas als de server bevestigt - een optimistische
            // prijs is een prijs waarop iemand een verkeerde beslissing neemt.
            this.pendingBid = amountCents;

            const result = await this.post(`/api/auctions/${this.state.auction_id}/bids`, {
                amount_cents: amountCents,
            });

            this.pendingBid = null;

            if (result) {
                this.state = result.state;
                this.history = result.state.history ?? [];
            } else if (this.error?.reason === 'already_highest') {
                // Race met je eigen automatische maximum: bij het verzenden
                // stond je nog niet bovenaan, inmiddels wel. Dat is het
                // gewenste resultaat, geen fout - dus geen melding.
                this.error = null;
                await this.refreshState();
            }
        },

        async setMaxBid(maxAmountCents) {
            this.error = null;

            const result = await this.post(`/api/auctions/${this.state.auction_id}/auto-bid`, {
                max_amount_cents: maxAmountCents,
            });

            if (result) {
                this.state = result.state;
                this.history = result.state.history ?? [];
            }
        },

        async cancelMaxBid() {
            this.error = null;

            const result = await this.post(
                `/api/auctions/${this.state.auction_id}/auto-bid`,
                {},
                'DELETE',
            );

            if (result) this.state = result.state;
        },

        async acceptDutch() {
            this.error = null;

            const result = await this.post(`/api/auctions/${this.state.auction_id}/dutch-accept`, {
                // De prijs die op dít scherm stond. De server rekent opnieuw.
                seen_price_cents: this.state.price_cents,
            });

            if (result) window.location.reload();
        },

        async buyNow(confirmed) {
            this.error = null;

            const result = await this.post(`/api/auctions/${this.state.auction_id}/buy-now`, {
                confirm: confirmed,
            });

            if (result?.confirmed) window.location.reload();

            return result;
        },

        /**
         * De borg regelen (hfst. 9): boven de drempel eerst een reservering.
         * De server geeft een checkout-url terug; na de reservering kom je
         * terug op de kavel en klopt de stand weer.
         */
        async arrangeDeposit() {
            const result = await this.post(`/api/auctions/${this.state.auction_id}/deposit`, {});

            if (result?.covered) {
                await this.refreshState();
            } else if (result?.checkout_url) {
                window.location.href = result.checkout_url;
            }
        },

        async post(url, body, method = 'POST') {
            try {
                const response = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        // Maakt een dubbele submit of een retry onschadelijk.
                        'Idempotency-Key': crypto.randomUUID(),
                    },
                    body: JSON.stringify(body),
                });

                const data = await response.json();

                if (! response.ok) {
                    this.error = data;

                    // De prijs is verschoven: meteen de nieuwe stand tonen in
                    // plaats van alleen melden dat het misging.
                    if (data.current_price_cents) await this.refreshState();

                    return null;
                }

                return data;
            } catch {
                this.error = { message: 'Geen verbinding. Probeer het opnieuw.' };

                return null;
            }
        },

        // Weergave -----------------------------------------------------------

        formatCents(cents) {
            return '€ ' + (cents / 100).toLocaleString('nl-NL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    };
}
