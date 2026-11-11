/*
 | Het zwevende paneel van Ivy (rechtsonder op elke pagina). Werkt zoals
 | Izzy bij Eazyonline: het antwoord komt als stroom binnen, je ziet welke
 | stappen Ivy zet ("Kavels zoeken"), de tekst wordt uitgetypt, er is een
 | stopknop, opnieuw proberen, kopiëren, voorlezen en inspreken.
 |
 | Anders dan Izzy: het gesprek staat op de server (niet in de browser), en
 | een bod of aankoop bevestig je nooit in de chat maar op het scherm dat
 | de voorstelkaart opent.
 */

const escapeHtml = (text) => text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

/** Vet, cursief, code en links binnen één regel; alles is eerst ontsnapt. */
function inline(text) {
    return escapeHtml(text)
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (match, label, url) => {
            // Alleen een pad binnen de site of een gewone weblink; nooit javascript: of data:.
            if (/^\/(?!\/)/.test(url)) return `<a href="${url}">${label}</a>`;
            if (/^https?:\/\//.test(url)) return `<a href="${url}" target="_blank" rel="noopener">${label}</a>`;

            return label;
        })
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>');
}

/**
 * Eenvoudige, veilige markdown: alinea's, lijsten, koppen, vet, cursief,
 * code en links. Geen tabellen en geen afbeeldingen.
 */
export function ivyMarkdown(source) {
    // Een link die nog niet af is tijdens het typen tonen we als gewone tekst.
    const text = String(source ?? '').replace(/\[([^\]]*)\]\([^)]*$/, '$1').replace(/\[([^\]]*)$/, '$1');
    const html = [];
    let list = null;
    let paragraph = [];

    const closeParagraph = () => {
        if (paragraph.length) html.push('<p>' + paragraph.join('<br>') + '</p>');
        paragraph = [];
    };
    const closeList = () => {
        if (list) html.push(`</${list}>`);
        list = null;
    };

    for (const raw of text.split('\n')) {
        const line = raw.trimEnd();
        const bullet = line.match(/^\s*[-*•]\s+(.*)$/);
        const numbered = line.match(/^\s*(\d+)[.)]\s+(.*)$/);
        const heading = line.match(/^#{1,6}\s+(.*)$/);

        if (bullet || numbered) {
            closeParagraph();
            const kind = bullet ? 'ul' : 'ol';

            if (list !== kind) {
                closeList();
                html.push(kind === 'ol' ? `<ol start="${Number(numbered[1])}">` : '<ul>');
                list = kind;
            }

            html.push('<li>' + inline(bullet ? bullet[1] : numbered[2]) + '</li>');
        } else if (heading) {
            closeParagraph();
            closeList();
            html.push('<h4>' + inline(heading[1]) + '</h4>');
        } else if (line.trim() === '') {
            closeParagraph();
            closeList();
        } else {
            closeList();
            paragraph.push(inline(line));
        }
    }

    closeParagraph();
    closeList();

    return html.join('');
}

