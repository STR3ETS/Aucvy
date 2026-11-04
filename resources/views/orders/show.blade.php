@use('App\Enums\DisputeStatus')
@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

<x-layouts.dashboard :title="'Bestelling '.$order->order_number" :heading="$order->lot->title" eyebrow="Bestelling">
    <x-slot:crumbs>
        <a href="{{ route('orders.index') }}">Bestellingen</a>
        <span aria-hidden="true">/</span>
        <span>{{ $order->order_number }}</span>
    </x-slot:crumbs>
    <div class="mx-auto max-w-4xl">

        <div class="grid gap-8 lg:grid-cols-[1fr_300px] lg:items-start">
            <div class="space-y-5">

                {{-- Status en de eerstvolgende stap ------------------------- --}}
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Status">
                    <p class="text-sm font-extrabold text-aj-soft">Status</p>
                    <p class="mt-1 text-xl font-extrabold">{{ $order->status->label() }}</p>
                    <p class="mt-1 text-sm text-aj-muted">{{ __('orders.timeline.'.$order->status->value) }}</p>

                    @if ($order->status === OrderStatus::AwaitingPayment && $order->payment_deadline_at !== null)
                        <p class="mt-2 text-sm">
                            Betaaltermijn tot
                            <time class="tabular font-bold" datetime="{{ $order->payment_deadline_at->toIso8601String() }}">
                                {{ $order->payment_deadline_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM YYYY, HH:mm') }}
                            </time>
                        </p>
                    @endif

                    @if ($order->status === OrderStatus::Delivered && $order->dispute_window_ends_at !== null)
                        <p class="mt-2 text-sm">
                            Probleem melden kan tot
                            <time class="tabular font-bold" datetime="{{ $order->dispute_window_ends_at->toIso8601String() }}">
                                {{ $order->dispute_window_ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM YYYY, HH:mm') }}
                            </time>
                        </p>
                    @endif

                    {{-- De handelingen die bij deze status en deze rol horen. --}}
                    <div class="mt-4 flex flex-wrap gap-3">
                        @if ($isBuyer && $order->status === OrderStatus::AwaitingPayment)
                            <form method="POST" action="{{ route('orders.pay', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">Betalen</button>
                            </form>
                        @endif

                        @if (! $isBuyer && $order->status === OrderStatus::Paid)
                            <form method="POST" action="{{ route('orders.ship', $order) }}" class="flex flex-wrap gap-3">
                                @csrf
                                <button type="submit" name="method" value="pickup" class="btn btn-ghost">
                                    Klaar om op te halen
                                </button>
                                <button type="submit" name="method" value="shipped" class="btn btn-primary">
                                    Verzonden
                                </button>
                            </form>
                        @endif

                        @if ($isBuyer && in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::Shipped], true))
                            <form method="POST" action="{{ route('orders.deliver', $order) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">Levering bevestigen</button>
                            </form>
                        @endif
                    </div>
                </section>

                {{-- Tijdlijn: wat er gebeurd is, op volgorde. --------------- --}}
                <x-orders.timeline :entries="$timeline" />

                {{-- Dossier (briefing nieuwe functies): alles rond deze kavel en
                     order, alleen wat deze lezer mag zien. --}}
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="dossier">
                    <h2 id="dossier" class="text-sm font-extrabold">Dossier</h2>
                    <p class="mt-1 text-xs text-aj-muted">Berichten, mail, documenten en geld rond deze kavel, op volgorde.</p>
                    <x-dossier.timeline :activities="$dossier" :show-actor="false" class="mt-3" empty="Nog niets in het dossier." />
                </section>

                {{-- Berichten tussen koper, verkoper en support (briefing sectie 7). --}}
                <x-orders.messages :order="$order" :messages="$order->messages" :action="route('orders.messages.store', $order)" :viewer="auth()->user()" />

                {{-- Probleem melden ------------------------------------------ --}}
                @php
                    $canReport = $isBuyer && $openDispute === null && (
                        in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::Shipped], true)
                        || ($order->status === OrderStatus::Delivered && $order->isWithinDisputeWindow())
                    );
                @endphp

                @if ($canReport)
                    <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Probleem melden"
                             x-data="{ open: false }">
                        <button type="button" class="text-sm font-bold text-aj-blue-text underline" @click="open = ! open">
                            Is er iets mis met deze bestelling?
                        </button>

                        <form method="POST" action="{{ route('orders.dispute', $order) }}" x-show="open" x-cloak class="mt-4 space-y-3">
                            @csrf
                            <label class="block sm:max-w-xs">
                                <span class="text-sm font-bold">Wat is er aan de hand?</span>
                                <select name="reason" required
                                        class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                                        style="min-height:var(--cta-h)">
                                    @foreach (__('orders.dispute_reasons') as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="text-sm font-bold">Omschrijf het probleem</span>
                                <textarea name="description" rows="4" required maxlength="4000"
                                          class="mt-1 w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm"></textarea>
                            </label>
                            <p class="text-xs text-aj-muted">
                                Zodra je meldt, staat de uitbetaling aan de verkoper stil tot het is opgelost.
                            </p>
                            <button type="submit" class="btn btn-primary">Probleem melden</button>
                        </form>
                    </section>
                @endif

                @if ($openDispute !== null)
                    <section class="rounded-aj-s border border-aj-error/30 bg-aj-error/5 p-6" aria-label="Geschil">
                        <h2 class="text-sm font-extrabold">Lopend geschil</h2>
                        <p class="mt-1 text-sm">
                            {{ __('orders.dispute_reasons.'.$openDispute->reason) ?? $openDispute->reason }}
                            - {{ $openDispute->status->label() }}
                        </p>
                        @if ($openDispute->description)
                            <p class="mt-2 text-sm text-aj-muted">{{ $openDispute->description }}</p>
                        @endif

                        @if ($openDispute->seller_response !== null)
                            <p class="mt-3 text-xs font-extrabold text-aj-soft">Reactie van de verkoper</p>
                            <p class="mt-1 text-sm">{{ $openDispute->seller_response }}</p>
                        @endif

                        @if (! $isBuyer && $openDispute->status === DisputeStatus::Reported)
                            {{-- De 72 uur uit de zekerheidsbelofte: hier reageert de verkoper. --}}
                            @if (session('status'))
                                <p class="mt-3 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
                            @endif
                            <form method="POST" action="{{ route('orders.dispute.respond', $order) }}" class="mt-4 space-y-3">
                                @csrf
                                <label class="block">
                                    <span class="text-sm font-bold">Jouw reactie</span>
                                    <span class="block text-xs text-aj-muted">
                                        Reageer vóór {{ $openDispute->sla_due_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm') ?? '-' }}; daarna beoordeelt een medewerker met wat er ligt.
                                    </span>
                                    <textarea name="response" rows="4" required maxlength="4000"
                                              class="mt-1 w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm">{{ old('response') }}</textarea>
                                </label>
                                @error('response')
                                    <p role="alert" class="text-sm text-aj-error">{{ $message }}</p>
                                @enderror
                                @error('dispute')
                                    <p role="alert" class="text-sm text-aj-error">{{ $message }}</p>
                                @enderror
                                <button type="submit" class="btn btn-primary">Reactie versturen</button>
                            </form>
                        @endif

                        <p class="mt-2 text-xs text-aj-muted">
                            De uitbetaling staat stil zolang dit loopt. Een medewerker beoordeelt de melding.
                        </p>
                    </section>
                @endif

                {{-- Review: alleen bij een afgeronde order (hfst. 13.6). --}}
                @if ($order->status === OrderStatus::Completed)
                    @php $review = $order->review; @endphp

                    <section id="beoordeling" class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Beoordeling">
                        @if (session('status'))
                            <p class="mb-3 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
                        @endif
                        @if ($review !== null)
                            <h2 class="text-sm font-extrabold">Beoordeling</h2>
                            @if ($review->moderation_status === \App\Models\Review::STATUS_HELD)
                                <p class="mt-1 text-xs font-bold text-aj-warn-text">Wordt bekeken door een medewerker voordat hij online komt.</p>
                            @elseif ($review->moderation_status === \App\Models\Review::STATUS_REMOVED)
                                <p class="mt-1 text-xs font-bold text-aj-muted">Niet gepubliceerd: {{ $review->moderation_reason?->label() }}.</p>
                            @endif
                            <p class="mt-2 text-sm">
                                <span class="tabular font-extrabold">{{ $review->rating }}★</span>
                                @if ($review->body) - "{{ $review->body }}" @endif
                            </p>
                            @if ($review->seller_reply)
                                <p class="mt-2 border-l-2 border-aj-border-strong pl-3 text-sm text-aj-muted">
                                    Reactie verkoper: "{{ $review->seller_reply }}"
                                </p>
                            @endif
                        @elseif ($isBuyer)
                            <h2 class="text-sm font-extrabold">Hoe was het?</h2>
                            <form method="POST" action="{{ route('orders.review', $order) }}" class="mt-3 space-y-3">
                                @csrf
                                <div class="flex gap-2" role="radiogroup" aria-label="Aantal sterren">
                                    @foreach (range(1, 5) as $stars)
                                        <label class="btn btn-ghost" style="height:40px;font-size:13px">
                                            <input type="radio" name="rating" value="{{ $stars }}" required class="sr-only">
                                            {{ $stars }}★
                                        </label>
                                    @endforeach
                                </div>
                                <textarea name="body" rows="3" maxlength="2000"
                                          placeholder="Eerlijk is genoeg - ook als het niet vlekkeloos liep."
                                          class="w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm"></textarea>
                                <button type="submit" class="btn btn-primary">Beoordeling plaatsen</button>
                            </form>
                            <p class="mt-2 text-xs text-aj-muted">Reviews blijven staan, ook kritische. Scheldwoorden, discriminatie, politiek en persoonsgegevens worden eerst door een medewerker bekeken.</p>
                        @else
                            <p class="text-sm text-aj-muted">De koper kan deze bestelling nu beoordelen.</p>
                        @endif
                    </section>
                @endif

                @if ($errors->any())
                    <p role="alert" class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                        {{ $errors->first() }}
                    </p>
                @endif
            </div>

            {{-- Zijkolom: het geld ------------------------------------------ --}}
            <aside class="space-y-4">
                <x-lot.cost-breakdown :costs="$costs" title="Kostenopbouw" />

                {{-- Facturen (hoofdbriefing p. 7): koper ziet kavel en administratiekosten, verkoper ziet kavel en succesfee. --}}
                @php $invoices = $order->invoices->filter(fn ($invoice) => $isBuyer ? in_array($invoice->kind, ['lot', 'admin_fee', 'credit'], true) : in_array($invoice->kind, ['lot', 'fee', 'credit'], true)); @endphp
                @if ($invoices->isNotEmpty())
                    <div class="rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4" aria-label="Facturen">
                        <p class="text-sm font-extrabold">Facturen</p>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($invoices as $invoice)
                                <li class="flex items-center justify-between gap-2"><span>{{ $invoice->kindLabel() }} <span class="text-xs text-aj-muted">{{ $invoice->number }}</span></span><a href="{{ route('invoices.show', $invoice) }}" class="font-semibold underline">Bekijken</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (! $isBuyer && $order->payout !== null)
                    <div class="rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4" aria-label="Uitbetaling">
                        <p class="text-sm font-extrabold">Jouw uitbetaling</p>
                        <dl class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-aj-muted">Hamerprijs</dt>
                                <dd class="tabular font-semibold">{{ Money::format($order->hammer_cents) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-aj-muted">Succesfee</dt>
                                <dd class="tabular font-semibold">− {{ Money::format($order->payout->fee_cents) }}</dd>
                            </div>
                            <div class="flex justify-between gap-4 border-t border-aj-border pt-2">
                                <dt class="font-extrabold">Uitbetaling</dt>
                                <dd class="tabular font-extrabold">{{ Money::format($order->payout->amount_cents) }}</dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-xs text-aj-muted">
                            {{ $order->payout->status->label() }}@if ($order->payout->hold_until !== null && $order->payout->released_at === null)
                                - vrijgave na {{ $order->payout->hold_until->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm') }}
                            @endif
                        </p>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.dashboard>
