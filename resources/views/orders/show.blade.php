@use('App\Enums\DisputeStatus')
@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $lot = $order->lot;
    $awaitingPayment = $order->status === OrderStatus::AwaitingPayment;
    $invoices = $order->invoices->filter(fn ($invoice) => $isBuyer ? in_array($invoice->kind, ['lot', 'admin_fee', 'credit'], true) : in_array($invoice->kind, ['lot', 'fee', 'credit'], true));

    // De vier stappen van een bestelling, met de stand van deze.
    $rank = match ($order->status) {
        OrderStatus::AwaitingPayment => 1,
        OrderStatus::Paid => 2,
        OrderStatus::ReadyForPickup, OrderStatus::Shipped => 2,
        OrderStatus::Delivered, OrderStatus::Disputed => 3,
        OrderStatus::Completed => 4,
        OrderStatus::Cancelled => 0,
    };
    $steps = [
        [$isBuyer ? 'Kavel gewonnen' : 'Kavel verkocht', $order->created_at->timezone($displayTimezone)->isoFormat('D MMM, HH:mm')],
        ['Betalen', $order->paid_at ? 'Betaald op '.$order->paid_at->timezone($displayTimezone)->isoFormat('D MMM, HH:mm') : ($order->payment_deadline_at ? 'Uiterlijk '.$order->payment_deadline_at->timezone($displayTimezone)->isoFormat('D MMM, HH:mm') : 'Na de gunning')],
        ['Ophalen of transport', $order->delivered_at ? 'Geleverd op '.$order->delivered_at->timezone($displayTimezone)->isoFormat('D MMM') : ($order->shipped_at ? 'Onderweg of klaar om op te halen' : 'Na je betaling')],
        ['Afgerond', $order->completed_at ? $order->completed_at->timezone($displayTimezone)->isoFormat('D MMM') : 'Na ontvangst en controle'],
    ];
@endphp

