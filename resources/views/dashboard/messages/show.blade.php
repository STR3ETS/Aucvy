@use('App\Models\ConversationMessage')
@use('App\Support\Money\Money')

<x-layouts.dashboard :title="$conversation->lot->title" eyebrow="Berichten" :heading="$conversation->counterpartLabel($user)"
    :intro="$conversation->lot->public_id.' · '.$conversation->kindLabel().($conversation->partLabel() ? ' · '.$conversation->partLabel() : '').($conversation->lot->auction ? ' · stand '.Money::format($conversation->lot->currentPriceCents()) : '')">
    <x-slot:crumbs>
        <a href="{{ route('messages.index') }}">Berichten</a> <span aria-hidden="true">/</span> <span>{{ $conversation->lot->public_id }}</span>
    </x-slot:crumbs>

    <x-slot:actions>
        @if ($conversation->lot->status->isPublic())
            <a class="btn btn-white" href="{{ route('lots.show', $conversation->lot) }}">Bekijk de kavel</a>
        @endif
        @if ($conversation->order)
            <a class="btn btn-outline-white" href="{{ route('orders.show', $conversation->order) }}">Bestelling {{ $conversation->order->order_number }}</a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_280px] lg:items-start">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface" aria-label="Gesprek"
                 x-data="conversationThread({{ $conversation->id }}, {{ $messages->last()?->id ?? 0 }}, @js(route('messages.poll', $conversation)))">
            <p class="border-b border-aj-border px-4 py-2 text-xs text-aj-muted">
                {{ $conversation->hasOperator() ? __('messaging.notices.operator_joined') : __('messaging.notices.operator_watches') }}
                @if ($isBuyer) {{ __('messaging.notices.names') }} @endif
            </p>

            <ol class="space-y-4 p-4" x-ref="list" aria-live="polite">
                @foreach ($messages as $message)
                    <x-messaging.bubble :message="$message" :viewer="$user" :conversation="$conversation" />
                @endforeach
            </ol>

            @if (! $isBuyer && $conversation->kind === ConversationMessage::class || true)
                @php
                    $lastAnswer = ! $isBuyer ? $messages->last(fn ($m) => $m->sender_type === ConversationMessage::FROM_SELLER && ! $m->isPending()) : null;
                @endphp
            @endif

            @if ($conversation->isBlocked())
                <p class="border-t border-aj-border p-4 text-sm text-aj-muted">{{ __('messaging.errors.blocked') }}</p>
            @else
                <form method="POST" action="{{ route('messages.store', $conversation) }}" enctype="multipart/form-data" class="border-t border-aj-border p-4">
                    @csrf
                    <input type="hidden" name="client_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <label for="body" class="sr-only">Je bericht</label>
                    <textarea id="body" name="body" rows="3" required maxlength="{{ ConversationMessage::MAX_LENGTH }}" placeholder="Typ een bericht"
                              class="w-full d-textarea">{{ old('body') }}</textarea>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <label class="btn btn-ghost cursor-pointer" style="height:40px;font-size:13px">
                            Foto of PDF
                            <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only">
                        </label>
                        @if (! $isBuyer && $lastAnswer)
                            <form method="POST" action="{{ route('messages.publish', [$conversation, $lastAnswer]) }}" class="contents">
                                @csrf
                                <button type="submit" formaction="{{ route('messages.publish', [$conversation, $lastAnswer]) }}" class="btn btn-ghost" style="height:40px;font-size:13px" title="Dan hebben alle bieders dezelfde informatie. De naam van de vrager blijft verborgen.">Ook tonen bij de kavel</button>
                            </form>
                        @endif
                        <span class="ml-auto flex gap-2">
                            <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">Verstuur</button>
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-aj-muted">{{ __('messaging.notices.no_contact') }} Afspraken over prijs, betaling of ophalen tellen alleen in de bestelling.</p>
                </form>
            @endif
        </section>

        <aside class="space-y-4">
            <section class="d-card" aria-labelledby="kavel">
                <h2 id="kavel" class="text-sm font-extrabold">Kavel</h2>
                <a href="{{ $conversation->lot->status->isPublic() ? route('lots.show', $conversation->lot) : '#' }}" class="mt-2 block">
                    <span class="block aspect-[4/3] w-full overflow-hidden rounded-aj-sm border border-aj-border bg-aj-navy">
                        @if ($conversation->lot->media->isNotEmpty())
                            <img src="{{ $conversation->lot->media->first()->path }}" alt="" class="h-full w-full object-cover">
                        @endif
                    </span>
                    <span class="mt-2 block text-sm font-bold">{{ $conversation->lot->title }}</span>
                </a>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Status</dt><dd class="font-semibold">{{ $conversation->lot->status->label() }}</dd></div>
                    @if ($conversation->lot->auction)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Stand</dt><dd class="tabular font-semibold">{{ Money::format($conversation->lot->currentPriceCents()) }}</dd></div>
                    @endif
                    @if ($conversation->order)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Bestelling</dt><dd class="font-semibold">{{ $conversation->order->status->label() }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="d-card" aria-labelledby="veilig">
                <h2 id="veilig" class="text-sm font-extrabold">Veilig handelen</h2>
                <p class="mt-1 text-xs text-aj-muted">Betaling, prijs en ophaalafspraken lopen via de bestelling. Zo blijft het geld beschermd tot de ontvangst.</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @unless ($conversation->isBlocked())
                        <form method="POST" action="{{ route('messages.report', $conversation) }}" x-data="{ open: false }">
                            @csrf
                            <button type="button" class="btn btn-ghost" style="height:36px;font-size:12px" @click="open = ! open">Melden</button>
                            <div x-show="open" x-cloak class="mt-2">
                                <input type="text" name="note" maxlength="500" placeholder="Wat is er aan de hand?" class="w-full d-input" style="min-height:36px">
                                <button type="submit" class="btn btn-dark mt-2" style="height:36px;font-size:12px">Melding sturen</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('messages.block', $conversation) }}" onsubmit="return confirm('Gesprek sluiten? Je krijgt geen berichten meer van deze partij.')">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="height:36px;font-size:12px;color:var(--color-aj-error)">Blokkeren</button>
                        </form>
                    @endunless
                </div>
            </section>

            @if ($others->isNotEmpty())
                <section class="d-card" aria-labelledby="andere">
                    <h2 id="andere" class="text-sm font-extrabold">Andere gesprekken</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($others as $other)
                            <li><a href="{{ route('messages.show', $other) }}" class="block truncate hover:underline">{{ $other->lot->title }} <span class="text-xs text-aj-muted">· {{ $other->counterpartLabel($user) }}</span></a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('conversationThread', (id, lastId, pollUrl) => ({
                    lastId,
                    timer: null,
                    init() {
                        // Realtime via Reverb als die er is; anders elke tien seconden ophalen.
                        if (window.Echo) {
                            window.Echo.private(`conversation.${id}`).listen('.message.posted', () => this.fetch());
                        }
                        this.timer = setInterval(() => this.fetch(), 10000);
                        this.$nextTick(() => this.$refs.list.lastElementChild?.scrollIntoView({ block: 'nearest' }));
                    },
                    async fetch() {
                        try {
                            const response = await fetch(`${pollUrl}?after=${this.lastId}`, { headers: { Accept: 'application/json' } });
                            if (!response.ok) return;
                            const data = await response.json();
                            for (const message of data.messages) {
                                if (this.$refs.list.querySelector(`[data-message-id="${message.id}"]`)) continue;
                                this.$refs.list.insertAdjacentHTML('beforeend', message.html);
                                this.lastId = Math.max(this.lastId, message.id);
                            }
                        } catch (error) {}
                    },
                    destroy() { clearInterval(this.timer); window.Echo?.leave(`conversation.${id}`); },
                }));
            });
        </script>
    @endpush
</x-layouts.dashboard>
