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
        scan: { busy: false, busyText: '', result: null, error: null, code: '' },
        saving: false,
        publishing: false,
        saved: false,
        error: null,
        uploading: false,

        // Ivy (stap 3): eerst wat voor het hele concept geldt (tasks), dan
        // per product dezelfde stappen (products), na elkaar. Elke taak heeft
        // een stand: waiting, busy, done, failed of skipped.
        ivy: { running: false, sharpened: false, note: null, proposal: null, keepTogether: false, analysedKey: null, now: 0, tasks: [], products: [] },

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
                'ownership_declared', 'warranty', 'warranty_until', 'documents_authentic',
                'intake', 'scope',
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

        /** Een bedrag om te tonen, met punten voor de duizendtallen. */
        money(cents) {
            return cents == null ? '' : '€ ' + (cents / 100).toLocaleString('nl-NL', { maximumFractionDigits: 2 });
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

            // De eigen woorden van stap 1 wonen in meta, de omschrijving op het concept.
            const base = ((field === 'intake' ? this.meta.intake : this.draft?.[field]) ?? '').trim();
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

        // Scannen (briefing nieuwe functies): barcode met de lezer van de
        // browser waar die er is, anders leest Ivy het label. De code wordt
        // op controlegetal gecheckt en opgezocht in de eigen cache en de
        // EAN-dienst; merk en model komen als voorstel met bron.
        async scanBarcode(file) {
            if (!file) return;

            if (!this.canScan) {
                await this.scanNameplate(file, 'Geen barcodelezer in deze browser; Ivy leest het label…');

                return;
            }

            this.scan = { ...this.scan, busy: true, busyText: 'Barcode zoeken…', result: null, error: null };

            try {
                const bitmap = await createImageBitmap(file);
                const detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'qr_code', 'data_matrix'] });
                const codes = await detector.detect(bitmap);

                if (!codes.length) {
                    await this.scanNameplate(file, 'Geen barcode herkend; Ivy leest het label…');

                    return;
                }

                const code = String(codes[0].rawValue).trim();

                if (/^\d{8,14}$/.test(code)) {
                    await this.lookupGtin(code);
                } else {
                    await this.ensureDraft();
                    this.setSpec('barcode', code);
                    this.scan = { ...this.scan, busy: false, result: 'Code ' + code + ' bij de kenmerken gezet.', error: null };
                }

                // Het label hoort bij de kavel: de foto gaat gewoon mee.
                this.uploadPhotos([file]);
            } catch (error) {
                await this.scanNameplate(file, 'Scannen lukte niet op dit apparaat; Ivy leest het label…');
            }
        },

        async submitGtin() {
            const code = String(this.scan.code ?? '').trim();
            if (!code) return;
            await this.lookupGtin(code);
        },

        async lookupGtin(code) {
            this.scan = { ...this.scan, busy: true, busyText: 'Productcode controleren en opzoeken…', result: null, error: null };
            await this.ensureDraft();

            const response = await this.request(`/api/drafts/${this.draft.id}/scan/gtin`, 'POST', { code });

            if (!response) {
                this.scan = { ...this.scan, busy: false, error: this.error ?? 'Dit is geen geldige productcode.' };

                return;
            }

            if (response.draft) this.apply(response);
            this.scan = { ...this.scan, busy: false, code: '', result: response.message, error: null };
        },

        // Typeplaat: de foto wordt een intern document (niet een kavelfoto);
        // Ivy leest merk, model, bouwjaar, vermogen en serienummer.
        async scanNameplate(file, busyText = 'Ivy leest de typeplaat…') {
            if (!file) return;
            this.scan = { ...this.scan, busy: true, busyText, result: null, error: null };
            await this.ensureDraft();

            const body = new FormData();
            body.append('file', file);

            const started = await this.request(`/api/drafts/${this.draft.id}/scan/nameplate`, 'POST', body);

            if (!started) {
                this.scan = { ...this.scan, busy: false, error: this.error ?? 'De typeplaat kon niet worden gelezen.' };

                return;
            }

            this.draft.documents.push(started.document);

            for (let attempt = 0; attempt < 40; attempt++) {
                await new Promise((resolve) => setTimeout(resolve, 1500));
                const poll = await this.request(`/api/ivy/tasks/${started.run.id}`, 'GET');
                const status = poll?.run?.status;

                if (status === 'completed') {
                    const fresh = await this.request(`/api/drafts/${this.draft.id}`, 'GET');
                    if (fresh?.draft) this.apply(fresh);
                    const fields = this.draft ?? {};
                    const parts = [fields.brand ? 'merk ' + fields.brand : null, fields.model ? 'model ' + fields.model : null, fields.year ? 'bouwjaar ' + fields.year : null].filter(Boolean);
                    this.scan = { ...this.scan, busy: false, result: parts.length ? 'Ivy las de typeplaat: ' + parts.join(', ') + '. Controleer de velden; de bron staat erbij.' : 'Ivy kon weinig lezen op deze plaat. Probeer een scherpere foto van dichtbij.', error: null };

                    return;
                }

                if (status === 'failed' || status === 'skipped') {
                    this.scan = { ...this.scan, busy: false, error: 'Ivy kon de typeplaat nu niet lezen. Vul merk en model zelf in.' };

                    return;
                }
            }

            this.scan = { ...this.scan, busy: false, error: 'Dit duurt langer dan verwacht; de velden worden ingevuld zodra Ivy klaar is.' };
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
         * De taken staan vanaf de start als lijst op het scherm: wat nog moet
         * komen is grijs, wat loopt zegt wat Ivy op dat moment doet, wat klaar
         * is toont het resultaat. Zo ziet de verkoper wat er gebeurt.
         *
         * Faalt of skipt er iets (noodrem, budget), dan stopt de reeks en
         * vult de verkoper zelf in - Ivy is nooit een blokkade (hfst. 8).
         */
        async analyse() {
            if (this.ivy.running) return;

            clearTimeout(this._timer);
            await this.flush();
            await this.ensureDraft();

            if (! this.draft) return;

            // Meerdere producten: eerst de foto's per product bij elkaar
            // zetten, zolang het concept nog niet gesplitst is en de verkoper
            // niet zei dat het één kavel blijft.
            const keepTogether = this.ivy.keepTogether ?? false;
            const grouping = ['multiple', 'inventory'].includes(this.meta.scope) && (this.draft?.media?.length ?? 0) >= 2
                && ! (this.draft?.siblings?.length) && ! keepTogether;

            this.ivy = {
                running: false,
                sharpened: false,
                note: null,
                proposal: null,
                keepTogether,
                analysedKey: this.ivyInputKey,
                now: Date.now(),
                // Onthoud de vorige schatting: wordt de range smaller doordat
                // er meer is aangeleverd, dan vieren we dat expliciet.
                previousSpread: this.draft?.value_min_cents != null && this.draft?.value_max_cents != null
                    ? this.draft.value_max_cents - this.draft.value_min_cents
                    : null,
                // Wat voor het hele concept geldt (de foto's verdelen), daarna per product.
                tasks: grouping ? [this.ivyTask('group_photos')] : [],
                products: this.buildProducts(),
            };

            await this.runTasks();
        },

        ivyTask(task) {
            return { task, status: 'waiting', run: null, started: null };
        },

        /**
         * De producten in deze analyse: dit concept eerst, dan de kavels die
         * eruit gesplitst zijn. Elk krijgt dezelfde taken; een ander kavel
         * dat al een titel en een waarde heeft, doet Ivy niet nog eens.
         */
        buildProducts() {
            const tasks = (documents) => [
                'recognise_lot', 'draft_listing', 'estimate_value',
                ...(documents ? ['check_documents'] : []),
            ].map((task) => this.ivyTask(task));

            const own = { id: this.draft.id, own: true, open: true, status: 'waiting', tasks: tasks((this.draft.documents ?? []).length > 0) };

            const others = (this.draft.siblings ?? []).map((sibling) => {
                const finished = sibling.title && sibling.value_min_cents != null;

                return { ...sibling, own: false, open: false, status: finished ? 'done' : 'waiting', tasks: finished ? [] : tasks(false) };
            });

            return [own, ...others];
        },

        /**
         * Het rijtje af: eerst wat voor het hele concept geldt, dan elk
         * product na elkaar. Het product dat aan de beurt is klapt open en
         * doorloopt zijn stappen; daarna klapt het dicht en gaat het
         * volgende open. Stelt Ivy voor om de foto's te verdelen, dan wacht
         * de reeks op de keuze van de verkoper (split of keepTogether).
         */
        async runTasks() {
            this.ivy.running = true;

            // Een klok voor de wisselende zin bij de taak die loopt.
            const clock = setInterval(() => (this.ivy.now = Date.now()), 1000);
            let halted = false;

            try {
                for (const item of this.ivy.tasks.filter((task) => task.status === 'waiting')) {
                    const run = await this.runOne(item, this.draft.id, (response) => this.apply(response));

                    if (item.task === 'group_photos' && run.status === 'completed' && (run.groups?.length ?? 0) > 1) {
                        this.ivy.proposal = run.groups;

                        return;
                    }

                    if (run.status === 'skipped' || run.halted) halted = true;
                }

                for (const product of this.ivy.products) {
                    if (halted || product.status === 'done') continue;

                    this.ivy.products.forEach((other) => (other.open = other === product));
                    product.status = 'busy';

                    for (const item of product.tasks.filter((task) => task.status === 'waiting')) {
                        const run = await this.runOne(item, product.id, product.own
                            ? (response) => this.apply(response)
                            : (response) => Object.assign(product, this.productFacts(response.draft)));

                        if (product.own && item.task === 'recognise_lot' && run.output?.seller_note) {
                            this.ivy.note = run.output.seller_note;
                        }

                        // Noodrem, budget of geen verbinding: de rest gaat hetzelfde zeggen.
                        if (run.status === 'skipped' || run.halted) {
                            halted = true;
                            break;
                        }
                    }

                    product.status = product.tasks.some((task) => task.status === 'failed') ? 'failed' : 'done';
                }

                // Wat niet meer aan de beurt kwam, staat als overgeslagen.
                [...this.ivy.tasks, ...this.ivy.products.flatMap((product) => product.tasks)]
                    .filter((item) => item.status === 'waiting')
                    .forEach((item) => (item.status = 'skipped'));

                // Klaar: alles dicht, zodat je de rij in één oogopslag ziet. Eén product blijft open.
                if (this.ivy.products.length > 1) this.ivy.products.forEach((product) => (product.open = false));

                const spread = this.draft?.value_min_cents != null && this.draft?.value_max_cents != null
                    ? this.draft.value_max_cents - this.draft.value_min_cents
                    : null;

                this.ivy.sharpened = this.ivy.previousSpread != null && spread !== null && spread < this.ivy.previousSpread;
            } finally {
                clearInterval(clock);
                this.ivy.running = false;
            }
        },

        /** Eén taak voor één kavel: starten, wachten, de stand bijwerken. */
        async runOne(item, lotId, onDraft) {
            item.status = 'busy';
            item.started = Date.now();

            const started = await this.request('/api/ivy/tasks', 'POST', { task: item.task, lot_id: lotId });
            const run = started ? await this.awaitRun(started.run, onDraft) : { status: 'failed', halted: true };

            item.run = run;
            item.status = { completed: 'done', skipped: 'skipped' }[run.status] ?? 'failed';

            return run;
        },

        /** Wat de kop van een ander kavel toont, uit de verse conceptstand van de server. */
        productFacts(draft) {
            return {
                title: draft.title,
                subtitle: [draft.specifications?.objecttype?.value, draft.brand, draft.model].filter(Boolean).join(' · '),
                value_min_cents: draft.value_min_cents,
                value_max_cents: draft.value_max_cents,
            };
        },

        /** De kop van een product: voor dit concept live uit het concept, voor de andere uit hun stand. */
        productView(product) {
            if (! product.own) {
                return {
                    title: product.title,
                    subtitle: product.subtitle ?? '',
                    cover: product.cover,
                    photos: product.photos ?? 0,
                    value_min_cents: product.value_min_cents,
                    value_max_cents: product.value_max_cents,
                };
            }

            return {
                title: this.draft?.title,
                subtitle: [this.draft?.specifications?.objecttype?.value, this.draft?.brand, this.draft?.model].filter(Boolean).join(' · '),
                cover: this.draft?.media?.[0]?.url,
                photos: this.draft?.media?.length ?? 0,
                value_min_cents: this.draft?.value_min_cents,
                value_max_cents: this.draft?.value_max_cents,
            };
        },

        /** Is deze taak van dit product nog niet klaar terwijl Ivy loopt? Dan krijgt dat deel van de kop een skelet. */
        productPending(product, task) {
            return this.ivy.running && product.tasks.some((item) => item.task === task && ['waiting', 'busy'].includes(item.status));
        },

        /** De verkoper neemt het voorstel over: elk product een eigen kavel. */
        async split() {
            const response = await this.request(`/api/drafts/${this.draft.id}/split`, 'POST', {
                groups: this.ivy.proposal.map((group) => group.media),
            });

            if (! response) return;

            this.apply(response);
            this.ivy.proposal = null;
            // De foto's zijn verdeeld, niet veranderd: de analyse is niet verouderd.
            this.ivy.analysedKey = this.ivyInputKey;
            // Nu er meer kavels zijn, gaat Ivy ze een voor een af.
            this.ivy.products = this.buildProducts();

            await this.runTasks();
        },

        /** De verkoper houdt alles als één kavel. */
        async keepTogether() {
            this.ivy.proposal = null;
            this.ivy.keepTogether = true;

            await this.runTasks();
        },

        /** De foto's van een groep uit het voorstel, voor de miniaturen. */
        groupPhotos(group) {
            return (this.draft?.media ?? []).filter((item) => group.media.includes(item.id));
        },

        /**
         * Bij het openen van stap 3 begint Ivy vanzelf, als er iets te zien is
         * en ze dit nog niet bekeken heeft. Een tweede bezoek met dezelfde
         * foto's en woorden start niets.
         */
        autoAnalyse() {
            if (this.ivy.running || (this.ivy.products.length && ! this.ivyStale)) return;

            if (! (this.draft?.media?.length || (this.meta.intake ?? '').trim())) return;

            this.analyse();
        },

        /** Wat Ivy bekijkt: de foto's en de eigen woorden. Verandert dit, dan is de analyse verouderd. */
        get ivyInputKey() {
            return (this.draft?.media ?? []).map((item) => item.id).join(',') + '|' + (this.meta.intake ?? '').trim();
        },

        get ivyStale() {
            return ! this.ivy.running && this.ivy.analysedKey !== null && this.ivy.analysedKey !== this.ivyInputKey;
        },

        get ivyDone() {
            return this.ivy.products.some((product) => product.tasks.some((item) => item.status === 'done'));
        },

        /** Pollen tot de run klaar is; de taken zelf draaien op de queue. */
        async awaitRun(run, onDraft = (response) => this.apply(response)) {
            // Even lang wachten als de server de taak gunt (180 s), anders
            // meldt de wizard "te lang" terwijl de run nog slaagt.
            for (let attempt = 0; attempt < 120; attempt++) {
                if (['completed', 'failed', 'skipped'].includes(run.status)) return run;

                await new Promise((resolve) => setTimeout(resolve, 1500));

                const response = await this.request(`/api/ivy/tasks/${run.id}`, 'GET');

                if (! response) break;

                run = response.run;

                if (run.status === 'completed' && response.draft) onDraft(response);
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
                group_photos: 'Producten onderscheiden',
            }[task] ?? task;
        },

        ivyAgent(task) {
            return { draft_listing: 'nova', estimate_value: 'atlas', check_documents: 'lex' }[task] ?? 'ivy';
        },

        /**
         * Wat de taak op dit moment doet, in een zin die om de paar seconden
         * wisselt. Het zijn de stappen die de taak echt zet; het model meldt
         * zelf geen tussenstand.
         */
        ivyActivity(item, product = null) {
            const photos = product ? this.productView(product).photos : (this.draft?.media?.length ?? 0);
            const phrases = {
                recognise_lot: [
                    photos > 1 ? `${photos} foto's bekijken` : (photos === 1 ? 'De foto bekijken' : 'Je woorden lezen'),
                    'Zoeken naar merk, model en typeplaat',
                    'De staat beoordelen',
                    'Kenmerken noteren',
                ],
                draft_listing: ['Een titel kiezen', 'De omschrijving schrijven', 'Gebreken eerlijk benoemen'],
                estimate_value: ['Vergelijkbare verkopen zoeken', 'Prijzen naast elkaar leggen', 'De bandbreedte bepalen'],
                check_documents: ['De documenten lezen', 'Gegevens vergelijken met de kavel'],
                group_photos: [photos > 1 ? `${photos} foto's naast elkaar leggen` : 'De foto bekijken', 'Zelfde producten bij elkaar zetten', 'Per product een naam kiezen'],
            }[item.task] ?? ['Bezig'];

            const seconds = Math.max(0, Math.floor((this.ivy.now - (item.started ?? this.ivy.now)) / 1000));

            return { text: phrases[Math.floor(seconds / 3) % phrases.length], seconds };
        },

        /** Het resultaat van een afgeronde taak, in één regel. */
        ivyResult(item, conditions, product = null) {
            const output = item.run?.output ?? {};
            const view = product ? this.productView(product) : null;

            if (item.status === 'skipped') return item.run?.error ?? 'Overgeslagen.';
            if (item.status === 'failed') return 'Niet gelukt. Vul dit zelf in bij de volgende stap.';

            switch (item.task) {
                case 'recognise_lot':
                    return [output.object_type, [output.brand, output.model].filter(Boolean).join(' '), conditions[output.condition]]
                        .filter(Boolean).join(' · ');
                case 'draft_listing':
                    return view?.title ? `"${view.title}"` : 'Tekst geschreven.';
                case 'estimate_value':
                    return view?.value_min_cents != null
                        ? this.money(view.value_min_cents) + ' tot ' + this.money(view.value_max_cents)
                        : 'Te weinig vergelijkbare verkopen.';
                case 'check_documents':
                    return output.summary ?? 'Gecontroleerd.';
                case 'group_photos':
                    if ((item.run?.groups?.length ?? 1) < 2) return 'Eén product op alle foto\'s.';
                    if (this.ivy.proposal) return 'Ivy ziet ' + item.run.groups.length + ' producten.';

                    return item.run.groups.length + (this.ivy.keepTogether ? ' producten, samen in één kavel.' : ' producten, elk een eigen kavel.');
                default:
                    return 'Klaar.';
            }
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
            } else if (response?.submitted) {
                // Ingediend: Ivy controleert de kavel vóór hij live gaat.
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
