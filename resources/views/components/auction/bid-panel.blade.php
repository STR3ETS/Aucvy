@props(['auction'])

{{--
    De acht biedstates uit hoofdstuk 13.3, elk met een eigen tekst:

      kan bieden · hoogste bieder · overboden · slotfase · niet geverifieerd
      · eigen kavel · gesloten · gewonnen · verloren

    Geen generieke fout, nergens. "Je bent nog niet geverifieerd" heeft een
    knop naar de verificatiestap; "je bod is te laag" heeft een bedrag.

    De kaart volgt het ontwerp van november: wit, met de staat als pil, de
    stand groot, de interesse eronder, dan het bod en het maximum, de
    sleepknop en de kostenopbouw bij dit bod.
--}}
@php
    $lot = $auction->lot;
    $lastChance = ! $auction->isClosed() && $auction->ends_at->isBefore(now()->addDay());
@endphp
<div class="d-bidcard" id="bied" style="scroll-margin-top:12px" x-cloak>

    {{-- Statusbalk: wat voor moment is dit, en hoelang nog ----------------- --}}
    <div class="d-bidcard-top">
        <template x-if="status === 'live'">
            <span class="d-tag {{ $lastChance ? 'tone-warn' : 'tone-live' }}">{{ $lastChance ? 'Laatste kans' : 'Live' }}</span>
        </template>
        <template x-if="status === 'closing'">
            <span class="d-tag tone-warn">Slotfase</span>
        </template>
        <template x-if="status === 'closed'">
            <span class="d-tag tone-grey">Gesloten</span>
        </template>
        <template x-if="status === 'paused'">
            <span class="d-tag tone-grey">Gepauzeerd</span>
        </template>

        <span class="d-bidcard-time">
            <x-auction.countdown />
        </span>
    </div>

    {{-- Stand -------------------------------------------------------------- --}}
    <div>
        <div class="d-muted">
            Huidig bod · <span x-text="state.bidder_count"></span> bieders<template x-if="state.extension_count > 0"><span> · <span x-text="state.extension_count"></span>× verlengd</span></template><template x-if="(state.watching_now ?? 0) > 0"><span> · <span x-text="state.watching_now"></span> kijken nu</span></template>
        </div>
        <div class="d-bidcard-price" x-text="formatCents(state.price_cents)"></div>
        @if ($lot !== null && $lot->mayShowValueComparison())
            <div class="d-bidcard-value" :class="state.price_cents > {{ $lot->value_max_cents }} && 'is-above'"
                 x-text="state.price_cents < {{ $lot->value_min_cents }}
                    ? Math.round(100 - state.price_cents / {{ $lot->value_min_cents }} * 100) + '% onder waarde-indicatie'
                    : (state.price_cents > {{ $lot->value_max_cents }} ? 'Boven de waarde-indicatie' : 'Binnen de waarde-indicatie')"></div>
        @endif
    </div>

    {{-- Jouw positie, direct bij de prijs waar hij over gaat. --}}
    <template x-if="state.viewer?.state === 'highest' && isOpen">
        <p class="d-soft is-ok" style="margin:0">Je bent de hoogste bieder.</p>
    </template>
    <template x-if="state.viewer?.state === 'outbid' && isOpen">
        <p class="d-soft is-warn" style="margin:0">Je bent overboden.</p>
    </template>

    {{-- Slotfase: de klok springt zichtbaar, en er staat bij waaróm. --}}
    <template x-if="status === 'closing'">
        <p class="d-soft" style="margin:0">Een bod nu verlengt de veiling met een minuut, zodat anderen kunnen reageren.</p>
    </template>

    {{-- Verbinding kwijt: nooit stilzwijgend een bevroren scherm ----------- --}}
    <template x-if="connection === 'reconnecting'">
        <p class="d-soft" style="margin:0">Verbinding kwijt - we halen de stand op.</p>
    </template>
    <template x-if="connection === 'stale'">
        <p class="d-soft is-err" style="margin:0">De stand kon niet ververst worden. Bieden staat uit tot we weer verbinding hebben - bieden op een verouderde stand kost geld.</p>
    </template>

    {{ $slot }}

    {{-- Wat er te doen is ------------------------------------------------- --}}
    <div>
        @guest
            <a href="{{ route('login') }}" class="d-btn d-btn-wide d-btn-sq">Inloggen om te bieden</a>
        @endguest

        @auth
            {{-- Eigen kavel --}}
            <template x-if="state.viewer?.is_seller">
                <p class="d-soft" style="margin:0">Dit is je eigen kavel. Bieden op je eigen kavel kan niet.</p>
            </template>

            {{-- Niet geverifieerd, met de route ernaartoe --}}
            <template x-if="isOpen && ! state.viewer?.is_seller && ! state.viewer?.can_bid">
                <div class="d-soft">
                    <p style="margin:0">Rond eerst je verificatie af; een medewerker beoordeelt die, meestal binnen een werkdag.</p>
                    <p class="d-hint">Nodig vóór je eerste bod, niet vóór het rondkijken.</p>
                    <a href="{{ route('verification.overview') }}" class="d-btn d-btn-sm d-btn-sq" style="margin-top:10px">Verificatie starten</a>
                </div>
            </template>

            {{-- Gesloten: gewonnen of verloren --}}
            <template x-if="state.status === 'closed'">
                <div>
                    <template x-if="state.viewer?.state === 'won'">
                        <div class="celebrate d-soft is-ok" style="text-align:center;padding:20px">
                            <svg class="mx-auto size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M8 21h8m-4-4v4M7 4h10v4a5 5 0 0 1-10 0V4Z"/>
                                <path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3"/>
                            </svg>
                            <p style="margin:8px 0 0;font-size:20px;font-weight:800;color:#0e1526">Gewonnen - hij is van jou!</p>
                            <p style="margin:4px 0 0;font-weight:500;color:#47505f">Het hoogste bod was van jou. Rond de betaling af, dan komt hij jouw kant op.</p>
                            <template x-if="state.viewer?.won_order_number">
                                <a :href="'/bestellingen/' + state.viewer.won_order_number" class="d-btn d-btn-sq" style="margin-top:14px">Naar je bestelling</a>
                            </template>
                        </div>
                    </template>

                    <template x-if="state.viewer?.state !== 'won'">
                        <p class="d-soft" style="margin:0">
                            <span x-show="state.viewer?.state === 'lost'">Je hebt niet gewonnen.</span>
                            <span x-show="state.viewer?.state !== 'lost'">Deze veiling is gesloten.</span>
                        </p>
                    </template>

                    {{-- Gerealiseerde prijs en biednaam zijn openbaar (vraag 13, besluit Don). --}}
                    @php $soldOrder = \App\Support\PlatformFacts::publicSoldPrices() ? $auction->lot?->order : null; @endphp
                    @if ($soldOrder)
                        <p class="d-note" style="margin-top:8px">
                            Verkocht voor <strong class="tabular">{{ \App\Support\Money\Money::format($soldOrder->hammer_cents) }}</strong>
                            aan {{ $soldOrder->buyer?->publicName() ?? 'een koper' }}.
                        </p>
                    @endif
                </div>
            </template>

            {{-- Kan bieden --}}
            <template x-if="isOpen && state.viewer?.can_bid && ! state.viewer?.is_seller">
                {{--
                    De meldingen kennen een rangorde: eerst "te laag" (dat bod
                    kan sowieso niet), pas daarna de borg (die is alleen
                    relevant voor een bod dat anders geldig zou zijn).
                    De formulierlogica en het geldformaat staan in bid-form.js.
                    Het formulier staat open, zoals in het ontwerp.
                --}}
                <div x-data="bidForm" x-init="open = true" class="d-stack-tight">

                    {{-- Snel kiezen: het minimum en de twee volgende staffelstappen. --}}
                    <div class="d-chips">
                        <template x-for="quick in (state.quick_bid_cents ?? [])" :key="quick">
                            <button type="button" @click="setAmountCents(quick)" class="d-chip d-chip-sm tabular"
                                    :class="amountCents === quick && 'is-on'" x-text="formatCents(quick)"></button>
                        </template>
                    </div>

                    <label class="d-field" for="bid-amount">Jouw bod
                        <span class="d-input-wrap">
                            <i aria-hidden="true">&euro;</i>
                            <input id="bid-amount" type="text" inputmode="decimal" autocomplete="off"
                                   x-model="amountText" x-ref="amountInput"
                                   @input="reformat($event, 'amountText')"
                                   :disabled="isBlocked"
                                   @blur="if (tooLow) setAmountCents(state.minimum_bid_cents)"
                                   class="d-input is-amount tabular">
                        </span>
                        <span class="d-hint" :class="tooLow && 'is-warn'">
                            Minimaal <span class="tabular" x-text="formatCents(state.minimum_bid_cents)"></span><span x-show="! tooLow">: huidig bod plus de biedstap.</span><span x-show="tooLow" x-cloak>: je bedrag ligt eronder.</span>
                        </span>
                    </label>

                    {{-- Automatisch meebieden, direct onder het bedrag. --}}
                    <div x-show="! state.viewer?.max_amount_cents">
                        <label class="d-field" for="max-amount">Automatisch meebieden tot (optioneel)
                            <span class="d-input-wrap">
                                <i aria-hidden="true">&euro;</i>
                                <input id="max-amount" type="text" inputmode="decimal" autocomplete="off"
                                       x-model="maxText" @input="reformat($event, 'maxText'); showMax = maxCents > 0"
                                       class="d-input is-amount tabular">
                            </span>
                            <span class="d-hint">Niemand ziet jouw maximum, ook de verkoper niet.</span>
                        </label>
                        <button type="button" x-show="maxCents > 0" x-cloak @click="setMaxBid(maxCents)"
                                class="d-btn d-btn-secondary d-btn-sm d-btn-sq" style="margin-top:8px">Maximum instellen</button>
                    </div>
                    <template x-if="state.viewer?.max_amount_cents">
                        <div class="d-soft">
                            <strong>Automatisch meebieden tot <span class="tabular" x-text="formatCents(state.viewer.max_amount_cents)"></span>.</strong>
                            We bieden voor je terug, nooit boven jouw limiet.
                            <button type="button" class="d-link" style="display:block;margin-top:4px" @click="cancelMaxBid()">Uitzetten (je huidige bod blijft staan)</button>
                        </div>
                    </template>

                    {{--
                        Bieden is slepen, geen klik: van links naar rechts, als
                        bewuste handeling die niet per ongeluk gebeurt. Ook met
                        het toetsenbord bedienbaar (pijltjes naar rechts).
                    --}}
                    <div x-show="pendingBid === null && ! isBlocked && ! tooLow && ! needsDeposit"
                         x-data="slideConfirm(() => placeBid(amountCents))">
                        <div x-ref="track" class="d-slide"
                             role="slider" tabindex="0"
                             :aria-valuenow="Math.round(progress)" aria-valuemin="0" aria-valuemax="100"
                             :aria-label="'Sleep naar rechts of gebruik de pijltjestoetsen om ' + formatCents(amountCents) + ' te bieden'"
                             @keydown.arrow-right.prevent="nudge(10)"
                             @keydown.arrow-left.prevent="nudge(-10)"
                             @keydown.end.prevent="nudge(100)"
                             @keydown.home.prevent="reset()"
                             @keydown.escape.prevent="reset()"
                             @pointermove.window="moveDrag($event)"
                             @pointerup.window="endDrag()"
                             @pointercancel.window="endDrag()">
                            <div class="fill" :style="`width: calc(${progress} * (100% - 10px) / 100 + 10px)`"></div>
                            <span class="label">Sleep om <span class="tabular" x-text="formatCents(amountCents)"></span> te bieden</span>
                            <div class="handle" aria-hidden="true" :class="dragging && 'dragging'"
                                 :style="`left: calc(5px + ${progress} * (100% - 60px) / 100)`"
                                 @pointerdown="startDrag($event)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </div>
                        </div>
                    </div>

                    {{-- Je eigen bod verschijnt direct als "wordt verwerkt". --}}
                    <p x-show="pendingBid !== null" x-cloak class="d-soft" style="margin:0;text-align:center">Wordt verwerkt…</p>

                    {{-- De borg: vooraf op de pagina, niet pas bij het klikken (hfst. 9). --}}
                    <div x-show="! tooLow && needsDeposit" x-cloak class="d-soft">
                        <p style="margin:0">
                            Boven <span class="tabular" x-text="formatCents(state.viewer?.deposit?.threshold_cents)"></span>
                            vragen we eerst een borg van
                            <span class="tabular" x-text="formatCents(state.viewer?.deposit?.threshold_cents)"></span> -
                            die je terugkrijgt als je niet wint.
                        </p>
                        <template x-if="state.viewer?.deposit?.available">
                            <button type="button" @click="arrangeDeposit()" class="d-btn d-btn-sm d-btn-sq" style="margin-top:10px">Borg regelen</button>
                        </template>
                        <template x-if="! state.viewer?.deposit?.available">
                            <p class="d-hint">{{ __('auctions.deposit.provider_missing') }}</p>
                        </template>
                    </div>
                </div>
            </template>
        @endauth
    </div>

    <template x-if="error">
        <p role="alert" class="d-soft is-err" style="margin:0" x-text="error.message"></p>
    </template>

    <p class="d-note">
        Een bod is bindend. Een bod in de laatste {{ $auction->anti_snipe_window_s ?? 30 }} seconden verlengt de veiling.
        <a href="{{ route('assurance') }}">Hoe werkt dit?</a>
    </p>
</div>