<x-layouts.dashboard :title="'Bestelling '.$order->order_number" :heading="$awaitingPayment && $isBuyer ? 'Betaalverzoek' : $lot->title" eyebrow="Bestelling"
    :intro="$awaitingPayment && $isBuyer ? 'Je hebt deze kavel gewonnen. Rond je betaling af om de aankoop te bevestigen.' : __('orders.timeline.'.$order->status->value)">
    <x-slot:crumbs>
        <a href="{{ route('orders.index') }}">Bestellingen</a>
        <span aria-hidden="true">/</span>
        <span>{{ $order->order_number }}</span>
    </x-slot:crumbs>

    <div class="d-cols">
        <div class="d-col-main" style="gap:18px">

            {{-- Het bedrag en de stand, bovenaan ----------------------------- --}}
            @if ($awaitingPayment && $isBuyer)
                <div class="d-paycard">
                    <span class="icon" aria-hidden="true"><x-v4.icon name="banknote" :size="24" stroke="2" /></span>
                    <div class="text">
                        <span>Te betalen bedrag</span>
                        <b>{{ Money::format($order->total_cents) }}</b>
                        <span>Bestelling {{ $order->order_number }}</span>
                    </div>
                    @if ($order->payment_deadline_at !== null)
                        <div class="due">Betaal voor<b><time datetime="{{ $order->payment_deadline_at->toIso8601String() }}">{{ $order->payment_deadline_at->timezone($displayTimezone)->isoFormat('D MMM YYYY') }}</time></b></div>
                    @endif
                </div>
            @else
                <section class="d-card" aria-label="Status">
                    <div class="d-card-head">
                        <div>
                            <div class="d-label">Status</div>
                            <div class="d-big" style="font-size:22px">{{ $order->status->label() }}</div>
                            <p class="d-sub">{{ __('orders.timeline.'.$order->status->value) }}</p>
                            @if ($order->status === OrderStatus::Delivered && $order->dispute_window_ends_at !== null)
                                <p class="d-sub">Probleem melden kan tot <time class="tabular" style="font-weight:700;color:#0e1526" datetime="{{ $order->dispute_window_ends_at->toIso8601String() }}">{{ $order->dispute_window_ends_at->timezone($displayTimezone)->isoFormat('D MMMM YYYY, HH:mm') }}</time>.</p>
                            @endif
                        </div>
                        <span class="d-tag {{ match ($order->status) { OrderStatus::Disputed => 'tone-live', OrderStatus::Completed, OrderStatus::Delivered => 'tone-ok', OrderStatus::Cancelled => 'tone-grey', default => 'tone-atlas' } }}">{{ $order->status->label() }}</span>
                    </div>
                </section>
            @endif

            {{-- Besteloverzicht: de kavel en het bedrag ---------------------- --}}
            <section aria-label="Besteloverzicht">
                <h2 class="d-section-title">Besteloverzicht</h2>
                <article class="d-listrow">
                    @if ($lot->media->first())
                        <img class="d-listrow-thumb" src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                    @else
                        <span class="d-listrow-thumb" aria-hidden="true"></span>
                    @endif
                    <div class="d-listrow-body">
                        <strong>@if ($lot->status->isPublic())<a href="{{ route('lots.show', $lot) }}">{{ $lot->title }}</a>@else{{ $lot->title }}@endif</strong>
                        <span class="d-listrow-meta">Kavel #{{ $lot->public_id }}@if ($lot->category) · {{ $lot->category->name }}@endif</span>
                    </div>
                    <div class="d-listrow-right">
                        <b>{{ Money::format($order->hammer_cents) }}</b>
                        <span>{{ $isBuyer ? 'Jouw winnende bod' : 'Hamerprijs' }}</span>
                    </div>
                </article>
                <div class="d-total" style="margin-top:10px">
                    <strong>{{ $isBuyer ? 'Totaalbedrag' : 'Totaal voor de koper' }}</strong>
                    <div class="amount">
                        <b>{{ Money::format($order->total_cents) }}</b>
                        <span>Geen opgeld. Btw en kosten staan per regel op de factuur.</span>
                    </div>
                </div>
                @if ($invoices->isNotEmpty())
                    <a href="{{ route('invoices.show', $invoices->first()) }}" class="d-link" style="display:inline-flex;align-items:center;min-height:44px">Bekijk de factuur</a>
                @endif
            </section>

            {{-- De handelingen die bij deze status en deze rol horen. --------- --}}
            @if ($isBuyer && $awaitingPayment)
                <section aria-label="Betaalmethode">
                    <h2 class="d-section-title">Betaalmethode</h2>
                    <form method="POST" action="{{ route('orders.pay', $order) }}" class="d-stack-tight">
                        @csrf
                        <div class="d-options">
                            <label class="d-option">
                                <input type="radio" name="method" value="ideal" checked>
                                <span><strong>iDEAL</strong><span>Veilig en direct betalen</span></span>
                            </label>
                            <label class="d-option">
                                <input type="radio" name="method" value="transfer">
                                <span><strong>Bankoverschrijving</strong><span>Betalen via je eigen bank</span></span>
                            </label>
                        </div>
                        <button type="submit" class="d-btn d-btn-wide d-btn-sq" style="min-height:48px">Nu betalen</button>
                        <p class="d-hint" style="margin:0">Betalen kan met iDEAL of via een bankoverschrijving; de betaling wordt aan deze bestelling gekoppeld.</p>
                    </form>
                </section>
            @endif

            @if (! $isBuyer && $order->status === OrderStatus::Paid)
                <section class="d-card" aria-label="Levering regelen">
                    <h2 class="d-h3">Regel de levering</h2>
                    <p class="d-sub">Zet hem klaar om op te halen of meld hem verzonden. Na zeven dagen stilte geldt hij als geleverd.</p>
                    <form method="POST" action="{{ route('orders.ship', $order) }}" class="d-chips" style="margin-top:12px">
                        @csrf
                        <button type="submit" name="method" value="pickup" class="d-btn d-btn-secondary d-btn-sq">Klaar om op te halen</button>
                        <button type="submit" name="method" value="shipped" class="d-btn d-btn-sq">Verzonden</button>
                    </form>
                </section>
            @endif

            @if ($isBuyer && in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::Shipped], true))
                <section class="d-card" aria-label="Levering bevestigen">
                    <h2 class="d-h3">Alles ontvangen?</h2>
                    <p class="d-sub">Bevestig de levering zodra alles binnen en gecontroleerd is. Daarna loopt de termijn om een probleem te melden.</p>
                    <form method="POST" action="{{ route('orders.deliver', $order) }}" style="margin-top:12px">
                        @csrf
                        <button type="submit" class="d-btn d-btn-sq">Levering bevestigen</button>
                    </form>
                </section>
            @endif

            {{-- Tijdlijn: wat er gebeurd is, op volgorde. --------------- --}}
            <x-orders.timeline :entries="$timeline" />

            {{-- Dossier (briefing nieuwe functies): alles rond deze kavel en order, alleen wat deze lezer mag zien. --}}
            <section class="d-card" aria-labelledby="dossier">
                <h2 id="dossier" class="d-h3">Dossier</h2>
                <p class="d-sub">Berichten, mail, documenten en geld rond deze kavel, op volgorde.</p>
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
                <section class="d-card" aria-label="Probleem melden" x-data="{ open: false }">
                    <button type="button" class="d-link" @click="open = ! open">Is er iets mis met deze bestelling?</button>

                    <form method="POST" action="{{ route('orders.dispute', $order) }}" x-show="open" x-cloak class="d-stack-tight" style="margin-top:12px">
                        @csrf
                        <label class="d-field" style="max-width:320px">Wat is er aan de hand?
                            <select name="reason" required class="d-select" style="width:100%">
                                @foreach (__('orders.dispute_reasons') as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="d-field">Omschrijf het probleem
                            <textarea name="description" rows="4" required maxlength="4000" class="d-textarea" style="margin-top:6px"></textarea>
                        </label>
                        <p class="d-hint" style="margin:0">Zodra je meldt, staat de uitbetaling aan de verkoper stil tot het is opgelost.</p>
                        <button type="submit" class="d-btn d-btn-sq" style="align-self:flex-start">Probleem melden</button>
                    </form>
                </section>
            @endif

            @if ($openDispute !== null)
                <section class="d-card" style="border-color:rgba(192,57,43,.3);background:#fdf5f4" aria-label="Geschil">
                    <h2 class="d-h3">Lopend geschil</h2>
                    <p class="d-prose" style="margin-top:4px">
                        {{ __('orders.dispute_reasons.'.$openDispute->reason) ?? $openDispute->reason }}
                        - {{ $openDispute->status->label() }}
                    </p>
                    @if ($openDispute->description)
                        <p class="d-sub">{{ $openDispute->description }}</p>
                    @endif

                    @if ($openDispute->seller_response !== null)
                        <p class="d-label" style="margin-top:12px">Reactie van de verkoper</p>
                        <p class="d-prose" style="margin-top:4px">{{ $openDispute->seller_response }}</p>
                    @endif

                    @if (! $isBuyer && $openDispute->status === DisputeStatus::Reported)
                        {{-- De 72 uur uit de zekerheidsbelofte: hier reageert de verkoper. --}}
                        @if (session('status'))
                            <p class="d-soft is-ok" style="margin-top:12px">{{ session('status') }}</p>
                        @endif
                        <form method="POST" action="{{ route('orders.dispute.respond', $order) }}" class="d-stack-tight" style="margin-top:12px">
                            @csrf
                            <label class="d-field">Jouw reactie
                                <span class="d-hint">Reageer vóór {{ $openDispute->sla_due_at?->timezone($displayTimezone)->isoFormat('D MMMM, HH:mm') ?? '-' }}; daarna beoordeelt een medewerker met wat er ligt.</span>
                                <textarea name="response" rows="4" required maxlength="4000" class="d-textarea" style="margin-top:6px">{{ old('response') }}</textarea>
                            </label>
                            @error('response')
                                <p role="alert" class="d-hint is-warn">{{ $message }}</p>
                            @enderror
                            @error('dispute')
                                <p role="alert" class="d-hint is-warn">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="d-btn d-btn-sq" style="align-self:flex-start">Reactie versturen</button>
                        </form>
                    @endif

                    <p class="d-hint">De uitbetaling staat stil zolang dit loopt. Een medewerker beoordeelt de melding.</p>
                </section>
            @endif

            {{-- Review: alleen bij een afgeronde order (hfst. 13.6). --}}
            @if ($order->status === OrderStatus::Completed)
                @php $review = $order->review; @endphp

                <section id="beoordeling" class="d-card" aria-label="Beoordeling">
                    @if (session('status'))
                        <p class="d-soft is-ok" style="margin:0 0 12px">{{ session('status') }}</p>
                    @endif
                    @if ($review !== null)
                        <h2 class="d-h3">Beoordeling</h2>
                        @if ($review->moderation_status === \App\Models\Review::STATUS_HELD)
                            <p class="d-hint is-warn">Wordt bekeken door een medewerker voordat hij online komt.</p>
                        @elseif ($review->moderation_status === \App\Models\Review::STATUS_REMOVED)
                            <p class="d-hint">Niet gepubliceerd: {{ $review->moderation_reason?->label() }}.</p>
                        @endif
                        <p class="d-prose" style="margin-top:6px">
                            <span class="tabular" style="font-weight:800;color:#0e1526">{{ $review->rating }}★</span>
                            @if ($review->body) - "{{ $review->body }}" @endif
                        </p>
                        @if ($review->seller_reply)
                            <p class="d-prose" style="margin-top:8px;padding-left:12px;border-left:2px solid rgba(14,21,38,.16)">Reactie verkoper: "{{ $review->seller_reply }}"</p>
                        @endif
                    @elseif ($isBuyer)
                        <h2 class="d-h3">Hoe was het?</h2>
                        <form method="POST" action="{{ route('orders.review', $order) }}" class="d-stack-tight" style="margin-top:12px">
                            @csrf
                            <div class="d-chips" role="radiogroup" aria-label="Aantal sterren">
                                @foreach (range(1, 5) as $stars)
                                    <label class="d-chip" style="cursor:pointer">
                                        <input type="radio" name="rating" value="{{ $stars }}" required class="sr-only" @change="$el.closest('.d-chips').querySelectorAll('label').forEach(l => l.classList.toggle('is-on', l.querySelector('input').checked))">
                                        {{ $stars }}★
                                    </label>
                                @endforeach
                            </div>
                            <textarea name="body" rows="3" maxlength="2000" placeholder="Eerlijk is genoeg - ook als het niet vlekkeloos liep." class="d-textarea"></textarea>
                            <button type="submit" class="d-btn d-btn-sq" style="align-self:flex-start">Beoordeling plaatsen</button>
                        </form>
                        <p class="d-hint">Reviews blijven staan, ook kritische. Scheldwoorden, discriminatie, politiek en persoonsgegevens worden eerst door een medewerker bekeken.</p>
                    @else
                        <p class="d-sub">De koper kan deze bestelling nu beoordelen.</p>
                    @endif
                </section>
            @endif

            @if ($errors->any())
                <p role="alert" class="d-soft is-err" style="margin:0">{{ $errors->first() }}</p>
            @endif
        </div>

        {{-- Zijkolom: de status, wat er nu gebeurt en het geld ---------- --}}
        <aside class="d-col-aside" style="border:0;background:none;display:flex;flex-direction:column;gap:14px">
            <section class="d-card" aria-label="Status van je bestelling">
                <h2 class="d-h3">Status van je bestelling</h2>
                <ol class="d-steps">
                    @foreach ($steps as $index => [$label, $when])
                        @php $step = $index + 1; @endphp
                        <li class="{{ $step < $rank || $rank === 4 ? 'is-done' : ($step === $rank ? 'is-now' : '') }}">
                            <span class="dot" aria-hidden="true">@if ($step < $rank || $rank === 4)<x-v4.icon name="check" :size="12" stroke="3" />@endif</span>
                            <div><strong>{{ $label }}</strong><span>{{ $when }}</span></div>
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($isBuyer && $awaitingPayment)
                <div class="d-callout-info" style="flex-direction:column;gap:10px">
                    <strong style="display:flex;align-items:center;gap:10px;margin:0"><span class="mark" aria-hidden="true">i</span> Wat gebeurt er nu?</strong>
                    <ol style="margin:0;padding-left:20px;display:flex;flex-direction:column;gap:6px">
                        <li>Je betaling wordt automatisch aan je bestelling gekoppeld.</li>
                        <li>Na betaling plan je samen het ophaal- of bezorgmoment.</li>
                        <li>De verkoper krijgt het geld pas na de houdtermijn.</li>
                    </ol>
                </div>
            @endif

            <x-lot.cost-breakdown class="d-card" :costs="$costs" title="Kostenopbouw" />

            {{-- Facturen (hoofdbriefing p. 7): koper ziet kavel en administratiekosten, verkoper ziet kavel en succesfee. --}}
            @if ($invoices->isNotEmpty())
                <section class="d-card" aria-label="Facturen">
                    <h2 class="d-h3">Facturen</h2>
                    <div class="d-rows" style="margin-top:6px">
                        @foreach ($invoices as $invoice)
                            <div class="d-row"><span>{{ $invoice->kindLabel() }} <span class="d-muted">{{ $invoice->number }}</span></span><a href="{{ route('invoices.show', $invoice) }}" class="d-link">Bekijken</a></div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (! $isBuyer && $order->payout !== null)
                <section class="d-card" aria-label="Uitbetaling">
                    <h2 class="d-h3">Jouw uitbetaling</h2>
                    <div class="d-costs" style="margin-top:8px">
                        <div class="d-row"><span>Hamerprijs</span><strong>{{ Money::format($order->hammer_cents) }}</strong></div>
                        <div class="d-row"><span>Succesfee</span><strong>− {{ Money::format($order->payout->fee_cents) }}</strong></div>
                        <div class="d-row is-total"><span>Uitbetaling</span><strong>{{ Money::format($order->payout->amount_cents) }}</strong></div>
                    </div>
                    <p class="d-hint" style="margin-top:8px">
                        {{ $order->payout->status->label() }}@if ($order->payout->hold_until !== null && $order->payout->released_at === null)
                            - vrijgave na {{ $order->payout->hold_until->timezone($displayTimezone)->isoFormat('D MMMM, HH:mm') }}
                        @endif
                    </p>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
