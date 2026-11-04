{{--
    IvyLauncher en IvyPanel (Ivy-briefing hfst. 9): op elke pagina, start
    ingeklapt, rechtsonder; op mobiel boven de sticky biedbalk, nooit
    eroverheen. Desktop een kolom naast de pagina, mobiel volledig scherm.
    Behoudt het gesprek tussen pagina's. Zegt in de kop dat ze AI is.
--}}
@props(['lot' => null, 'page' => 'onbekend'])

@php $ivyEnabled = (bool) config('aucvy.ai.enabled') && \App\Ivy\Chat\IvyFlags::on('ivy.enabled'); @endphp

@if ($ivyEnabled)
<div x-data="ivyPanel({{ Js::from(['show' => route('ivy.show'), 'send' => route('ivy.send'), 'upload' => route('ivy.upload'), 'lot' => $lot?->slug, 'page' => $page, 'name' => auth()->user()?->name]) }})"
     class="ivy-root" @ivy-open.window="open($event.detail?.text)" @keydown.escape.window="opened = false">
    <style>
        .ivy-root{--ivy-offset:0px}
        .ivy-fab{position:fixed;right:16px;bottom:calc(16px + var(--ivy-offset,0px) + env(safe-area-inset-bottom,0px));z-index:60;display:flex;align-items:center;gap:8px;height:48px;padding:0 16px 0 8px;border-radius:999px;background:#0c1326;color:#fff;font-weight:800;font-size:14px;box-shadow:0 12px 30px -12px rgba(12,19,38,.6);border:0;cursor:pointer}
        .ivy-fab img{width:32px;height:32px;border-radius:50%;object-fit:cover}
        .ivy-fab .count{min-width:20px;height:20px;padding:0 6px;border-radius:999px;background:#2f6bff;font-size:11px;line-height:20px;text-align:center}
        .ivy-panel{position:fixed;z-index:70;right:16px;bottom:calc(72px + var(--ivy-offset,0px) + env(safe-area-inset-bottom,0px));width:min(400px,calc(100vw - 32px));height:min(620px,calc(100vh - 110px));display:flex;flex-direction:column;background:#fff;border:1px solid rgba(14,21,38,.09);border-radius:14px;box-shadow:0 24px 60px -24px rgba(12,19,38,.5);overflow:hidden}
        @media (max-width:640px){.ivy-panel{inset:0;width:100%;height:100%;border-radius:0;bottom:0;right:0}}
        .ivy-head{display:flex;align-items:center;gap:10px;padding:12px 14px;background:#0c1326;color:#fff}
        .ivy-head img{width:36px;height:36px;border-radius:50%;object-fit:cover}
        .ivy-log{flex:1;overflow:auto;padding:14px;display:flex;flex-direction:column;gap:10px;background:#f7f8fb}
        .ivy-msg{max-width:88%;padding:10px 12px;border-radius:14px;font-size:14px;line-height:1.5;white-space:pre-line}
        .ivy-msg.ivy{align-self:flex-start;background:#fff;border:1px solid rgba(14,21,38,.09);border-bottom-left-radius:4px}
        .ivy-msg.user{align-self:flex-end;background:#2f6bff;color:#fff;border-bottom-right-radius:4px}
        .ivy-card{align-self:stretch;background:#fff;border:1px solid rgba(14,21,38,.09);border-radius:12px;padding:10px 12px;font-size:13px}
        .ivy-card a{font-weight:800;color:#1e56e0;text-decoration:none}
        .ivy-chips{display:flex;flex-wrap:wrap;gap:6px;padding:8px 14px 0;background:#f7f8fb}
        .ivy-chips button{border:1px solid rgba(14,21,38,.18);background:#fff;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;min-height:32px;cursor:pointer}
        .ivy-form{display:flex;gap:8px;padding:10px 14px;border-top:1px solid rgba(14,21,38,.09);background:#fff}
        .ivy-form input{flex:1;min-height:44px;border:1px solid rgba(14,21,38,.18);border-radius:12px;padding:0 12px;font-size:16px}
        .ivy-form button{min-height:44px;min-width:44px;border-radius:12px;background:#2f6bff;color:#fff;border:0;font-weight:800;cursor:pointer;padding:0 14px}
        .ivy-note{padding:6px 14px 10px;font-size:11px;color:#6b7390;background:#fff}
    </style>

    <button type="button" class="ivy-fab" @click="toggle()" :aria-expanded="opened" aria-controls="ivy-panel" aria-label="Open Ivy, de AI-assistent">
        <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
        <span>Ivy</span>
        <span class="count" x-show="openCount > 0" x-text="openCount" x-cloak></span>
    </button>

    <section id="ivy-panel" class="ivy-panel" x-show="opened" x-cloak aria-label="Ivy, AI-assistent" role="dialog">
        <header class="ivy-head">
            <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
            <div class="min-w-0 flex-1">
                <p class="text-sm font-extrabold leading-tight">Ivy <span class="font-normal opacity-80">· AI-assistent van Aucvy</span></p>
                <p class="truncate text-xs opacity-70" x-text="lotTitle ? 'Over: ' + lotTitle : 'Gesprek blijft bewaard om je te helpen'"></p>
            </div>
            <a x-show="inboxUrl" :href="inboxUrl" class="rounded-full bg-white/10 px-2 py-1 text-xs font-bold" x-cloak>Inbox <span x-text="openCount"></span></a>
            <button type="button" class="rounded-full bg-white/10 px-2 py-1 text-xs font-bold" @click="opened = false" aria-label="Sluiten">×</button>
        </header>

        <div class="ivy-log" x-ref="log">
            <template x-if="greeting"><div class="ivy-msg ivy" x-text="greeting"></div></template>
            <template x-for="message in messages" :key="message.id">
                <div style="display:contents">
                    <div class="ivy-msg" :class="message.sender === 'user' ? 'user' : 'ivy'" x-text="message.body"></div>
                    <template x-for="(card, index) in (message.cards ?? [])" :key="message.id + '-' + index">
                        <div class="ivy-card">
                            <template x-if="card.type === 'lot'">
                                <div class="flex items-start justify-between gap-2">
                                    <div><a :href="card.url" x-text="card.title"></a><div class="text-xs text-aj-muted" x-text="[card.meta, card.ends ? 'sluit ' + card.ends : null, card.badge].filter(Boolean).join(' · ')"></div></div>
                                    <div class="text-right"><strong x-text="card.price"></strong><div class="text-xs text-aj-muted" x-text="card.price_label"></div></div>
                                </div>
                            </template>
                            <template x-if="card.type === 'proposal'">
                                <div class="flex items-center justify-between gap-2">
                                    <div><strong x-text="card.title"></strong> <span x-text="card.amount"></span><div class="text-xs text-aj-muted">Niets gebeurt zonder jouw ja.</div></div>
                                    <a :href="card.url" class="rounded-full bg-aj-navy px-3 py-1 text-xs font-bold text-white">Bekijk en bevestig</a>
                                </div>
                            </template>
                            <template x-if="card.type === 'example'">
                                <div><span class="rounded-full bg-aj-bg px-2 py-0.5 text-[11px] font-bold" x-text="card.label"></span><p class="mt-1" x-text="card.body"></p></div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
            <div class="ivy-msg ivy" x-show="busy" x-cloak>Ivy denkt na…</div>
        </div>

        <div class="ivy-chips" x-show="chips.length && !busy">
            <template x-for="chip in chips" :key="chip"><button type="button" @click="send(chip)" x-text="chip"></button></template>
        </div>

        <form class="ivy-form" @submit.prevent="send(draft)">
            <input type="text" x-model="draft" placeholder="Typ je vraag" aria-label="Je bericht aan Ivy" maxlength="2000" :disabled="busy || !enabled">
            <label class="flex cursor-pointer items-center" title="Stuur een foto">
                <input type="file" accept="image/*" class="sr-only" @change="upload($event.target.files[0]); $event.target.value = ''">
                <span class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-aj-sm border border-aj-border-strong text-lg" aria-hidden="true">📷</span>
            </label>
            <button type="submit" :disabled="busy || !draft.trim() || !enabled">Stuur</button>
        </form>
        <p class="ivy-note" x-text="enabled ? 'Ivy is een AI-assistent. Een bod of aankoop bevestig je altijd zelf op een scherm met het actuele bedrag en alle kosten.' : 'Ivy staat even uit. Bieden, zoeken en je meldingen werken gewoon.'"></p>
    </section>
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('ivyPanel', (config) => ({
                opened: false, loaded: false, busy: false, enabled: true, draft: '', greeting: null, chips: [], messages: [], openCount: 0, inboxUrl: null, lotTitle: null,
                query() { return new URLSearchParams({ lot: config.lot ?? '', page: config.page ?? '' }).toString(); },
                headers() { return { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' }; },
                async init() {
                    try {
                        const response = await fetch(config.show + '?' + this.query(), { headers: this.headers() });
                        const data = await response.json();
                        this.openCount = data.open_count ?? 0;
                        this.inboxUrl = data.inbox_url;
                    } catch (e) {}
                },
                async toggle() { this.opened = !this.opened; if (this.opened && !this.loaded) await this.load(); },
                async open(text) { this.opened = true; if (!this.loaded) await this.load(); if (text) await this.send(text); },
                async load() {
                    try {
                        const response = await fetch(config.show + '?' + this.query(), { headers: this.headers() });
                        const data = await response.json();
                        this.enabled = data.enabled; this.greeting = data.greeting; this.chips = data.chips ?? []; this.messages = data.messages ?? [];
                        this.openCount = data.open_count ?? 0; this.inboxUrl = data.inbox_url; this.lotTitle = data.lot?.title ?? null; this.loaded = true;
                        this.scroll();
                    } catch (e) { this.greeting = 'Ivy is even niet bereikbaar. Alles op Aucvy werkt gewoon zonder haar.'; }
                },
                async send(text) {
                    text = (text ?? '').trim();
                    if (!text || this.busy) return;
                    this.draft = ''; this.busy = true; this.chips = [];
                    this.messages.push({ id: 'u' + Date.now(), sender: 'user', body: text, cards: [] });
                    this.scroll();
                    try {
                        const response = await fetch(config.send + '?' + this.query(), { method: 'POST', headers: { ...this.headers(), 'Content-Type': 'application/json' }, body: JSON.stringify({ text }) });
                        const data = await response.json();
                        if (data.message) this.messages.push(data.message);
                        if (typeof data.open_count === 'number') this.openCount = data.open_count;
                    } catch (e) {
                        this.messages.push({ id: 'e' + Date.now(), sender: 'ivy', body: 'Dat lukte nu niet. Probeer het zo nog eens of vraag een medewerker.', cards: [] });
                    } finally { this.busy = false; this.scroll(); }
                },
                async upload(file) {
                    if (!file || this.busy) return;
                    this.busy = true;
                    const body = new FormData(); body.append('photo', file);
                    try {
                        const response = await fetch(config.upload + '?' + this.query(), { method: 'POST', headers: this.headers(), body });
                        const data = await response.json();
                        if (data.message) this.messages.push(data.message); else if (data.message === undefined && data.errors) this.messages.push({ id: 'e' + Date.now(), sender: 'ivy', body: Object.values(data.errors).flat().join(' '), cards: [] });
                    } catch (e) {} finally { this.busy = false; this.scroll(); }
                },
                scroll() { this.$nextTick(() => { const log = this.$refs.log; if (log) log.scrollTop = log.scrollHeight; }); },
            }));
        });
    </script>
@endonce
@endif
