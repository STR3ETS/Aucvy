@use('App\Models\ConversationMessage')
@use('App\Support\Money\Money')

@php
    $lot = $conversation->lot;
    $order = $conversation->order;
    $displayTimezone = config('aucvy.display_timezone');
    $dossierIcons = ['message' => 'message-circle', 'mail' => 'send', 'document' => 'eye', 'bid' => 'gavel', 'order' => 'banknote', 'payment' => 'banknote'];
    $dossierLimit = 4;
    // De uitleg achter het vraagteken: wie er meekijkt en waar afspraken gelden.
    $threadHelp = __('messaging.notices.operator_watches').($isBuyer ? ' '.__('messaging.notices.names') : '').' Betaling, prijs en ophaalafspraken lopen via de bestelling. Zo blijft het geld beschermd tot de ontvangst.';
    $lastAnswer = ! $isBuyer ? $messages->last(fn ($m) => $m->sender_type === ConversationMessage::FROM_SELLER && ! $m->isPending()) : null;
@endphp

{{--
    Eén gesprek: links de lijst, in het midden het gesprek met de acties als
    pictogram in de kop en het invoerveld altijd in beeld, rechts wat er rond
    de kavel gebeurde. Wisselen tussen gesprekken ververst alleen het gesprek
    en de kolom ernaast (messages-nav.js).
--}}
<x-layouts.dashboard :title="$lot->title" eyebrow="Berichten" heading="Berichten" intro="Elk gesprek hoort bij één kavel. Vragen, mail en status staan bij elkaar.">
    <x-slot:crumbs>
        <a href="{{ route('messages.index') }}">Berichten</a> <span aria-hidden="true">/</span> <span>{{ $lot->public_id }}</span>
    </x-slot:crumbs>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="d-soft is-err" style="margin:0 0 14px">{{ $errors->first() }}</div>
    @endif

    <div class="d-msg-layout is-open">
        @include('dashboard.messages.partials.list', ['items' => $conversations, 'current' => $conversation, 'user' => $user])

        <section class="d-thread" aria-label="Gesprek"
                 x-data="conversationThread({{ $conversation->id }}, {{ $messages->last()?->id ?? 0 }}, @js(route('messages.poll', $conversation)))">
            <div x-data="{ report: false }">
                <header class="d-thread-head">
                    <a class="d-iconbtn d-thread-back" href="{{ route('messages.index', ['page' => 1]) }}" data-tip="Alle gesprekken" aria-label="Terug naar alle gesprekken"><x-v4.icon name="arrow-left" :size="17" stroke="2" /></a>
                    <span class="d-conv-thumb" aria-hidden="true">
                        @if ($lot->media->isNotEmpty())
                            <img src="{{ $lot->media->first()->path }}" alt="">
                        @endif
                    </span>
                    <div class="d-thread-who">
                        <div class="d-titleline">
                            <strong class="d-thread-title">{{ $lot->title }}</strong>
                            <x-dashboard.help :text="$threadHelp" />
                        </div>
                        <span class="d-muted">Kavel #{{ $lot->public_id }} · met {{ $conversation->counterpartLabel($user) }}@if ($conversation->partLabel()) · {{ $conversation->partLabel() }}@endif</span>
                    </div>

                    {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                    <div class="d-thread-actions">
                        @if ($lot->status->isPublic())
                            <a href="{{ route('lots.show', $lot) }}" class="d-iconbtn" data-tip="Naar de kavel" aria-label="Naar de kavel {{ $lot->title }}"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                        @endif
                        @if ($order)
                            <a href="{{ route('orders.show', $order) }}" class="d-iconbtn" data-tip="Naar de bestelling" aria-label="Naar bestelling {{ $order->order_number }}"><x-v4.icon name="shopping-bag" :size="17" stroke="2" /></a>
                        @endif
                        @unless ($conversation->isBlocked())
                            <button type="button" class="d-iconbtn" :class="report && 'is-on'" data-tip="Melden" aria-label="Dit gesprek melden" :aria-expanded="report" @click="report = ! report; if (report) $nextTick(() => $refs.note.focus())"><x-v4.icon name="flag" :size="17" stroke="2" /></button>
                            <form method="POST" action="{{ route('messages.block', $conversation) }}" onsubmit="return confirm('Gesprek sluiten? Je krijgt geen berichten meer van deze partij.')">
                                @csrf
                                <button type="submit" class="d-iconbtn is-danger" data-tip="Blokkeren" aria-label="Deze partij blokkeren"><x-v4.icon name="ban" :size="17" stroke="2" /></button>
                            </form>
                        @endunless
                    </div>
                </header>

                @unless ($conversation->isBlocked())
                    <form method="POST" action="{{ route('messages.report', $conversation) }}" class="d-thread-report" x-show="report" x-cloak>
                        @csrf
                        <label for="melding" class="sr-only">Wat is er aan de hand?</label>
                        <input id="melding" x-ref="note" type="text" name="note" maxlength="500" placeholder="Wat is er aan de hand?" class="d-input" style="margin:0">
                        <button type="submit" class="d-btn d-btn-dark d-btn-sm d-btn-sq">Melding sturen</button>
                    </form>
                @endunless
            </div>

            <p class="d-thread-guard"><img src="{{ asset('images/agents/ivy.jpg') }}" alt=""><span>{{ $conversation->hasOperator() ? __('messaging.notices.operator_joined') : 'Ivy bewaakt dit gesprek.' }}</span></p>

            <ol class="d-thread-list" x-ref="list" aria-live="polite">
                @foreach ($messages as $message)
                    <x-messaging.bubble :message="$message" :viewer="$user" :conversation="$conversation" />
                @endforeach
            </ol>

            @if ($conversation->isBlocked())
                <p class="d-note" style="padding:14px 16px;border-top:1px solid rgba(14,21,38,.08)">{{ __('messaging.errors.blocked') }}</p>
            @else
                <form method="POST" action="{{ route('messages.store', $conversation) }}" enctype="multipart/form-data" class="d-thread-compose" x-data="{ file: '' }">
                    @csrf
                    <input type="hidden" name="client_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <p class="d-compose-file" x-show="file" x-cloak>
                        <x-v4.icon name="paperclip" :size="14" stroke="2" />
                        <span x-text="file"></span>
                        <button type="button" aria-label="Bijlage weghalen" @click="file = ''; $refs.file.value = ''"><x-v4.icon name="x" :size="14" stroke="2.4" /></button>
                    </p>
                    <div class="d-thread-compose-row">
                        <label class="d-iconbtn" data-tip="Foto of PDF toevoegen">
                            <x-v4.icon name="paperclip" :size="18" stroke="2" />
                            <span class="sr-only">Foto of PDF toevoegen</span>
                            <input type="file" name="attachment" x-ref="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only" @change="file = $event.target.files[0]?.name ?? ''">
                        </label>
                        <label for="body" class="sr-only">Je bericht</label>
                        <textarea id="body" name="body" rows="1" required maxlength="{{ ConversationMessage::MAX_LENGTH }}" placeholder="Typ je bericht" class="d-textarea"
                                  @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight + 2, 160) + 'px'"
                                  @keydown.enter.exact.prevent="$el.form.requestSubmit()">{{ old('body') }}</textarea>
                        @if ($lastAnswer)
                            <button type="submit" formaction="{{ route('messages.publish', [$conversation, $lastAnswer]) }}" formnovalidate class="d-iconbtn" data-tip="Antwoord ook bij de kavel tonen" aria-label="Je laatste antwoord ook bij de kavel tonen. Alle bieders hebben dan dezelfde informatie; de naam van de vrager blijft verborgen."><x-v4.icon name="megaphone" :size="18" stroke="2" /></button>
                        @endif
                        <button type="submit" class="d-iconbtn is-accent" data-tip="Versturen" aria-label="Bericht versturen"><x-v4.icon name="send" :size="18" stroke="2" /></button>
                    </div>
                    <p class="d-hint">{{ __('messaging.notices.no_contact') }}</p>
                </form>
            @endif
        </section>

        <aside class="d-card d-lotside" aria-labelledby="kavel" x-data="{ all: false }">
            <div class="d-titleline">
                <h2 id="kavel" class="d-h2">Over deze kavel</h2>
                <x-dashboard.help class="is-end" text="Wat er rond deze kavel gebeurde, voor zover jij het mag zien: vragen, documenten, biedingen en de bestelling." />
            </div>

            <p class="d-lotside-state">
                <span class="d-tag tone-atlas">{{ $lot->status->label() }}</span>
                @if ($order)
                    <span><b class="tabular">{{ Money::format($order->hammer_cents) }}</b> hamerprijs</span>
                @elseif ($lot->auction)
                    <span><b class="tabular">{{ Money::format($lot->currentPriceCents()) }}</b> stand</span>
                @else
                    <span class="d-muted">Nog geen veiling</span>
                @endif
            </p>

            <ul class="d-dossier">
                @if ($order)
                    <li>
                        <span class="d-dossier-icon" aria-hidden="true"><x-v4.icon name="shopping-bag" :size="14" stroke="2.2" /></span>
                        <span><strong>Bestelling {{ $order->order_number }}</strong><span>{{ $order->status->label() }}</span></span>
                    </li>
                @endif
                @foreach ($dossier as $activity)
                    <li @if ($loop->index >= $dossierLimit) x-show="all" x-cloak @endif>
                        <span class="d-dossier-icon" aria-hidden="true"><x-v4.icon :name="$dossierIcons[$activity->source ?? ''] ?? 'sparkles'" :size="14" stroke="2.2" /></span>
                        <span>
                            <strong>{{ $activity->label() }}</strong>
                            <span>{{ $activity->occurred_at?->timezone($displayTimezone)->isoFormat('D MMM, HH:mm') }} · {{ __('dossier.sources.'.$activity->source) }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>

            <x-dashboard.more :total="$dossier->count()" :limit="$dossierLimit" />
        </aside>
    </div>
</x-layouts.dashboard>
