@use('App\Enums\DisputeStatus')
@use('App\Enums\OrderStatus')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $lot = $order->lot;
    $awaitingPayment = $order->status === OrderStatus::AwaitingPayment;
    $payNow = $awaitingPayment && $isBuyer;
    $invoices = $order->invoices->filter(fn ($invoice) => $isBuyer ? in_array($invoice->kind, ['lot', 'admin_fee', 'credit'], true) : in_array($invoice->kind, ['lot', 'fee', 'credit'], true))->values();
    $at = fn ($moment): string => rtrim($moment->timezone($displayTimezone)->isoFormat('D MMM'), '.').', '.$moment->timezone($displayTimezone)->format('H:i');
    $day = fn ($moment): string => rtrim($moment->timezone($displayTimezone)->isoFormat('D MMM'), '.');

    // De vier stappen van een bestelling: wat klaar is, en welke stap nu aan de beurt is.
    $paid = $order->paid_at !== null || ! in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::Cancelled], true);
    $delivered = $order->delivered_at !== null || in_array($order->status, [OrderStatus::Delivered, OrderStatus::Completed], true);
    $steps = [
        [$isBuyer ? 'Kavel gewonnen' : 'Kavel verkocht', $at($order->created_at), true],
        ['Betalen', $order->paid_at ? 'Betaald op '.$at($order->paid_at) : ($order->payment_deadline_at ? 'Uiterlijk '.$at($order->payment_deadline_at) : 'Na de gunning'), $paid],
        ['Ophalen of transport', $order->delivered_at ? 'Geleverd op '.$day($order->delivered_at) : ($order->shipped_at ? 'Onderweg of klaar om op te halen' : ($paid ? ($isBuyer ? 'De verkoper maakt de kavel klaar' : 'Nog te regelen') : 'Na de betaling')), $delivered],
        ['Afgerond', $order->completed_at ? $day($order->completed_at) : 'Na ontvangst en controle', $order->status === OrderStatus::Completed],
    ];
    $nowIndex = $order->status === OrderStatus::Cancelled ? false : collect($steps)->search(fn (array $step): bool => ! $step[2]);

    $statusTone = match ($order->status) {
        OrderStatus::AwaitingPayment => 'tone-warn',
        OrderStatus::Disputed => 'tone-live',
        OrderStatus::Completed, OrderStatus::Delivered => 'tone-ok',
        OrderStatus::Cancelled => 'tone-grey',
        default => 'tone-atlas',
    };
    $canReport = $isBuyer && $openDispute === null && (
        in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::Shipped], true)
        || ($order->status === OrderStatus::Delivered && $order->isWithinDisputeWindow())
    );
    $tabs = [
        'berichten' => ['message-circle', 'Berichten', $order->messages->count()],
        'tijdlijn' => ['clock', 'Tijdlijn', count($timeline)],
        'dossier' => ['file-text', 'Dossier', $dossier->count()],
    ];
@endphp

