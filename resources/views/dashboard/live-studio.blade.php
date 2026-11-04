@use('App\Models\LiveSession')
@use('App\Support\Money\Money')

<x-layouts.dashboard title="Live uitzenden" eyebrow="Studio" :intro="$lot->title.' · '.$lot->public_id.($lot->auction ? ' · stand '.Money::format($lot->currentPriceCents()) : '')">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.lots') }}">Mijn kavels</a> <span aria-hidden="true">/</span> <a href="{{ route('dashboard.lots.show', $lot) }}">{{ $lot->public_id }}</a> <span aria-hidden="true">/</span> <span>Live</span>
    </x-slot:crumbs>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="studio"
                 x-data="liveStudio({{ Js::from(['session' => $session?->id, 'status' => $session?->status, 'token' => $token, 'url' => $clientUrl, 'script' => $clientScript, 'room' => $session?->room, 'goLiveUrl' => $session ? route('dashboard.lots.live.go', [$lot, $session]) : null]) }})">
            <h2 id="studio" class="text-lg font-extrabold">Studio</h2>
            <p class="mt-1 text-sm text-aj-muted">Eerst de camera- en verbindingscheck, dan de aftelling, dan pas live. Valt het beeld weg, dan loopt de veiling door en blijft bieden werken.</p>

            @if ($session === null)
                @if ($blocker)
                    <p class="mt-4 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-3 text-sm">{{ $blocker }}</p>
                @else
                    <form method="POST" action="{{ route('dashboard.lots.live.start', $lot) }}" class="mt-4">@csrf<button type="submit" class="btn btn-primary" style="height:42px;font-size:14px">Kamer klaarzetten</button></form>
                    <p class="mt-2 text-xs text-aj-muted">Je hebt minimaal 3 Mbit/s stabiele upload nodig; op 4G kost een uur ongeveer 1,1 GB.</p>
                @endif
            @else
                <div class="mt-4 overflow-hidden rounded-aj-s border border-aj-border bg-aj-navy" style="aspect-ratio:16/9">
                    <video x-ref="preview" autoplay muted playsinline class="h-full w-full object-cover"></video>
                </div>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                    <div class="rounded-aj-sm bg-aj-bg p-3"><dt class="text-xs text-aj-muted">Camera</dt><dd class="font-bold" x-text="checks.camera"></dd></div>
                    <div class="rounded-aj-sm bg-aj-bg p-3"><dt class="text-xs text-aj-muted">Verbinding</dt><dd class="font-bold" x-text="checks.connection"></dd></div>
                    <div class="rounded-aj-sm bg-aj-bg p-3"><dt class="text-xs text-aj-muted">Status</dt><dd class="font-bold" x-text="label"></dd></div>
                </dl>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <template x-if="status === 'preparing'">
                        <button type="button" class="btn btn-primary" style="height:42px;font-size:14px" @click="startCountdown()" :disabled="!ready || countdown !== null">
                            <span x-text="countdown === null ? 'Live gaan (aftelling 5 s)' : countdown"></span>
                        </button>
                    </template>
                    <template x-if="status === 'live'">
                        <span class="inline-flex items-center gap-2 rounded-full bg-aj-error/10 px-3 py-1 text-sm font-extrabold text-aj-error"><span class="size-2 animate-pulse rounded-full bg-aj-error"></span> LIVE</span>
                    </template>
                    <form method="POST" action="{{ route('dashboard.lots.live.end', [$lot, $session]) }}">@csrf<button type="submit" class="btn btn-ghost" style="height:42px;font-size:14px">Uitzending stoppen</button></form>
                </div>
                <p class="mt-2 text-xs text-aj-muted" x-show="!url" x-cloak>Er is nog geen mediaserver gekoppeld; de studio draait in oefenstand. Zodra LiveKit is ingesteld gaat het beeld naar de kijkers.</p>
                <p class="mt-2 text-xs text-aj-error" x-show="error" x-text="error" x-cloak></p>
            @endif
        </section>

        <aside class="space-y-4">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="kijkers">
                <h2 id="kijkers" class="text-sm font-extrabold">Kijkers</h2>
                <p class="tabular mt-1 text-2xl font-extrabold">{{ $session?->viewers_now ?? 0 }} <span class="text-xs font-normal text-aj-muted">nu · piek {{ $session?->peak_viewers ?? 0 }} · maximaal {{ $session?->max_viewers ?? config('aucvy.live.max_viewers') }}</span></p>
                <p class="mt-2 text-xs text-aj-muted">Bieden gaat via de biedknop naast het beeld, niet via de video. De prijs loopt live mee voor iedereen.</p>
            </section>
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="regels">
                <h2 id="regels" class="text-sm font-extrabold">Spelregels</h2>
                <ul class="mt-2 list-disc space-y-1 pl-4 text-xs text-aj-muted">
                    <li>Alleen een geverifieerde zakelijke verkoper, op een eigen kavel met lopende veiling.</li>
                    <li>Opname staat uit; opnemen vraagt toestemming en een bewaartermijn.</li>
                    <li>Maximaal {{ config('aucvy.live.max_viewers') }} kijkers per uitzending; maandbudget {{ config('aucvy.live.monthly_budget_minutes') }} kijkminuten met een alert.</li>
                    <li>Kijken kan zonder account; bieden volgt de bestaande drempels.</li>
                </ul>
            </section>
            @if ($history->isNotEmpty())
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="eerder">
                    <h2 id="eerder" class="text-sm font-extrabold">Eerdere uitzendingen</h2>
                    <ul class="mt-2 divide-y divide-aj-border text-xs">
                        @foreach ($history as $past)
                            <li class="py-1.5">{{ $past->started_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? 'niet gestart' }} · {{ $past->statusLabel() }} · piek {{ $past->peak_viewers }} kijkers</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('liveStudio', (config) => ({
                status: config.status, url: config.url, error: null, countdown: null, room: null,
                checks: { camera: 'controleren…', connection: 'controleren…' },
                get ready() { return this.checks.camera === 'ok' && this.checks.connection !== 'geen'; },
                get label() { return { preparing: 'Klaar om te starten', live: 'Live' }[this.status] ?? this.status; },
                async init() {
                    if (!config.session) return;
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: true });
                        this.$refs.preview.srcObject = stream;
                        this.stream = stream;
                        this.checks.camera = 'ok';
                    } catch (e) {
                        this.checks.camera = 'geen toegang';
                    }
                    const connection = navigator.connection;
                    this.checks.connection = !navigator.onLine ? 'geen' : (connection?.effectiveType ? connection.effectiveType + (connection.downlink ? ' · ' + connection.downlink + ' Mbit/s' : '') : 'ok');
                    if (this.status === 'live' && this.url) this.publish();
                },
                startCountdown() {
                    this.countdown = 5;
                    const tick = setInterval(async () => {
                        this.countdown -= 1;
                        if (this.countdown <= 0) {
                            clearInterval(tick);
                            this.countdown = null;
                            await this.goLive();
                        }
                    }, 1000);
                },
                async goLive() {
                    const response = await fetch(config.goLiveUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '', 'Accept': 'application/json' } });
                    if (!response.ok) { this.error = 'Live gaan lukte niet. Probeer het opnieuw.'; return; }
                    this.status = 'live';
                    if (this.url) await this.publish();
                },
                async publish() {
                    try {
                        if (!window.LivekitClient && config.script) {
                            await new Promise((resolve, reject) => { const s = document.createElement('script'); s.src = config.script; s.onload = resolve; s.onerror = reject; document.head.appendChild(s); });
                        }
                        if (!window.LivekitClient) { this.error = 'De videoclient kon niet laden. De veiling loopt door; probeer de pagina te verversen.'; return; }
                        const { Room, createLocalTracks } = window.LivekitClient;
                        this.room = new Room({ adaptiveStream: true, dynacast: true });
                        this.room.on('disconnected', () => { this.error = 'Verbinding met de mediaserver weggevallen. De veiling loopt door; vernieuw om opnieuw te zenden.'; });
                        await this.room.connect(this.url, config.token);
                        const tracks = await createLocalTracks({ audio: true, video: { facingMode: 'environment' } });
                        for (const track of tracks) await this.room.localParticipant.publishTrack(track);
                    } catch (e) {
                        this.error = 'Zenden lukte niet: ' + (e?.message ?? 'onbekende fout') + '. De veiling loopt gewoon door.';
                    }
                },
            }));
        });
    </script>
</x-layouts.dashboard>