/** Wat de spraak voorleest: zonder opmaak, links en lijsttekens. */
const forSpeech = (text) => String(text ?? '')
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/[*_`#]/g, '')
    .replace(/^\s*[-•]\s+/gm, '')
    .trim();

/*
 | De stem van Ivy bij voorlezen. De browser levert de stemmen; welke er
 | zijn verschilt per apparaat. Ivy is een vrouw, dus een Nederlandse
 | vrouwenstem gaat voor: eerst de bekende namen, dan een stem die we niet
 | kennen, en pas als er niets anders is een mannenstem.
 */
const FEMALE_VOICES = ['colette', 'fenna', 'dena', 'google nederlands', 'claire', 'ellen', 'lotte', 'saskia', 'flo', 'sandy', 'shelley', 'female', 'vrouw'];
const MALE_VOICES = ['frank', 'maarten', 'xander', 'arnaud', 'bart', 'ruben', 'eddy', 'reed', 'rocko', 'grandpa', 'male', 'man'];

export function ivyVoice(voices) {
    const score = (voice) => {
        const name = (voice.name ?? '').toLowerCase();
        const lang = (voice.lang ?? '').toLowerCase().replace('_', '-');
        // "female" bevat "male": eerst op de vrouwennamen toetsen.
        const female = FEMALE_VOICES.some((part) => name.includes(part));
        const male = ! female && MALE_VOICES.some((part) => new RegExp('\\b' + part + '\\b').test(name));

        return (female ? 100 : male ? 0 : 50)
            + (lang === 'nl-nl' ? 10 : 0)
            // De online stemmen van Edge klinken het natuurlijkst.
            + (name.includes('natural') || name.includes('online') ? 5 : 0);
    };

    return [...voices]
        .filter((voice) => (voice.lang ?? '').toLowerCase().startsWith('nl'))
        .sort((a, b) => score(b) - score(a))[0] ?? null;
}

export function ivyPanel(config) {
    return {
        opened: false,
        loaded: false,
        busy: false,
        enabled: true,
        wide: false,
        dropping: false,
        draft: '',
        greeting: null,
        firstName: config.name ? String(config.name).trim().split(' ')[0] : null,
        chips: [],
        // Met wie de klant praat (Ivy of een specialist), het team en wat er vanuit dit teamlid nog wacht.
        agent: 'ivy',
        agents: [],
        tasks: [],
        notes: [],
        switching: false,
        messages: [],
        openCount: 0,
        inboxUrl: null,
        lotTitle: null,
        pageTitle: '',
        follows: true,
        listening: false,
        speaking: null,
        copied: null,
        canDictate: Boolean(window.SpeechRecognition || window.webkitSpeechRecognition),
        canSpeak: 'speechSynthesis' in window,
        initialPath: window.location.pathname,
        abort: null,
        recognition: null,
        voice: null,
        shortcut: /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘I' : 'Ctrl+I',

        async init() {
            this.readPage();

            // Met wie je het laatst praatte, onthouden we op dit apparaat. Alleen met een account.
            if (config.name) {
                try { this.agent = window.localStorage.getItem('ivy.agent') || 'ivy'; } catch (error) { /* zonder opslag begint het bij Ivy */ }
            }

            // De stemmen komen vaak pas na het laden binnen; kies opnieuw zodra ze er zijn.
            if (this.canSpeak) {
                const choose = () => { this.voice = ivyVoice(window.speechSynthesis.getVoices()); };
                choose();
                window.speechSynthesis.addEventListener?.('voiceschanged', choose);
            }

            window.addEventListener('keydown', (event) => this.onKey(event));
            window.addEventListener('popstate', () => this.readPage());
            document.addEventListener('dash-nav:swapped', () => this.readPage());

            try {
                const response = await fetch(config.show + '?' + this.query(), { headers: this.headers() });
                const data = await response.json();
                this.openCount = data.open_count ?? 0;
                this.inboxUrl = data.inbox_url;
                this.agents = data.agents ?? [];
                this.agent = data.agent?.key ?? 'ivy';
            } catch (error) {
                // Zonder Ivy werkt alles gewoon; de knop blijft staan.
            }
        },

        // De pagina waar de klant nu kijkt. Na een wissel zonder herladen
        // klopt de kavel uit de eerste weergave niet meer; dan volgt de
        // server het pad.
        readPage() {
            this.pageTitle = document.title.replace(/\s*[·|-]\s*Aucvy\s*$/i, '').trim() || 'deze pagina';
        },

        query() {
            const samePage = window.location.pathname === this.initialPath;

            return new URLSearchParams({
                lot: samePage ? (config.lot ?? '') : '',
                page: config.page ?? '',
                path: window.location.pathname,
                agent: this.agent,
            }).toString();
        },

        headers() {
            return {
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            };
        },

        onKey(event) {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target?.tagName) || event.target?.isContentEditable;
            const inPanel = this.$root.contains(event.target);

            // Ctrl+I of Cmd+I opent en sluit; in een ander tekstveld is dat cursief en blijven we eraf.
            if ((event.ctrlKey || event.metaKey) && ! event.shiftKey && ! event.altKey && event.key.toLowerCase() === 'i' && (! typing || inPanel)) {
                event.preventDefault();
                this.toggle();

                return;
            }

            if (event.key === 'Escape' && this.opened) {
                if (this.busy) this.stop(); else this.close();
            }
        },

        async toggle() {
            if (this.opened) {
                this.close();

                return;
            }

            this.opened = true;
            this.readPage();
            if (! this.loaded) await this.load();
            this.focus();
            this.scroll(true);
        },

        async open(text, agent) {
            this.opened = true;
            this.readPage();

            if (agent && agent !== this.agent) await this.switchTo(agent);
            else if (! this.loaded) await this.load();

            if (text) await this.send(text); else this.focus();
            this.scroll(true);
        },

        // Wisselen van gesprekspartner: elk teamlid heeft zijn eigen gesprek en zijn eigen takenlijst.
        async switchTo(key) {
            if (this.busy || this.switching || (key === this.agent && this.loaded)) return;

            this.stopSpeaking();
            this.agent = key;
            try { window.localStorage.setItem('ivy.agent', key); } catch (error) { /* onthouden is een gemak, geen eis */ }

            this.switching = true;
            await this.load();
            this.switching = false;
            this.focus();
        },

        // Wie er nu praat. Voor het laden, en voor een bezoeker zonder account, is dat Ivy.
        get current() {
            return this.agents.find((member) => member.key === this.agent) ?? { key: 'ivy', name: 'Ivy', speciality: '', photo: config.photo, count: 0, urgent: false };
        },

        // De teamleden bij wie ook nog iets wacht.
        get others() {
            return this.agents.filter((member) => member.key !== this.agent && member.count > 0);
        },

        // Een taak verbergen: hij verdwijnt uit de lijst en de teller loopt mee.
        dropTask(id) {
            this.tasks = this.tasks.filter((task) => task.id !== id);

            const member = this.agents.find((item) => item.key === this.agent);
            if (member) member.count = Math.max(0, member.count - 1);

            fetch('/api/insights/' + id + '/dismiss', { method: 'POST', headers: this.headers() }).catch(() => {});
        },

        close() {
            this.opened = false;
            this.stopSpeaking();
            this.stopListening();
        },

        focus() {
            // Op een telefoon springt anders meteen het toetsenbord open.
            if (window.matchMedia('(max-width: 640px)').matches) return;
            this.$nextTick(() => this.$refs.input?.focus());
        },

        apply(data) {
            this.enabled = data.enabled;
            this.greeting = data.greeting;
            this.firstName = data.first_name ?? this.firstName;
            this.chips = data.chips ?? [];
            this.agents = data.agents ?? [];
            this.agent = data.agent?.key ?? 'ivy';
            this.tasks = data.tasks ?? [];
            this.notes = data.notes ?? [];
            this.messages = (data.messages ?? []).map((message) => this.prepared(message));
            this.openCount = data.open_count ?? 0;
            this.inboxUrl = data.inbox_url;
            this.lotTitle = data.lot?.title ?? null;
        },

        prepared(message) {
            return { steps: [], error: null, stopped: false, pending: false, typing: false, ...message, shown: message.body ?? '', cards: message.cards ?? [] };
        },

        async load() {
            try {
                const response = await fetch(config.show + '?' + this.query(), { headers: this.headers() });
                this.apply(await response.json());
                this.loaded = true;
                this.scroll(true);
            } catch (error) {
                this.greeting = 'Ivy is even niet bereikbaar. Alles op Aucvy werkt gewoon zonder haar.';
                this.enabled = false;
            }
        },

        async restart() {
            if (this.busy) return;

            this.stopSpeaking();

            try {
                const response = await fetch(config.reset + '?' + this.query(), { method: 'POST', headers: this.headers() });
                this.apply(await response.json());
                this.focus();
            } catch (error) {
                // Het oude gesprek blijft staan; niets aan de hand.
            }
        },

        get hello() {
            const hour = new Date().getHours();
            const part = hour < 6 ? 'Goedenacht' : hour < 12 ? 'Goedemorgen' : hour < 18 ? 'Goedemiddag' : 'Goedenavond';

            return this.firstName ? `${part}, ${this.firstName}` : part;
        },

        get lastId() {
            return this.messages.length ? this.messages[this.messages.length - 1].id : null;
        },

        render(message) {
            return ivyMarkdown(message.shown);
        },

        // Versturen -----------------------------------------------------------

        async send(text) {
            text = (text ?? '').trim();
            if (! text || this.busy || ! this.enabled) return;

            this.draft = '';
            this.resize();
            this.busy = true;
            this.stopSpeaking();
            this.messages.push(this.prepared({ id: 'u' + Date.now(), sender: 'user', body: text }));

            const answer = this.prepared({ id: 'p' + Date.now(), sender: 'ivy', body: '', pending: true, question: text });
            this.messages.push(answer);
            const live = this.messages[this.messages.length - 1];
            this.scroll(true);

            this.abort = new AbortController();

            try {
                const response = await fetch(config.send + '?' + this.query(), {
                    method: 'POST',
                    headers: { ...this.headers(), Accept: 'text/event-stream', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text, stream: true, path: window.location.pathname, title: this.pageTitle }),
                    signal: this.abort.signal,
                });

                if (! response.ok || ! (response.headers.get('content-type') ?? '').includes('text/event-stream') || ! response.body) {
                    live.error = await this.problem(response);
                } else {
                    await this.read(response.body.getReader(), live);

                    if (! live.error && ! live.body && ! live.stopped) live.error = 'Er kwam geen antwoord terug.';
                }
            } catch (error) {
                if (error.name === 'AbortError') live.stopped = true; else live.error = 'Geen verbinding met Ivy. Controleer je internet en probeer het opnieuw.';
            } finally {
                live.pending = false;
                live.steps.forEach((step) => { if (step.state === 'bezig') step.state = 'klaar'; });
                this.busy = false;
                this.abort = null;
                this.scroll();
                this.focus();
            }
        },

        /** Leest de stroom: regels `data: {json}`, gescheiden door een lege regel. */
        async read(reader, live) {
            const decoder = new TextDecoder();
            let buffer = '';

            for (;;) {
                const { value, done } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const parts = buffer.split('\n\n');
                buffer = parts.pop() ?? '';

                for (const part of parts) {
                    const line = part.split('\n').find((row) => row.startsWith('data: '));
                    if (! line) continue;

                    let event;
                    try { event = JSON.parse(line.slice(6)); } catch (error) { continue; }

                    this.handle(event, live);
                }
            }
        },

        handle(event, live) {
            if (event.type === 'stap') {
                live.steps.push({ id: event.id, text: event.tekst, state: 'bezig' });
                this.scroll();
            } else if (event.type === 'stap_klaar') {
                const step = live.steps.find((item) => item.id === event.id);
                if (step) step.state = event.ok ? 'klaar' : 'mislukt';
            } else if (event.type === 'bericht') {
                live.id = event.message.id;
                live.body = event.message.body ?? '';
                live.cards = event.message.cards ?? [];
                live.pending = false;
                if (typeof event.open_count === 'number') this.openCount = event.open_count;
                this.type(live);
            } else if (event.type === 'fout') {
                live.error = event.tekst;
            }
        },

        /** Typt het antwoord uit; een lange tekst gaat sneller, zodat het nooit sleept. */
        type(live) {
            // In een tabblad op de achtergrond lopen animaties niet; dan staat de tekst er meteen.
            if (document.hidden) {
                live.shown = live.body;
                live.typing = false;
                this.afterAnswer(live);
                this.scroll();

                return;
            }

            live.typing = true;
            live.shown = '';

            const tick = () => {
                if (! this.messages.includes(live)) return;

                const rest = live.body.length - live.shown.length;

                if (rest <= 0) {
                    live.typing = false;
                    this.afterAnswer(live);

                    return;
                }

                live.shown = live.body.slice(0, live.shown.length + Math.max(2, Math.ceil(rest / 16)));
                this.scroll();
                requestAnimationFrame(tick);
            };

            requestAnimationFrame(tick);
        },

        /** Een knop met direct=true brengt de klant meteen naar de pagina, na het antwoord. */
        afterAnswer(live) {
            const direct = live.cards.find((card) => card.type === 'link' && card.direct && /^\/(?!\/)/.test(card.url ?? ''));

            if (direct && direct.url !== window.location.pathname) {
                setTimeout(() => { window.location.href = direct.url; }, 600);
            }
        },

        async problem(response) {
            const fixed = {
                401: 'Je bent uitgelogd. Log opnieuw in en probeer het nog eens.',
                419: 'Je sessie is verlopen. Ververs de pagina en probeer het opnieuw.',
                429: 'Even rustig aan: je stuurt veel berichten achter elkaar. Probeer het over een minuut weer.',
            }[response.status];

            if (fixed) return fixed;

            try {
                const data = await response.json();
                const first = data.errors ? Object.values(data.errors).flat()[0] : data.message;
                if (first) return String(first);
            } catch (error) {
                // Geen leesbaar antwoord; de algemene melding volstaat.
            }

            return `Er ging iets mis (${response.status}). Probeer het zo nog eens.`;
        },

        stop() {
            this.abort?.abort();
        },

        /** Haalt de mislukte vraag en het lege antwoord weg en stelt de vraag opnieuw. */
        retry(message) {
            const index = this.messages.indexOf(message);
            if (index < 0 || this.busy) return;

            const question = message.question ?? this.messages[index - 1]?.body;
            this.messages.splice(Math.max(0, index - 1), 2);
            this.send(question);
        },

        async upload(file) {
            if (! file || this.busy) return;

            this.busy = true;
            const body = new FormData();
            body.append('photo', file);

            try {
                const response = await fetch(config.upload + '?' + this.query(), { method: 'POST', headers: this.headers(), body });
                const data = await response.json();

                if (data.message && typeof data.message === 'object') {
                    this.messages.push(this.prepared(data.message));
                } else {
                    const text = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message ?? 'De foto kwam niet aan.');
                    this.messages.push(this.prepared({ id: 'e' + Date.now(), sender: 'ivy', body: String(text) }));
                }
            } catch (error) {
                this.messages.push(this.prepared({ id: 'e' + Date.now(), sender: 'ivy', body: 'De foto kwam niet aan. Probeer het nog eens.' }));
            } finally {
                this.busy = false;
                this.scroll(true);
            }
        },

        // Invoer --------------------------------------------------------------

        onEnter(event) {
            // Tijdens het samenstellen van een teken (IME) hoort Enter bij het toetsenbord.
            if (event.isComposing || event.shiftKey) return;

            event.preventDefault();
            this.send(this.draft);
        },

        resize() {
            this.$nextTick(() => {
                const input = this.$refs.input;
                if (! input) return;

                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 140) + 'px';
            });
        },

        // Meescrollen alleen zolang de klant onderaan zit; wie terugleest wordt niet weggetrokken.
        onScroll() {
            const log = this.$refs.log;
            if (log) this.follows = log.scrollHeight - log.scrollTop - log.clientHeight < 120;
        },

        scroll(force = false) {
            if (force) this.follows = true;
            if (! this.follows) return;

            this.$nextTick(() => {
                const log = this.$refs.log;
                if (log) log.scrollTop = log.scrollHeight;
            });
        },

        // Kopiëren, voorlezen, inspreken ------------------------------------------

        async copy(message) {
            try {
                await navigator.clipboard.writeText(message.body);
                this.copied = message.id;
                setTimeout(() => { if (this.copied === message.id) this.copied = null; }, 1600);
            } catch (error) {
                // Zonder klembord (oude browser) doet de knop niets.
            }
        },

        speak(message) {
            if (! this.canSpeak) return;

            if (this.speaking === message.id) {
                this.stopSpeaking();

                return;
            }

            this.stopSpeaking();

            const utterance = new SpeechSynthesisUtterance(forSpeech(message.body));
            utterance.lang = 'nl-NL';
            utterance.voice = this.voice ?? ivyVoice(window.speechSynthesis.getVoices());
            utterance.onend = () => { if (this.speaking === message.id) this.speaking = null; };
            utterance.onerror = utterance.onend;

            this.speaking = message.id;
            window.speechSynthesis.speak(utterance);
        },

        stopSpeaking() {
            if (this.canSpeak) window.speechSynthesis.cancel();
            this.speaking = null;
        },

        /** Inspreken met de spraakherkenning van de browser; na de laatste zin gaat het bericht weg. */
        dictate() {
            if (! this.canDictate || this.busy) return;

            if (this.listening) {
                this.stopListening();

                return;
            }

            const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new Recognition();
            let heard = '';

            recognition.lang = 'nl-NL';
            recognition.interimResults = true;
            recognition.continuous = false;
            recognition.onresult = (event) => {
                heard = Array.from(event.results).map((result) => result[0].transcript).join(' ').trim();
                this.draft = heard;
                this.resize();
            };
            recognition.onerror = () => { this.listening = false; };
            recognition.onend = () => {
                const wasListening = this.listening;
                this.listening = false;
                this.recognition = null;
                if (wasListening && heard) this.send(heard);
            };

            this.stopSpeaking();
            this.recognition = recognition;
            this.listening = true;
            recognition.start();
        },

        stopListening() {
            const recognition = this.recognition;
            this.listening = false;
            this.recognition = null;
            recognition?.abort();
        },
    };
}
