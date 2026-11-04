{{--
    Afslag: de prijs zakt, jij beslist wanneer je toeslaat.

    Geen schuifknop om af te mijnen - een knop plus een expliciete bevestiging.
    Per ongeluk € 13.750 uitgeven mag niet één tik kosten (mobiele buildlijst).
    Opmaak: de witte kaart uit het ontwerp van november.
--}}
<div class="d-bidcard" id="bied" style="scroll-margin-top:12px" x-cloak
     x-data="{
        confirming: false,
        /*
         | De borg beweegt mee met de dalende prijs: zakt hij onder de
         | drempel, dan is er geen borg meer nodig en verdwijnt de vraag.
         */
        get needsDeposit() {
            return ! this.state.viewer?.deposit?.covered
                && this.state.price_cents > (this.state.viewer?.deposit?.threshold_cents ?? Infinity);
        },
     }">

    <div class="d-bidcard-top">
        <template x-if="isOpen"><span class="d-tag tone-atlas">Afslag</span></template>
        <template x-if="status === 'closed'"><span class="d-tag tone-grey">Gesloten</span></template>
        <template x-if="status === 'paused'"><span class="d-tag tone-grey">Gepauzeerd</span></template>
        <span class="d-bidcard-time"><x-auction.countdown /></span>
    </div>

    <div>
        <div class="d-muted">Huidige prijs<template x-if="(state.watching_now ?? 0) > 0"><span> · <span x-text="state.watching_now"></span> kijken nu - wie het eerst toeslaat koopt</span></template></div>
        <div class="d-bidcard-price" :class="priceDropped && 'price-drop'" x-text="formatCents(state.price_cents)"></div>
        <template x-if="dutchDropIn !== null">
            <div style="margin-top:6px">
                <div class="d-muted">
                    Daalt over <strong class="tabular" x-text="dutchDropIn"></strong>s
                    met <strong class="tabular" x-text="formatCents(state.dutch.step_cents)"></strong>
                </div>
                {{-- Het leeglopende balkje: je ziet de stap aankomen. --}}
                <div class="d-depth-bar" aria-hidden="true" style="margin-top:6px">
                    <i style="transition:width 1s linear" :style="`width:${Math.max(0, Math.min(100, dutchDropIn / state.dutch.interval_seconds * 100))}%`"></i>
                </div>
            </div>
        </template>
        <template x-if="floorReached">
            {{-- Een teller die nergens heen loopt is erger dan geen teller. --}}
            <p class="d-hint is-warn">De bodemprijs is bereikt. Lager wordt hij niet.</p>
        </template>
    </div>

    {{ $slot }}

    <div>
        @guest
            <a href="{{ route('login') }}" class="d-btn d-btn-wide d-btn-sq">Inloggen om af te mijnen</a>
        @endguest

        @auth
            <template x-if="state.viewer?.is_seller">
                <p class="d-soft" style="margin:0">Dit is je eigen kavel.</p>
            </template>

            <template x-if="state.status === 'closed'">
                <div>
                    {{-- Wie hem zelf afmijnde, krijgt geen mededeling maar een moment - plus de route naar de betaling. --}}
                    <template x-if="state.viewer?.state === 'won'">
                        <div class="celebrate d-soft is-ok" style="text-align:center;padding:20px">
                            <svg class="mx-auto size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M8 21h8m-4-4v4M7 4h10v4a5 5 0 0 1-10 0V4Z"/>
                                <path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3"/>
                            </svg>
                            <p style="margin:8px 0 0;font-size:20px;font-weight:800;color:#0e1526">Hij is van jou!</p>
                            <p style="margin:4px 0 0;font-weight:500;color:#47505f">Jij was de snelste. Rond de betaling af, dan komt hij jouw kant op.</p>
                            <template x-if="state.viewer?.won_order_number">
                                <a :href="'/bestellingen/' + state.viewer.won_order_number" class="d-btn d-btn-sq" style="margin-top:14px">Naar je bestelling</a>
                            </template>
                        </div>
                    </template>
                    <template x-if="state.viewer?.state !== 'won'">
                        <p class="d-soft" style="margin:0">Deze kavel is al afgemijnd.</p>
                    </template>
                </div>
            </template>

            <template x-if="! state.viewer?.can_bid && ! state.viewer?.is_seller && state.status !== 'closed'">
                <div class="d-soft">
                    <p style="margin:0">Rond eerst je verificatie af; een medewerker beoordeelt die, meestal binnen een werkdag.</p>
                    <a href="{{ route('verification.overview') }}" class="d-btn d-btn-sm d-btn-sq" style="margin-top:10px">Verificatie starten</a>
                </div>
            </template>

            <template x-if="state.viewer?.can_bid && ! state.viewer?.is_seller && state.status !== 'closed'">
                <div class="d-stack-tight">
                    {{-- De borg vóóraf, niet pas bij het afmijnen (hfst. 9). --}}
                    <template x-if="needsDeposit">
                        <div class="d-soft">
                            <p style="margin:0">
                                Boven <span class="tabular" x-text="formatCents(state.viewer?.deposit?.threshold_cents)"></span>
                                vragen we eerst een borg van
                                <span class="tabular" x-text="formatCents(state.viewer?.deposit?.threshold_cents)"></span> -
                                die je terugkrijgt als je niet koopt.
                            </p>
                            <template x-if="state.viewer?.deposit?.available">
                                <button type="button" @click="arrangeDeposit()" class="d-btn d-btn-sm d-btn-sq" style="margin-top:10px">Borg regelen</button>
                            </template>
                            <template x-if="! state.viewer?.deposit?.available">
                                <p class="d-hint">{{ __('auctions.deposit.provider_missing') }}</p>
                            </template>
                            <p class="d-hint">Daarna kun je direct afmijnen - of wacht tot de prijs onder de drempel zakt.</p>
                        </div>
                    </template>

                    <template x-if="! needsDeposit && ! confirming">
                        <button type="button" :disabled="isBlocked" @click="confirming = true" class="d-btn d-btn-wide d-btn-sq" style="min-height:52px">
                            Mijn af voor <span class="tabular" x-text="formatCents(state.price_cents)"></span>
                        </button>
                    </template>

                    <template x-if="! needsDeposit && confirming">
                        {{-- De focus gaat mee naar de bevestiging: de knop die hem had is net verdwenen. --}}
                        <div class="d-soft" role="group" aria-label="Bevestig je koop"
                             x-init="$nextTick(() => $el.querySelector('button')?.focus())">
                            <p style="margin:0">
                                Je koopt deze kavel voor
                                <strong class="tabular" x-text="formatCents(state.price_cents)"></strong>.
                                De veiling sluit daarmee direct.
                            </p>
                            <p class="d-hint">Zakt de prijs precies nu, dan reken je af tegen de nieuwe, lagere prijs.</p>
                            <div class="d-pair" style="margin-top:10px">
                                <button type="button" @click="acceptDutch()" :disabled="isBlocked || busy" class="d-btn d-btn-sq">
                                    <span x-show="! busy">Ja, afmijnen</span>
                                    <span x-show="busy" x-cloak>Even geduld…</span>
                                </button>
                                <button type="button" @click="confirming = false" class="d-btn d-btn-secondary d-btn-sq">Annuleren</button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        @endauth
    </div>

    <template x-if="error">
        <p role="alert" class="d-soft is-err" style="margin:0" x-text="error.message"></p>
    </template>

    <p class="d-note">
        <strong>Afslag:</strong> de prijs zakt vanzelf, wie het eerst toeslaat koopt.
        <a href="{{ route('assurance') }}">Hoe werkt dit?</a>
    </p>
</div>
