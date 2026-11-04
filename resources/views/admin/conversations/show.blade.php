@use('App\Models\ConversationMessage')
@use('App\Support\Money\Money')

<x-layouts.admin :title="$conversation->lot->title" :subtitle="$conversation->sellerLabel().' en '.$conversation->buyerLabel().($conversation->flag ? '. '.__('messaging.signals.'.$conversation->flag) : '')">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.conversations') }}" class="underline">Gesprekken</a> · <a href="{{ route('admin.lots.dossier', $conversation->lot) }}" class="underline">Volledig dossier</a>
        @if ($conversation->order) · <a href="{{ route('admin.orders.show', $conversation->order) }}" class="underline">Order {{ $conversation->order->order_number }}</a> @endif
    </p>

    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <p class="mt-4 rounded-aj-sm border border-aj-blue/30 bg-aj-blue/5 p-3 text-sm">
        <span class="font-bold">Partijen zien dat jij meeleest zodra je deelneemt.</span> Interne notities blijven alleen zichtbaar voor het team. <span class="text-aj-muted">Inzage gelogd.</span>
    </p>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
        <div class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface" aria-label="Gesprek">
                <ol class="space-y-4 p-4">
                    @foreach ($conversation->messages as $message)
                        @if ($message->internal)
                            <li class="rounded-aj-sm border border-dashed border-aj-border-strong bg-aj-bg p-3 text-sm">
                                <span class="text-xs font-bold text-aj-muted">Interne notitie · {{ $message->sender?->name }} · {{ $message->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span>
                                <span class="mt-1 block whitespace-pre-line">{{ $message->body }}</span>
                            </li>
                        @else
                            <x-messaging.bubble :message="$message" :viewer="null" :conversation="$conversation" :as-staff="true" />
                            @if ($message->state === ConversationMessage::HELD)
                                <li class="flex flex-wrap gap-2 pl-2">
                                    <form method="POST" action="{{ route('admin.conversations.release', [$conversation, $message]) }}">@csrf<button type="submit" class="btn btn-ghost" style="height:34px;font-size:12px">Toch vrijgeven</button></form>
                                    <form method="POST" action="{{ route('admin.conversations.confirm', [$conversation, $message]) }}">@csrf<button type="submit" class="btn btn-dark" style="height:34px;font-size:12px">Tegenhouden bevestigen</button></form>
                                </li>
                            @endif
                        @endif
                    @endforeach
                </ol>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="reageren" x-data="{ mode: 'reply' }">
                <h2 id="reageren" class="text-sm font-extrabold">Reageren</h2>
                <div class="mt-2 flex gap-2 text-sm">
                    <button type="button" class="rounded-full px-3 py-1 font-bold" :class="mode === 'reply' ? 'bg-aj-navy text-white' : 'border border-aj-border'" @click="mode = 'reply'">Reageren in het gesprek</button>
                    <button type="button" class="rounded-full px-3 py-1 font-bold" :class="mode === 'note' ? 'bg-aj-navy text-white' : 'border border-aj-border'" @click="mode = 'note'">Interne notitie</button>
                </div>
                <form method="POST" action="{{ route('admin.conversations.reply', $conversation) }}" x-show="mode === 'reply'" class="mt-3">
                    @csrf
                    <textarea name="body" rows="3" required class="w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm" placeholder="Bericht aan beide partijen"></textarea>
                    <button type="submit" class="btn btn-primary mt-2" style="height:40px;font-size:13px">Deelnemen en versturen</button>
                </form>
                <form method="POST" action="{{ route('admin.conversations.note', $conversation) }}" x-show="mode === 'note'" x-cloak class="mt-3">
                    @csrf
                    <textarea name="body" rows="3" required class="w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm" placeholder="Notitie voor het team"></textarea>
                    <button type="submit" class="btn btn-dark mt-2" style="height:40px;font-size:13px">Notitie opslaan</button>
                </form>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="bot">
                <h2 id="bot" class="text-sm font-extrabold">Waarom de bot dit tegenhield</h2>
                @php $moderations = $conversation->messages->flatMap->moderations->sortByDesc('id'); @endphp
                @if ($moderations->isEmpty())
                    <p class="mt-1 text-xs text-aj-muted">Geen botbeslissing; {{ $conversation->reported_at ? 'gemeld door een deelnemer' : 'geopend met een reden' }}.</p>
                @else
                    <ul class="mt-2 space-y-2 text-xs">
                        @foreach ($moderations->take(5) as $moderation)
                            <li>
                                <span class="font-bold">{{ $moderation->signalLabel() }}</span> · {{ $moderation->prompt_version ? 'Ivy' : 'Regel' }} versie {{ $moderation->prompt_version ?? $moderation->rule_version }} · zekerheid {{ (int) round($moderation->confidence * 100) }}% · {{ $moderation->action }}
                                @if ($moderation->reviewed_at) · {{ $moderation->review_action }} door {{ $moderation->reviewer?->name }} @endif
                                @if ($moderation->explanation)<span class="block text-aj-muted">{{ $moderation->explanation }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($conversation->report_note)
                    <p class="mt-2 text-xs"><span class="font-bold">Melding:</span> {{ $conversation->report_note }}</p>
                @endif
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="transactie">
                <h2 id="transactie" class="text-sm font-extrabold">Transactie</h2>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Kavel</dt><dd class="font-semibold">{{ $conversation->lot->status->label() }}</dd></div>
                    @if ($conversation->lot->auction)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Stand</dt><dd class="tabular font-semibold">{{ Money::format($conversation->lot->currentPriceCents()) }}</dd></div>
                    @endif
                    @if ($conversation->order)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Order</dt><dd class="font-semibold">{{ $conversation->order->status->label() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Bedrag</dt><dd class="tabular font-semibold">{{ Money::format($conversation->order->total_cents) }}</dd></div>
                    @endif
                    @if ($conversation->dispute_id)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Geschil</dt><dd class="font-semibold">#{{ $conversation->dispute_id }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="acties">
                <h2 id="acties" class="text-sm font-extrabold">Acties</h2>
                <div class="mt-2 space-y-3">
                    @if ($conversation->order && $conversation->dispute_id === null)
                        <form method="POST" action="{{ route('admin.conversations.dispute', $conversation) }}" x-data="{ open: false }">
                            @csrf
                            <button type="button" class="btn btn-ghost w-full justify-center" style="height:38px;font-size:13px" @click="open = ! open">Geschil starten</button>
                            <div x-show="open" x-cloak class="mt-2">
                                <textarea name="reason" rows="2" required minlength="10" class="w-full rounded-aj-sm border border-aj-border-strong p-2 text-sm" placeholder="Waarom, voor beide partijen"></textarea>
                                <button type="submit" class="btn btn-dark mt-2 w-full justify-center" style="height:36px;font-size:12px">Geschil starten met dit gesprek als bewijs</button>
                            </div>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.conversations.warn', $conversation) }}" x-data="{ open: false }">
                        @csrf
                        <button type="button" class="btn btn-ghost w-full justify-center" style="height:38px;font-size:13px" @click="open = ! open">Verkoper waarschuwen</button>
                        <div x-show="open" x-cloak class="mt-2">
                            <textarea name="note" rows="2" required minlength="10" class="w-full rounded-aj-sm border border-aj-border-strong p-2 text-sm" placeholder="Wat moet de verkoper weten"></textarea>
                            <button type="submit" class="btn btn-dark mt-2 w-full justify-center" style="height:36px;font-size:12px">Waarschuwing sturen</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="inzage">
                <h2 id="inzage" class="text-sm font-extrabold">Inzage</h2>
                <ul class="mt-2 space-y-1 text-xs text-aj-muted">
                    @foreach ($conversation->accessLogs->sortByDesc('opened_at')->take(5) as $log)
                        <li>{{ $log->staff?->name }} · {{ $log->opened_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}{{ $log->reason ? ' · '.$log->reason : '' }}</li>
                    @endforeach
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.admin>
