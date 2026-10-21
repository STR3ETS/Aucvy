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
const CHUNK_SIZE = 1024 * 1024;

export default function funnel(initial, feePercentage, schemas = {}) {
    return {
        draft: initial.draft,
        // Kenmerkenschema's per categorie, voor de editor in stap 4.
        schemas,
        // Lopende uploads met voortgang; fouten blijven staan tot ze gelezen zijn.
        uploads: [],
        dragOver: false,
        newSpecKey: '',
        completeness: initial.completeness,
        // Herkomst per veld en de preflight van stap 8: beide van de server,
        // bij elke autosave vers (briefing sectie 5).
        fields: initial.fields ?? {},
        preflight: initial.preflight ?? { ok: false, blocking: [], checks: [] },
        loggedIn: initial.logged_in,
        fee: feePercentage,

        step: 0,
        dictating: null,
        recognition: null,
        scan: { busy: false, result: null, error: null },
        saving: false,
        publishing: false,
        saved: false,
        error: null,
        uploading: false,

        // Ivy (stap 3): de drie taken lopen na elkaar; elke afgeronde run
        // komt als notitie in de lijst. `failed` betekent: verder zonder Ivy.
        ivy: { running: false, current: null, notes: [], sharpened: false },

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
            this.mirror(field, value);

            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.flush(), 800);
        },

        /** Eén veld in de lokale staat zetten, als het concept al bestaat. */
        mirror(field, value) {
            if (! this.draft) return;

            const metaFields = [
                'sale_type', 'start_price_cents', 'reserve_price_cents',
                'buy_now_price_cents', 'dutch_start_cents', 'dutch_floor_cents',
                'dutch_step_cents', 'dutch_interval_s', 'duration_days',
                'ownership_declared',
            ];

            metaFields.includes(field)
                ? (this.draft.meta = { ...this.meta, [field]: value })
                : (this.draft[field] = value);
        },

        setEuro(field, value) {
            // Nederlands: "1.500,50" is vijftienhonderd vijftig, dus eerst de
            // duizendtalpunten weg en dan de komma als decimaalteken.
            const euros = parseFloat(String(value).replace(/\./g, '').replace(',', '.'));

            this.set(field, Number.isFinite(euros) ? Math.round(euros * 100) : null);
        },

        euro(cents) {
            return cents == null ? '' : (cents / 100).toString().replace('.', ',');
        },

        async flush() {
            if (Object.keys(this._pending).length === 0) return;

            await this.ensureDraft();

            // Geen concept (throttle, budget, storing): de invoer blijft in
            // de wachtrij staan voor de volgende poging.
            if (! this.draft) return;

            const payload = this._pending;
            this._pending = {};
            this.saving = true;

            const response = await this.request(`/api/drafts/${this.draft.id}`, 'PATCH', payload);

            this.saving = false;

            if (! response) {
                // Mislukt: wat nog niet opnieuw getypt is gaat terug in de
                // wachtrij, zodat "je wijzigingen staan nog klaar" ook waar is.
                this._pending = { ...payload, ...this._pending };
            }

            if (response) {
                /*
                 | Bewust NIET de hele draft uit de respons overnemen: wie
                 | tijdens een trage opslag verder klikt of typt, zou zijn
                 | nieuwere invoer teruggedraaid zien worden. De velden zijn
                 | van de gebruiker; de server levert alleen wat hij berekent.
                 */
                this.completeness = response.completeness;
                this.fields = response.fields ?? this.fields;
                this.preflight = response.preflight ?? this.preflight;
                this.loggedIn = response.logged_in;
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
                if (response) {
                    this.apply(response);
                } else {
                    // Mislukt: de volgende aanroep probeert het opnieuw in
                    // plaats van eeuwig op deze mislukte belofte te wachten.
                    this._creating = null;
                }
            });

            await this._creating;
        },

        apply(response) {
            this.draft = response.draft;
            this.completeness = response.completeness;
            this.fields = response.fields ?? {};
            this.preflight = response.preflight ?? this.preflight;
            this.loggedIn = response.logged_in;

            // Wat al getypt was voordat het concept bestond, hoort nu in de
            // lokale staat; de server krijgt het bij de eerstvolgende flush.
            for (const [field, value] of Object.entries(this._pending)) {
                this.mirror(field, value);
            }
        },

        /**
         * Alleen wat de server berekent verversen (compleetheid, herkomst,
         * preflight) zonder de invoer van de gebruiker aan te raken.
         */
        async refreshStatus() {
            if (! this.draft) return;

            const response = await this.request(`/api/drafts/${this.draft.id}`, 'GET');

            if (response) {
                this.completeness = response.completeness;
                this.fields = response.fields ?? this.fields;
                this.preflight = response.preflight ?? this.preflight;
            }
        },

        // Herkomst ------------------------------------------------------------

        /** De verkoper bevestigt wat Ivy met weinig zekerheid invulde. */
        async confirm(fields) {
            await this.ensureDraft();

            const response = await this.request(`/api/drafts/${this.draft.id}/confirm`, 'POST', { fields });

            if (response) {
                this.completeness = response.completeness;
                this.fields = response.fields ?? {};
                this.preflight = response.preflight ?? this.preflight;
            }
        },

        async confirmAll() {
            await this.confirm(this.unconfirmed);
        },

        get unconfirmed() {
            return Object.entries(this.fields)
                .filter(([, info]) => info.needs_confirmation)
                .map(([field]) => field);
        },

        /** Publiceren mag als er een concept is en alles behalve de accountvraag in orde is. */
        get canPublish() {
            return this.draft !== null && ! this.publishing
                && this.preflight.blocking.filter((key) => key !== 'account').length === 0;
        },

        // Uploads ------------------------------------------------------------

        /**
         * Foto's uploaden met voortgang per bestand (XMLHttpRequest, want
         * fetch geeft geen uploadvoortgang). Wat geen foto is of te groot,
         * blijft als fout in de lijst staan; de rest verdwijnt na een tel.
         */
        async uploadPhotos(files) {
            await this.ensureDraft();
            this.uploading = true;
            this.dragOver = false;

            for (const file of files) {
                if (! file.type.startsWith('image/')) {
                    this.uploads.push({ name: file.name, pct: 100, error: 'Geen foto: alleen JPG, PNG of WebP.' });
                    continue;
                }

                if (file.size > 10 * 1024 * 1024) {
                    this.uploads.push({ name: file.name, pct: 100, error: 'Groter dan 10 MB.' });
                    continue;
                }

                const upload = { name: file.name, pct: 0, error: null };
                this.uploads.push(upload);

                try {
                    // Grote foto's gaan in delen en zijn hervatbaar (briefing stap 1);
                    // kleine in één keer.
                    const response = file.size > CHUNK_SIZE * 2
                        ? await this.uploadChunked(file, upload)
                        : await this.uploadWhole(file, upload);
                    this.draft.media = response.all;
                    this.completeness = response.completeness;
                    upload.pct = 100;
                } catch (message) {
                    upload.error = message;
                    upload.pct = 100;
                }
            }

            this.uploading = false;
            setTimeout(() => (this.uploads = this.uploads.filter((upload) => upload.error)), 1200);
            await this.refreshStatus();
        },

        async removePhoto(media) {
            const response = await this.request(
                `/api/drafts/${this.draft.id}/media/${media.id}`, 'DELETE', {},
            );

            if (response) {
                this.draft.media = response.all;
                this.completeness = response.completeness;
                await this.refreshStatus();
            }
        },

        /** Deze foto als omslag: naar voren, de rest schuift op. */
        async setCover(media) {
            const response = await this.request(`/api/drafts/${this.draft.id}/media/${media.id}/cover`, 'POST', {});

            if (response) {
                this.draft.media = response.all;
            }
        },

        uploadWhole(file, upload) {
            const body = new FormData();
            body.append('file', file);

            return this.upload(`/api/drafts/${this.draft.id}/media`, body, (pct) => (upload.pct = pct));
        },

        /**
         * In delen van een megabyte, met de stand in localStorage: valt de
         * verbinding weg of herlaadt de pagina, dan gaat dezelfde foto verder
         * bij het eerste deel dat nog ontbreekt.
         */
        async uploadChunked(file, upload) {
            const key = `aucvy_upload_${this.draft.id}_${file.name}_${file.size}_${file.lastModified}`;
            const chunks = Math.ceil(file.size / CHUNK_SIZE);
            let id = null;
            let received = [];

            try { id = localStorage.getItem(key); } catch (error) {}

            if (id) {
                const status = await this.request(`/api/drafts/${this.draft.id}/uploads/${id}`, 'GET');
                if (status && status.chunks === chunks) received = status.received; else id = null;
            }

            if (! id) {
                const started = await this.request(`/api/drafts/${this.draft.id}/uploads`, 'POST', { name: file.name, size: file.size, type: file.type, chunks });
                if (! started) throw this.error ?? 'Upload kon niet starten.';
                id = started.upload;
                try { localStorage.setItem(key, id); } catch (error) {}
            }

            if (received.length) upload.pct = Math.round((received.length / chunks) * 95);

            for (let index = 0; index < chunks; index++) {
                if (received.includes(index)) continue;

                const body = new FormData();
                body.append('chunk', file.slice(index * CHUNK_SIZE, (index + 1) * CHUNK_SIZE), file.name);
                await this.upload(`/api/drafts/${this.draft.id}/uploads/${id}/${index}`, body, (pct) => (upload.pct = Math.round(((index + pct / 100) / chunks) * 95)));
            }

            const response = await this.request(`/api/drafts/${this.draft.id}/uploads/${id}/complete`, 'POST', {});
            if (! response) throw this.error ?? 'Samenvoegen mislukt.';

            try { localStorage.removeItem(key); } catch (error) {}

            return response;
        },

        upload(url, body, onProgress) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', url);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) onProgress(Math.round((event.loaded / event.total) * 100));
                });
                xhr.addEventListener('load', () => {
                    let data = {};
                    try { data = JSON.parse(xhr.responseText); } catch (error) {}

                    if (xhr.status >= 200 && xhr.status < 300) return resolve(data);

                    reject(data.message ?? Object.values(data.errors ?? {}).flat()[0] ?? 'Er ging iets mis.');
                });
                xhr.addEventListener('error', () => reject('Geen verbinding.'));
                xhr.send(body);
            });
        },

        // Kenmerken ----------------------------------------------------------

        /** De velden uit het schema van de gekozen categorie. */
        get schemaFields() {
            return this.schemas[this.draft?.category_id] ?? [];
        },

        /** Kenmerken buiten het schema: van Ivy of zelf toegevoegd. */
        get extraSpecs() {
            const known = new Set(this.schemaFields.map((field) => field.key));

            return Object.entries(this.draft?.specifications ?? {})
                .filter(([key]) => ! known.has(key))
                .map(([key, entry]) => ({ key, value: entry?.value ?? entry, source: entry?.source ?? 'seller' }));
        },

        spec(key) {
            const entry = this.draft?.specifications?.[key];

            return entry == null ? '' : (entry.value ?? entry);
        },

        specSource(key) {
            return this.draft?.specifications?.[key]?.source ?? null;
        },

        setSpec(key, value) {
            if (! this.draft) return;

            const specs = { ...(this.draft.specifications ?? {}) };

            if (value === '' || value === null) {
                delete specs[key];
            } else {
                specs[key] = { value, source: 'seller' };
            }

            this.draft.specifications = specs;
            this.set('specifications', Object.fromEntries(Object.entries(specs).map(([k, entry]) => [k, entry.value ?? entry])));
        },

        addSpec() {
            const key = this.newSpecKey.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 40);

            if (! key || this.draft?.specifications?.[key]) return;

            this.setSpec(key, '-');
            this.newSpecKey = '';
        },

        specLabel(key) {
            const labels = { ean: 'Barcode (EAN)', barcode: 'Barcode', build_year: 'Bouwjaar' };

            return labels[key] ?? key.charAt(0).toUpperCase() + key.slice(1).replace(/_/g, ' ');
        },

        // Spraak en barcode (briefing sectie 5, stap 1) -------------------------
        // Beide via wat de browser zelf kan: geen bibliotheek, en knoppen die
        // verdwijnen waar het niet werkt.

        get canDictate() {
            return typeof window !== 'undefined' && Boolean(window.SpeechRecognition || window.webkitSpeechRecognition);
        },

        dictate(field) {
            if (this.dictating === field) {
                this.recognition?.stop();

                return;
            }

            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Recognition) return;

            const recognition = new Recognition();
            recognition.lang = 'nl-NL';
            recognition.continuous = true;
            recognition.interimResults = false;

            const base = (this.draft?.[field] ?? '').trim();
            recognition.onresult = (event) => {
                const spoken = Array.from(event.results).filter((r) => r.isFinal).map((r) => r[0].transcript.trim()).join(' ');
                this.set(field, (base ? base + ' ' : '') + spoken);
            };
            recognition.onend = () => { this.dictating = null; this.recognition = null; };
            recognition.onerror = () => { this.dictating = null; this.recognition = null; };

            this.recognition = recognition;
            this.dictating = field;
            recognition.start();
        },

        get canScan() {
            return typeof window !== 'undefined' && 'BarcodeDetector' in window;
        },

        async scanBarcode(file) {
            if (!file || !this.canScan) return;
            this.scan = { busy: true, result: null, error: null };

            try {
                const bitmap = await createImageBitmap(file);
                const detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code', 'data_matrix'] });
                const codes = await detector.detect(bitmap);

                if (!codes.length) {
                    this.scan = { busy: false, result: null, error: 'Geen barcode gevonden. Probeer een scherpere foto van dichtbij.' };

                    return;
                }

                const code = String(codes[0].rawValue).trim();

                // Het kenmerk kan pas op een concept; de eerste actie in een
                // verse wizard kan deze scan zijn.
                await this.ensureDraft();
                this.setSpec(/^\d{8,14}$/.test(code) ? 'ean' : 'barcode', code);
                this.scan = { busy: false, result: code, error: null };

                // Het label hoort bij de kavel: de foto gaat gewoon mee.
                this.uploadPhotos([file]);
            } catch (error) {
                this.scan = { busy: false, result: null, error: 'Scannen lukte niet op dit apparaat.' };
            }
        },

        // SEO-voorbeeld ---------------------------------------------------------

        get seoSlug() {
            const slug = (this.draft?.title ?? '')
                .toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

            return (slug || 'kavel') + '-' + (this.draft?.public_id ?? '');
        },

        get seoDescription() {
            const text = (this.draft?.description ?? '').replace(/\s+/g, ' ').trim();

            return text.length > 155 ? text.slice(0, 152).trimEnd() + '...' : text;
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
            await this.refreshStatus();
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

            // Onthoud de vorige schatting: wordt de range smaller doordat er
            // meer is aangeleverd, dan vieren we dat expliciet.
            const previousSpread = this.draft?.value_min_cents != null && this.draft?.value_max_cents != null
                ? this.draft.value_max_cents - this.draft.value_min_cents
                : null;

            this.ivy = { running: true, current: null, notes: [], sharpened: false };

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

            const spread = this.draft?.value_min_cents != null && this.draft?.value_max_cents != null
                ? this.draft.value_max_cents - this.draft.value_min_cents
                : null;

            this.ivy.sharpened = previousSpread !== null && spread !== null && spread < previousSpread;
        },

        /** Pollen tot de run klaar is; de taken zelf draaien op de queue. */
        async awaitRun(run) {
            // Even lang wachten als de server de taak gunt (180 s), anders
            // meldt de wizard "te lang" terwijl de run nog slaagt.
            for (let attempt = 0; attempt < 120; attempt++) {
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
            if (this.publishing) return;

            this.publishing = true;

            try {
                await this.publishNow();
            } finally {
                this.publishing = false;
            }
        },

        async publishNow() {
            // Eerst alles wegschrijven wat nog in de wachtrij staat.
            clearTimeout(this._timer);
            await this.flush();
            await this.ensureDraft();

            if (! this.draft) {
                this.error = 'Het concept kon niet worden aangemaakt. Probeer het zo opnieuw.';

                return;
            }

            // De preflight is de waarheid: staat er iets open, dan spring je
            // naar de stap waar het op te lossen is in plaats van een 422.
            const open = this.preflight.checks.find((check) => check.blocking && ! check.ok && check.key !== 'account');

            if (open) {
                this.error = open.hint ?? open.label;
                this.step = open.step;

                return;
            }

            // Dezelfde sleutel bij een herhaling: twee keer klikken of een retry
            // na een timeout geeft dezelfde publicatie terug.
            this._publishKey ??= crypto.randomUUID();
            const response = await this.request(`/api/drafts/${this.draft.id}/publish`, 'POST', {}, {
                'Idempotency-Key': this._publishKey,
            });

            if (response?.published) {
                window.location.href = response.url;
            } else if (response?.login_url) {
                // De accountvraag komt hier, niet eerder. Het concept blijft
                // op het cookie staan en gaat na het inloggen mee op naam.
                window.location.href = response.login_url;
            }
        },

        // Vervoer ------------------------------------------------------------

        async request(url, method, body, extraHeaders = {}) {
            this.error = null;

            try {
                const isForm = body instanceof FormData;

                const response = await fetch(url, {
                    method: isForm ? 'POST' : method,
                    headers: {
                        ...extraHeaders,
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
