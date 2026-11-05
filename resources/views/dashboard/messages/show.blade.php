@use('App\Models\ConversationMessage')
@use('App\Support\Money\Money')

@php
    $lot = $conversation->lot;
    $displayTimezone = config('aucvy.display_timezone');
    $dossierIcons = ['message' => 'message-circle', 'mail' => 'send', 'document' => 'eye', 'bid' => 'gavel', 'order' => 'banknote', 'payment' => 'banknote'];
@endphp

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

    <div class="d-msg-layout">
        @include('dashboard.messages.partials.list', ['items' => $conversations, 'current' => $conversation, 'user' => $user])

        <section class="d-thread" aria-label="Gesprek"
                 x-data="conversationThread({{ $conversation->id }}, {{ $messages->last()?->id ?? 0 }}, @js(route('messages.poll', $conversation)))">
            <header class="d-thread-head">
                <span class="d-conv-thumb" aria-hidden="true">
                    @if ($lot->media->isNotEmpty())
                        <img src="{{ $lot->media->first()->path }}" alt="">
                    @endif
                </span>
                <div style="min-width:0;flex:1">
                    <strong style="display:block;font-size:15px">{{ $lot->title }}</strong>
                    <span class="d-muted">Kavel #{{ $lot->public_id }} · met {{ $conversation->counterpartLabel($user) }}@if ($conversation->partLabel()) · {{ $conversation->partLabel() }}@endif</span>
                </div>
            </header>

            <p class="d-ivy-note" style="margin:12px 16px 0"><img src="{{ asset('images/agents/ivy.jpg') }}" alt=""><span><strong style="color:#6429c9">Ivy, bewaakt dit gesprek:</strong> {{ $conversation->hasOperator() ? __('messaging.notices.operator_joined') : __('messaging.notices.operator_watches') }}@if ($isBuyer) {{ __('messaging.notices.names') }}@endif</span></p>

            <ol class="d-thread-list" x-ref="list" aria-live="polite">
                @foreach ($messages as $message)
                    <x-messaging.bubble :message="$message" :viewer="$user" :conversation="$conversation" />
                @endforeach
            </ol>

            @php
                $lastAnswer = ! $isBuyer ? $messages->last(fn ($m) => $m->sender_type === ConversationMessage::FROM_SELLER && ! $m->isPending()) : null;
            @endphp

            @if ($conversation->isBlocked())
                <p class="d-note" style="padding:14px 16px;border-top:1px solid rgba(14,21,38,.08)">{{ __('messaging.errors.blocked') }}</p>
            @else
                <form method="POST" action="{{ route('messages.store', $conversation) }}" enctype="multipart/form-data" class="d-thread-compose">
                    @csrf
                    <input type="hidden" name="client_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <div class="d-thread-compose-row">
                        <label for="body" class="sr-only">Je bericht</label>
                        <textarea id="body" name="body" rows="1" required maxlength="{{ ConversationMessage::MAX_LENGTH }}" placeholder="Typ je bericht" class="d-textarea" style="min-height:44px">{{ old('body') }}</textarea>
                        <button type="submit" class="d-btn d-btn-sq">Versturen</button>
                    </div>
                    <div class="d-chips" style="gap:6px;margin-top:8px">
                        <label class="d-chip d-chip-sm" style="cursor:pointer">
                            Foto of PDF
                            <input type="file" name="attachment" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only">
                        </label>
                        @if (! $isBuyer && $lastAnswer)
                            <button type="submit" formaction="{{ route('messages.publish', [$conversation, $lastAnswer]) }}" formnovalidate class="d-chip d-chip-sm" title="Dan hebben alle bieders dezelfde informatie. De naam van de vrager blijft verborgen.">Ook tonen bij de kavel</button>
                        @endif
                    </div>
                    <p class="d-hint" style="margin-top:8px">{{ __('messaging.notices.no_contact') }} Afspraken over prijs, betaling of ophalen tellen alleen in de bestelling.</p>
                </form>
            @endif
        </section>

        <aside class="d-stack-tight">
            <section class="d-card" aria-labelledby="kavel">
                <h2 id="kavel" class="d-h3">Alles over deze kavel</h2>
                <ul class="d-dossier">
                    @forelse ($dossier as $activity)
                        <li>
                            <span class="d-dossier-icon" aria-hidden="true"><x-v4.icon :name="$dossierIcons[$activity->source ?? ''] ?? 'sparkles'" :size="14" stroke="2.2" /></span>
                            <span>
                                <strong>{{ $activity->label() }}</strong>
                                <span>{{ $activity->occurred_at?->timezone($displayTimezone)->isoFormat('D MMM, HH:mm') }} · {{ __('dossier.sources.'.$activity->source) }}</span>
                            </span>
                        </li>
                    @empty
                        <li>
                            <span class="d-dossier-icon" aria-hidden="true"><x-v4.icon name="gavel" :size="14" stroke="2.2" /></span>
                            <span><strong>{{ $lot->status->label() }}</strong><span>{{ $lot->auction ? 'Stand '.Money::format($lot->currentPriceCents()) : 'Nog geen veiling' }}</span></span>
                        </li>
                    @endforelse
                    @if ($conversation->order)
                        <li>
                            <span class="d-dossier-icon" aria-hidden="true"><x-v4.icon name="shopping-bag" :size="14" stroke="2.2" /></span>
                            <span><strong>Bestelling {{ $conversation->order->order_number }}</strong><span>{{ $conversation->order->status->label() }}</span></span>
                        </li>
                    @endif
                </ul>
                @if ($lot->status->isPublic())
                    <a href="{{ route('lots.show', $lot) }}" class="d-btn d-btn-secondary d-btn-wide d-btn-sq" style="margin-top:12px">Naar de kavel</a>
                @endif
                @if ($conversation->order)
                    <a href="{{ route('orders.show', $conversation->order) }}" class="d-btn d-btn-secondary d-btn-wide d-btn-sq" style="margin-top:8px">Naar de bestelling</a>
                @endif
            </section>

            <section class="d-card" aria-labelledby="veilig">
                <h2 id="veilig" class="d-h3">Veilig handelen</h2>
                <p class="d-sub">Betaling, prijs en ophaalafspraken lopen via de bestelling. Zo blijft het geld beschermd tot de ontvangst.</p>
                @unless ($conversation->isBlocked())
                    <div class="d-chips" style="gap:6px;margin-top:10px">
                        <form method="POST" action="{{ route('messages.report', $conversation) }}" x-data="{ open: false }">
                            @csrf
                            <button type="button" class="d-chip d-chip-sm" @click="open = ! open">Melden</button>
                            <div x-show="open" x-cloak style="margin-top:8px">
                                <input type="text" name="note" maxlength="500" placeholder="Wat is er aan de hand?" class="d-input" style="margin:0">
                                <button type="submit" class="d-btn d-btn-dark d-btn-sm d-btn-sq" style="margin-top:8px">Melding sturen</button>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('messages.block', $conversation) }}" onsubmit="return confirm('Gesprek sluiten? Je krijgt geen berichten meer van deze partij.')">
                            @csrf
                            <button type="submit" class="d-chip d-chip-sm" style="color:#a93226">Blokkeren</button>
                        </form>
                    </div>
                @endunless
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
