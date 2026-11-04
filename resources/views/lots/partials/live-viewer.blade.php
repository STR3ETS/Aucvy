{{--
    Kijker bij een lopende uitzending (briefing nieuwe functies): boven de
    foto's, kijken zonder account, bieden via de bestaande knop. Valt het
    beeld weg, dan ziet iedereen een melding en loopt de veiling door.
--}}
<section class="rounded-aj-s border border-aj-error/30 bg-aj-surface p-3" aria-label="Live uitzending"
         x-data="liveViewer({{ Js::from(['endpoint' => route('api.lots.live', $lot), 'script' => (string) config('aucvy.live.client_script')]) }})">
    <div class="flex items-center justify-between gap-2 px-1">
        <span class="inline-flex items-center gap-2 text-sm font-extrabold text-aj-error"><span class="size-2 animate-pulse rounded-full bg-aj-error"></span> Live vanaf de kavel</span>
        <span class="tabular text-xs text-aj-muted" x-text="viewers !== null ? viewers + ' kijkers' : ''"></span>
    </div>
    <div class="mt-2 overflow-hidden rounded-aj-sm bg-aj-navy" style="aspect-ratio:16/9">
        <video x-ref="video" autoplay playsinline controls class="h-full w-full object-contain"></video>
    </div>
    <p class="mt-2 px-1 text-xs text-aj-muted" x-text="notice"></p>
</section>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('liveViewer', (config) => ({
                viewers: null, notice: 'Verbinden met de uitzending…', room: null,
                async init() {
                    try {
                        const token = (localStorage.getItem('aucvy-viewer') ?? '').slice(0, 64);
                        const response = await fetch(config.endpoint + (token ? '?token=' + encodeURIComponent(token) : ''), { headers: { 'Accept': 'application/json' } });
                        const data = await response.json();
                        if (!data.live) { this.notice = 'De uitzending is gestopt. De veiling loopt gewoon door.'; return; }
                        if (data.full) { this.notice = data.message; return; }
                        this.viewers = data.viewers;
                        if (!data.url) { this.notice = 'Er is nog geen mediaserver gekoppeld; het beeld volgt zodra LiveKit is ingesteld. Bieden kan gewoon.'; return; }
                        if (!window.LivekitClient && config.script) {
                            await new Promise((resolve, reject) => { const s = document.createElement('script'); s.src = config.script; s.onload = resolve; s.onerror = reject; document.head.appendChild(s); });
                        }
                        const { Room, RoomEvent } = window.LivekitClient;
                        this.room = new Room({ adaptiveStream: true });
                        this.room.on(RoomEvent.TrackSubscribed, (track) => { if (track.kind === 'video') track.attach(this.$refs.video); if (track.kind === 'audio') track.attach(); });
                        this.room.on(RoomEvent.Disconnected, () => { this.notice = 'Het beeld is weggevallen. De veiling loopt door en bieden blijft werken.'; });
                        this.room.on(RoomEvent.ParticipantConnected, () => { this.viewers = Math.max(0, this.room.numParticipants - 1); });
                        this.room.on(RoomEvent.ParticipantDisconnected, () => { this.viewers = Math.max(0, this.room.numParticipants - 1); });
                        await this.room.connect(data.url, data.token);
                        this.notice = 'Beeld van de verkoper. Bieden via de knop hiernaast; de prijs loopt live mee.';
                    } catch (e) {
                        this.notice = 'Het beeld kon niet laden. De veiling loopt gewoon door en bieden blijft werken.';
                    }
                },
            }));
        });
    </script>
@endonce
