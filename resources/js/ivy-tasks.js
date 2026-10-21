/**
 * De losse Ivy-componenten buiten de funnel (hfst. 8):
 *
 *  - `ivyAdvice`: biedadvies op de kavelpagina. De bedragen op het scherm
 *    komen uit PHP (staffel en waardeschatting); Ivy levert alleen de
 *    duiding, altijd met "jij beslist".
 *  - `searchInterpret`: de zoekvraag als corrigeerbare lezing. Corrigeren is
 *    een link - er wordt nooit een tweede LLM-call gedaan voor een correctie.
 *
 * Beide volgen hetzelfde stramien als de funnel: taak starten, pollen, en
 * bij skipped (noodrem, budget) stilletjes terugvallen - Ivy is nooit een
 * blokkade.
 */

async function request(url, method, body) {
    try {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                ...(body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        });

        if (! response.ok) return null;

        return await response.json();
    } catch {
        return null;
    }
}

async function awaitRun(run) {
    for (let attempt = 0; attempt < 40; attempt++) {
        if (['completed', 'failed', 'skipped'].includes(run.status)) return run;

        await new Promise((resolve) => setTimeout(resolve, 1500));

        const response = await request(`/api/ivy/tasks/${run.id}`, 'GET');

        if (! response) return run;

        run = response.run;
    }

    return run;
}

export function ivyAdvice(lotId) {
    return {
        state: 'idle', // idle | busy | done | unavailable
        advice: null,

        async ask() {
            if (this.state === 'busy') return;

            this.state = 'busy';

            const started = await request('/api/ivy/tasks', 'POST', {
                task: 'bid_advice',
                lot_id: lotId,
            });

            if (! started) {
                this.state = 'unavailable';

                return;
            }

            const run = await awaitRun(started.run);

            if (run.status === 'completed') {
                this.advice = run;
                this.state = 'done';
            } else {
                // Noodrem, budget of een fout: geen advies is geen blokkade.
                this.state = 'unavailable';
            }
        },

        get showAmounts() {
            // Bij lage zekerheid geen exacte bedragen als advies (hfst. 8).
            return ['medium', 'high'].includes(this.advice?.confidence_level);
        },
    };
}

export function searchInterpret(query, enabled) {
    return {
        reading: null,

        async init() {
            if (! enabled || ! query || query.length < 2) return;

            const started = await request('/api/ivy/tasks', 'POST', {
                task: 'interpret_search',
                query,
            });

            if (! started) return;

            const run = await awaitRun(started.run);

            if (run.status === 'completed') this.reading = run;
        },

        /** Correctie = link met aangepaste filters; nooit een nieuwe LLM-call. */
        applyUrl(overrides) {
            const url = new URL(window.location.href);
            const filters = this.reading?.output?.filters ?? {};
            // Alleen de filters die de zoekpagina kent; nooit de zoekterm
            // zelf of een sleutel die het model erbij verzint.
            const known = ['categorie', 'vanaf', 'tot', 'conditie'];

            for (const [key, value] of Object.entries({ ...filters, ...overrides })) {
                if (! known.includes(key)) continue;

                if (value === null || value === undefined || value === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, value);
                }
            }

            return url.pathname + url.search;
        },

        get chips() {
            const filters = this.reading?.output?.filters ?? {};

            return [
                filters.categorie && { label: filters.categorie.replaceAll('-', ' '), remove: { categorie: null } },
                filters.vanaf && { label: `vanaf € ${filters.vanaf}`, remove: { vanaf: null } },
                filters.tot && { label: `tot € ${filters.tot}`, remove: { tot: null } },
                filters.conditie && { label: filters.conditie, remove: { conditie: null } },
            ].filter(Boolean);
        },
    };
}
