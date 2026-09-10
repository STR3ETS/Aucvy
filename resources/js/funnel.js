/**
 * De verkoopfunnel.
 *
 * Drie afspraken uit de briefing (hfst. 13.4) zitten hier in de structuur:
 *
 *  - het concept ontstaat pas bij de eerste invoer en hangt aan een
 *    cookie-token - geen account nodig tot het publiceren;
 *  - autosave is afgeknepen tot één call per 800 ms, en elke respons is de
 *    waarheid: wat de server terugstuurt overschrijft de lokale staat;
 *  - de compleetheidsscore komt van de server (geteld in PHP), nooit uit
 *    deze component.
 */
export default function funnel(initial, feePercentage) {
    return {
        draft: initial.draft,
        completeness: initial.completeness,
        loggedIn: initial.logged_in,
        fee: feePercentage,

        step: 0,
        saving: false,
        saved: false,
        error: null,
        uploading: false,

        // Ivy (stap 3): de drie taken lopen na elkaar; elke afgeronde run
        // komt als notitie in de lijst. `failed` betekent: verder zonder Ivy.
        ivy: { running: false, current: null, notes: [] },

        _pending: {},
        _timer: null,
        _creating: null,

        steps: [
            'Wat', 'Aanleveren', 'Analyse', 'Advertentie',
            'Vorm', 'Promotie', 'Kosten', 'Live',
        ],

        get meta() {
            return this.draft?.meta ?? {};
        },

        // Autosave -----------------------------------------------------------

        set(field, value) {
            this._pending[field] = value;

            // Direct lokaal bijwerken zodat de invoer niet "terugspringt"
            // voordat de server geantwoord heeft.
            if (this.draft) {
                const metaFields = [
                    'sale_type', 'start_price_cents', 'reserve_price_cents',
                    'buy_now_price_cents', 'dutch_start_cents', 'dutch_floor_cents',
                    'dutch_step_cents', 'dutch_interval_s', 'duration_days',
                ];

                metaFields.includes(field)
                    ? (this.draft.meta = { ...this.meta, [field]: value })
                    : (this.draft[field] = value);
            }

            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.flush(), 800);
        },

        setEuro(field, value) {
            const euros = parseFloat(String(value).replace(',', '.'));

            this.set(field, Number.isFinite(euros) ? Math.round(euros * 100) : null);
        },

        euro(cents) {
            return cents == null ? '' : (cents / 100).toString().replace('.', ',');
        },

        async flush() {
            if (Object.keys(this._pending).length === 0) return;

            await this.ensureDraft();

            const payload = this._pending;
            this._pending = {};
            this.saving = true;

            const response = await this.request(`/api/drafts/${this.draft.id}`, 'PATCH', payload);

            this.saving = false;

            if (response) {
                this.apply(response);
                this.saved = true;
                setTimeout(() => (this.saved = false), 2000);
            }
        },

        /**
         * Het concept ontstaat bij de eerste invoer. De promise wordt gedeeld,
         * zodat twee snelle wijzigingen niet twee concepten maken.
         */
        async ensureDraft() {
            if (this.draft) return;

            this._creating ??= this.request('/api/drafts', 'POST', {}).then((response) => {
                if (response) this.apply(response);
            });

            await this._creating;
        },

        apply(response) {
            this.draft = response.draft;
            this.completeness = response.completeness;
            this.loggedIn = response.logged_in;
        },

        // Uploads ------------------------------------------------------------

        async uploadPhotos(files) {
            await this.ensureDraft();
            this.uploading = true;

            for (const file of files) {
                const body = new FormData();
                body.append('file', file);

                const response = await this.request(`/api/drafts/${this.draft.id}/media`, 'POST', body);

                if (response) {
                    this.draft.media.push(response.media);
                    this.completeness = response.completeness;
                }
            }

            this.uploading = false;
        },

        async removePhoto(media) {
            const response = await this.request(
                `/api/drafts/${this.draft.id}/media/${media.id}`, 'DELETE', {},
            );

            if (response) {
                this.draft.media = this.draft.media.filter((item) => item.id !== media.id);
                this.completeness = response.completeness;
            }
        },

        async uploadDocument(file, kind) {
            await this.ensureDraft();
            this.uploading = true;

            const body = new FormData();
            body.append('file', file);
            body.append('kind', kind);

            const response = await this.request(`/api/drafts/${this.draft.id}/documents`, 'POST', body);

            if (response) {
                this.draft.documents.push(response.document);
                this.completeness = response.completeness;
            }

            this.uploading = false;
        },

        // Ivy ----------------------------------------------------------------

        /**
         * De analyse van stap 3: herkennen, advertentie schrijven, waarde
         * schatten - ná elkaar, want elke taak bouwt voort op wat de vorige
         * invulde. Elke geslaagde run stuurt de verse conceptstand mee en
         * die overschrijft de lokale staat, net als bij autosave.
         *
         * Faalt of skipt er iets (noodrem, budget), dan stopt de reeks en
         * vult de verkoper zelf in - Ivy is nooit een blokkade (hfst. 8).
         */
        async analyse() {
            if (this.ivy.running) return;

            clearTimeout(this._timer);
            await this.flush();
            await this.ensureDraft();

            this.ivy = { running: true, current: null, notes: [] };

            const tasks = ['recognise_lot', 'draft_listing', 'estimate_value'];

            // Documentcontrole alleen als er iets te controleren valt.
            if ((this.draft?.documents ?? []).length > 0) tasks.push('check_documents');

            for (const task of tasks) {
                this.ivy.current = task;

                const started = await this.request('/api/ivy/tasks', 'POST', {
                    task,
                    lot_id: this.draft.id,
                });

                if (! started) break;

                const run = await this.awaitRun(started.run);
                this.ivy.notes.push(run);

                // Noodrem of budget: de volgende taken gaan hetzelfde zeggen.
                if (run.status === 'skipped') break;
            }

            this.ivy.running = false;
            this.ivy.current = null;
        },

        /** Pollen tot de run klaar is; de taken zelf draaien op de queue. */
        async awaitRun(run) {
            for (let attempt = 0; attempt < 60; attempt++) {
                if (['completed', 'failed', 'skipped'].includes(run.status)) return run;

                await new Promise((resolve) => setTimeout(resolve, 1500));

                const response = await this.request(`/api/ivy/tasks/${run.id}`, 'GET');

                if (! response) break;

                run = response.run;

                if (run.status === 'completed' && response.draft) this.apply(response);
            }

            if (! ['completed', 'failed', 'skipped'].includes(run.status)) {
                return { ...run, status: 'failed', error: 'Dit duurde te lang.' };
            }

            return run;
        },

        ivyLabel(task) {
            return {
                recognise_lot: 'Object herkennen',
                draft_listing: 'Advertentie schrijven',
                estimate_value: 'Waarde schatten',
                check_documents: 'Documenten controleren',
            }[task] ?? task;
        },

        ivyNote(run) {
            if (run.status === 'completed') {
                // De documentcontrole heeft een eigen samenvatting (Lex).
                if (run.task === 'check_documents' && run.output?.summary) {
                    return run.output.summary;
                }

                return run.confidence_hint ?? 'klaar.';
            }

            if (run.status === 'skipped') return run.error ?? 'overgeslagen.';

            return 'niet gelukt - vul dit zelf in bij de volgende stap.';
        },

        // Publiceren ---------------------------------------------------------

        async publish() {
            // Eerst alles wegschrijven wat nog in de wachtrij staat.
            clearTimeout(this._timer);
            await this.flush();
            await this.ensureDraft();

            const response = await this.request(`/api/drafts/${this.draft.id}/publish`, 'POST', {});

            if (response?.published) {
                window.location.href = response.url;
            } else if (response?.login_url) {
                // De accountvraag komt hier, niet eerder. Het concept blijft
                // op het cookie staan en gaat na het inloggen mee op naam.
                window.location.href = response.login_url;
            }
        },

        // Vervoer ------------------------------------------------------------

        async request(url, method, body) {
            this.error = null;

            try {
                const isForm = body instanceof FormData;

                const response = await fetch(url, {
                    method: isForm ? 'POST' : method,
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        ...(isForm || method === 'GET' ? {} : { 'Content-Type': 'application/json' }),
                        ...(isForm && method === 'DELETE' ? { 'X-HTTP-Method-Override': 'DELETE' } : {}),
                    },
                    // Een GET met een body is ongeldig voor fetch.
                    body: isForm ? body : (method === 'GET' ? undefined : JSON.stringify(body)),
                });

                const data = await response.json();

                if (response.status === 401 && data.login_url) return data;

                if (! response.ok) {
                    // De rate limiter praat Engels; de gebruiker niet.
                    this.error = response.status === 429
                        ? 'Even rustig aan - probeer het over een minuut opnieuw.'
                        : data.message
                            ?? Object.values(data.errors ?? {}).flat()[0]
                            ?? 'Er ging iets mis. Probeer het opnieuw.';

                    return null;
                }

                return data;
            } catch {
                this.error = 'Geen verbinding. Je wijzigingen staan nog klaar - probeer het zo opnieuw.';

                return null;
            }
        },
    };
}