{{--
    Eén bestelling. Bovenaan de bestelling zelf met het bedrag, de acties als
    pictogram en de vier stappen op een rij. Daaronder hoogstens één kaart
    met wat jij nu moet doen (betalen, de levering regelen of bevestigen,
    reageren op een geschil). Berichten, tijdlijn en dossier staan als tabs in
    één kaart; het geld staat rechts.
--}}
<x-layouts.dashboard :title="'Bestelling '.$order->order_number" :heading="$payNow ? 'Betaalverzoek' : $lot->title" eyebrow="Bestelling"
    :intro="$payNow ? 'Je hebt deze kavel gewonnen. Rond je betaling af om de aankoop te bevestigen.' : __('orders.timeline.'.$order->status->value)">
    <x-slot:crumbs>
        <a href="{{ route('orders.index') }}">Bestellingen</a>
        <span aria-hidden="true">/</span>
        <span>{{ $order->order_number }}</span>
    </x-slot:crumbs>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p role="alert" class="d-soft is-err" style="margin:0 0 14px">{{ $errors->first() }}</p>
    @endif

    <div class="d-cols">
        <div class="d-col-main">

            {{-- De bestelling: wat het is, het bedrag, de acties en de stand. --}}
            <section class="d-card d-orderhead" aria-label="Bestelling {{ $order->order_number }}" x-data="{ report: false }">
                <div class="d-orderhead-top">
                    @if ($lot->status->isPublic())
                        <a class="thumb" href="{{ route('lots.show', $lot) }}" tabindex="-1" aria-hidden="true">
                    @else
                        <span class="thumb" aria-hidden="true">
                    @endif
                        @if ($lot->media->first())
                            <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                        @else
                            <x-v4.icon name="image" :size="22" stroke="1.6" />
                        @endif
                    @if ($lot->status->isPublic()) </a> @else </span> @endif

                    <div class="text">
                        <strong class="d-orderhead-title">{{ $payNow ? $lot->title : 'Bestelling '.$order->order_number }}</strong>
                        <span class="d-lotrow-tags">
                            <span class="d-tag {{ $statusTone }}">{{ $order->status->label() }}</span>
                            <span class="d-bid-meta">@if ($payNow)Bestelling {{ $order->order_number }} · @endif Kavel #{{ $lot->public_id }}@if ($lot->category) · {{ $lot->category->name }}@endif</span>
                        </span>
                    </div>

                    <div class="d-orderhead-amount">
                        <b class="tabular">{{ Money::format($isBuyer ? $order->total_cents : $order->hammer_cents) }}</b>
                        @if ($payNow && $order->payment_deadline_at !== null)
                            <span class="is-due">Betalen vóór <time datetime="{{ $order->payment_deadline_at->toIso8601String() }}">{{ $at($order->payment_deadline_at) }}</time></span>
                        @else
                            <span>{{ $isBuyer ? 'Totaal, met kosten en btw' : 'Hamerprijs' }}</span>
                        @endif
                    </div>

                    {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                    <div class="d-lotrow-actions">
                        @if ($lot->status->isPublic())
                            <a href="{{ route('lots.show', $lot) }}" class="d-iconbtn" data-tip="Bekijk kavel" aria-label="Kavel {{ $lot->title }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                        @endif
                        @if ($invoices->isNotEmpty())
                            <a href="{{ route('invoices.show', $invoices->first()) }}" class="d-iconbtn" data-tip="Bekijk de factuur" aria-label="Factuur {{ $invoices->first()->number }} bekijken"><x-v4.icon name="file-text" :size="17" stroke="2" /></a>
                        @endif
                        @if ($canReport)
                            <button type="button" class="d-iconbtn" :class="report && 'is-on'" data-tip="Probleem melden" aria-label="Een probleem met deze bestelling melden" :aria-expanded="report" @click="report = ! report"><x-v4.icon name="flag" :size="17" stroke="2" /></button>
                        @endif
                    </div>
                </div>

                <ol class="d-hsteps" aria-label="Status van je bestelling">
                    @foreach ($steps as $index => [$label, $when, $done])
                        <li class="{{ $done ? 'is-done' : ($index === $nowIndex ? 'is-now' : '') }}">
                            <span class="dot" aria-hidden="true">@if ($done)<x-v4.icon name="check" :size="13" stroke="3" />@endif</span>
                            <strong>{{ $label }}</strong>
                            <span>{{ $when }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($order->status === OrderStatus::Delivered && $order->dispute_window_ends_at !== null)
                    <p class="d-orderhead-note">Een probleem melden kan tot <time class="tabular" datetime="{{ $order->dispute_window_ends_at->toIso8601String() }}">{{ $order->dispute_window_ends_at->timezone($displayTimezone)->isoFormat('D MMMM YYYY, HH:mm') }}</time>.</p>
                @endif

                {{-- Probleem melden: klapt uit vanaf de vlag. --}}
                @if ($canReport)
                    <form method="POST" action="{{ route('orders.dispute', $order) }}" x-show="report" x-cloak class="d-orderhead-report">
                        @csrf
                        <strong>Is er iets mis met deze bestelling?</strong>
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
                        <button type="submit" class="d-btn d-btn-dark d-btn-sq" style="justify-self:start">Probleem melden</button>
                    </form>
                @endif
            </section>

            {{-- Wat jij nu moet doen: hoogstens één kaart, met de ene blauwe knop. --}}
            @if ($payNow)
                <section class="d-card" aria-labelledby="betalen">
                    <div class="d-titleline">
                        <h2 id="betalen" class="d-h2">Betaalmethode</h2>
                        <x-dashboard.help text="Je betaling wordt automatisch aan je bestelling gekoppeld. Na betaling plan je samen het ophaal- of bezorgmoment. De verkoper krijgt het geld pas na de houdtermijn." />
                    </div>
                    <form method="POST" action="{{ route('orders.pay', $order) }}" class="d-stack-tight" style="margin-top:14px">
                        @csrf
                        <div class="d-options is-two">
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
                    </form>
                </section>
            @endif

            @if (! $isBuyer && $order->status === OrderStatus::Paid)
                <section class="d-card" aria-labelledby="levering">
                    <div class="d-titleline">
                        <h2 id="levering" class="d-h2">Regel de levering</h2>
                        <x-dashboard.help text="Zet de kavel klaar om op te halen of meld hem verzonden. Na zeven dagen stilte geldt hij als geleverd." />
                    </div>
                    <p class="d-lead" style="margin:8px 0 0">De koper heeft betaald. Laat weten hoe de kavel naar de koper gaat.</p>
                    <form method="POST" action="{{ route('orders.ship', $order) }}" class="d-chips" style="margin-top:14px">
                        @csrf
                        <button type="submit" name="method" value="shipped" class="d-btn d-btn-sq">Verzonden</button>
                        <button type="submit" name="method" value="pickup" class="d-btn d-btn-secondary d-btn-sq">Klaar om op te halen</button>
                    </form>
                </section>
            @endif

            @if ($isBuyer && in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::Shipped], true))
                <section class="d-card" aria-labelledby="ontvangen">
                    <div class="d-titleline">
                        <h2 id="ontvangen" class="d-h2">Alles ontvangen?</h2>
                        <x-dashboard.help text="Na je bevestiging loopt de termijn om een probleem te melden. Is er nu al iets mis, gebruik dan de vlag bovenaan." />
                    </div>
                    <p class="d-lead" style="margin:8px 0 0">Bevestig de levering zodra alles binnen en gecontroleerd is.</p>
                    <form method="POST" action="{{ route('orders.deliver', $order) }}" style="margin-top:14px">
                        @csrf
                        <button type="submit" class="d-btn d-btn-sq">Levering bevestigen</button>
                    </form>
                </section>
            @endif

            @if ($openDispute !== null)
                <section class="d-card d-orderdispute" aria-labelledby="geschil">
                    <div class="d-titleline">
                        <h2 id="geschil" class="d-h2">Lopend geschil</h2>
                        <x-dashboard.help text="De uitbetaling staat stil zolang dit loopt. Een medewerker beoordeelt de melding." />
                    </div>
                    <p class="d-lotrow-tags" style="margin:10px 0 0">
                        <span class="d-tag tone-warn">{{ __('orders.dispute_reasons.'.$openDispute->reason) ?? $openDispute->reason }}</span>
                        <span class="d-tag tone-atlas">{{ $openDispute->status->label() }}</span>
                    </p>

                    <div class="d-disputetalk" style="margin-top:14px">
                        @if ($openDispute->description)
                            <div class="d-dispute-msg">
                                <div><strong>{{ $isBuyer ? 'Jij' : 'Koper' }}</strong><span class="d-muted">{{ $at($openDispute->created_at) }}</span></div>
                                <p>{{ $openDispute->description }}</p>
                            </div>
                        @endif
                        @if ($openDispute->seller_response !== null)
                            <div class="d-dispute-msg">
                                <div><strong>{{ $isBuyer ? 'Reactie van de verkoper' : 'Jij' }}</strong>@if ($openDispute->seller_responded_at)<span class="d-muted">{{ $at($openDispute->seller_responded_at) }}</span>@endif</div>
                                <p>{{ $openDispute->seller_response }}</p>
                            </div>
                        @endif
                    </div>

                    @if (! $isBuyer && $openDispute->status === DisputeStatus::Reported)
                        {{-- De 72 uur uit de zekerheidsbelofte: hier reageert de verkoper. --}}
                        <form method="POST" action="{{ route('orders.dispute.respond', $order) }}" class="d-stack-tight" style="margin-top:14px">
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
                </section>
            @endif

            {{-- Berichten, tijdlijn en dossier: drie tabs in één kaart. --}}
            <section class="d-card" aria-label="Berichten, tijdlijn en dossier" x-data="{ tab: 'berichten' }">
                <div class="d-tabs" role="tablist" aria-label="Wat wil je zien" style="margin-top:0">
                    @foreach ($tabs as $key => [$icon, $label, $count])
                        <button type="button" role="tab" id="order-tab-{{ $key }}" aria-controls="order-{{ $key }}"
                                :aria-selected="tab === '{{ $key }}'" :class="tab === '{{ $key }}' && 'is-on'" class="{{ $loop->first ? 'is-on' : '' }}"
                                @click="tab = '{{ $key }}'"><x-v4.icon :name="$icon" :size="16" stroke="2.1" /> {{ $label }} <span class="d-count tabular">{{ $count }}</span></button>
                    @endforeach
                </div>

                {{-- Berichten tussen koper, verkoper en support (briefing sectie 7). --}}
                <div id="order-berichten" role="tabpanel" aria-labelledby="order-tab-berichten" x-show="tab === 'berichten'">
                    <x-orders.messages bare :order="$order" :messages="$order->messages" :action="route('orders.messages.store', $order)" :viewer="auth()->user()" />
                </div>

                {{-- Tijdlijn: wat er gebeurd is, op volgorde. --}}
                <div id="order-tijdlijn" role="tabpanel" aria-labelledby="order-tab-tijdlijn" x-show="tab === 'tijdlijn'" x-cloak>
                    <x-orders.timeline bare :entries="$timeline" />
                </div>

                {{-- Dossier (briefing nieuwe functies): alles rond deze kavel en order, alleen wat deze lezer mag zien. --}}
                <div id="order-dossier" role="tabpanel" aria-labelledby="order-tab-dossier" x-show="tab === 'dossier'" x-cloak>
                    <p class="d-lead">Berichten, mail, documenten en geld rond deze kavel, op volgorde.</p>
                    <x-dossier.timeline :activities="$dossier" :show-actor="false" empty="Nog niets in het dossier." />
                </div>
            </section>

            {{-- Review: alleen bij een afgeronde order (hfst. 13.6). --}}
            @if ($order->status === OrderStatus::Completed)
                @php $review = $order->review; @endphp

                <section id="beoordeling" class="d-card" aria-label="Beoordeling">
                    @if ($review !== null)
                        <h2 class="d-h2">Beoordeling</h2>
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
                        <div class="d-titleline">
                            <h2 class="d-h2">Hoe was het?</h2>
                            <x-dashboard.help text="Reviews blijven staan, ook kritische. Scheldwoorden, discriminatie, politiek en persoonsgegevens worden eerst door een medewerker bekeken." />
                        </div>
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
                    @else
                        <p class="d-sub" style="margin:0">De koper kan deze bestelling nu beoordelen.</p>
                    @endif
                </section>
            @endif
        </div>

        {{-- Zijkolom: het geld. --}}
        <aside class="d-col-aside is-stack">
            <x-lot.cost-breakdown class="d-card" :costs="$costs" title="Kostenopbouw" :total-label="$isBuyer ? 'Jij betaalt' : 'De koper betaalt'" />

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

            {{-- Facturen (hoofdbriefing p. 7): koper ziet kavel en administratiekosten, verkoper ziet kavel en succesfee. --}}
            @if ($invoices->isNotEmpty())
                <section class="d-card" aria-label="Facturen">
                    <h2 class="d-h3">Facturen</h2>
                    <div class="d-invoicelist">
                        @foreach ($invoices as $invoice)
                            <div>
                                <span><strong>{{ $invoice->kindLabel() }}</strong><span class="d-muted tabular">{{ $invoice->number }}</span></span>
                                <a href="{{ route('invoices.show', $invoice) }}" class="d-iconbtn" data-tip="Bekijk factuur" aria-label="Factuur {{ $invoice->number }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
