{{--
    Het hoofdpaneel voor een koop-direct-kavel: vaste prijs, geen bieden.
    De handeling is de tweetrap uit hfst. 6: eerst de kostenopbouw zien,
    dan pas kopen. Opmaak: de witte kaart uit het ontwerp van november.
--}}
<div class="d-bidcard" id="bied" style="scroll-margin-top:12px" x-cloak x-data="{ costs: null, loading: false }">

    <div class="d-bidcard-top">
        <template x-if="isOpen"><span class="d-tag tone-ok">Koop direct</span></template>
        <template x-if="state.status === 'closed'"><span class="d-tag tone-grey">Verkocht</span></template>
        <span class="d-bidcard-time"><x-auction.countdown /></span>
    </div>

    <div>
        <div class="d-muted">Vaste prijs · geen bieden, wie het eerst koopt<template x-if="(state.watching_now ?? 0) > 0"><span> · <span x-text="state.watching_now"></span> kijken nu</span></template></div>
        <div class="d-bidcard-price" x-text="formatCents(state.buy_now?.price_cents ?? state.price_cents)"></div>
    </div>

    {{ $slot }}

    <div>
        @guest
            <a href="{{ route('login') }}" class="d-btn d-btn-wide d-btn-sq">Inloggen om te kopen</a>
        @endguest

        @auth
            <template x-if="state.viewer?.is_seller">
                <p class="d-soft" style="margin:0">Dit is je eigen kavel.</p>
            </template>

            <template x-if="! state.viewer?.is_seller && ! state.viewer?.can_bid && isOpen">
                <div class="d-soft">
                    <p style="margin:0">Rond eerst je verificatie af; een medewerker beoordeelt die, meestal binnen een werkdag.</p>
                    <a href="{{ route('verification.overview') }}" class="d-btn d-btn-sm d-btn-sq" style="margin-top:10px">Verificatie starten</a>
                </div>
            </template>

            {{-- Gesloten: gekocht door jou, of door een ander --}}
            <template x-if="state.status === 'closed'">
                <div>
                    <template x-if="state.viewer?.state === 'won'">
                        <div class="celebrate d-soft is-ok" style="text-align:center;padding:20px">
                            <svg class="mx-auto size-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M8 21h8m-4-4v4M7 4h10v4a5 5 0 0 1-10 0V4Z"/>
                                <path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3"/>
                            </svg>
                            <p style="margin:8px 0 0;font-size:20px;font-weight:800;color:#0e1526">Hij is van jou!</p>
                            <p style="margin:4px 0 0;font-weight:500;color:#47505f">Rond de betaling af, dan komt hij jouw kant op.</p>
                            <template x-if="state.viewer?.won_order_number">
                                <a :href="'/bestellingen/' + state.viewer.won_order_number" class="d-btn d-btn-sq" style="margin-top:14px">Naar je bestelling</a>
                            </template>
                        </div>
                    </template>
                    <template x-if="state.viewer?.state !== 'won'">
                        <p class="d-soft" style="margin:0">Deze kavel is verkocht.</p>
                    </template>
                </div>
            </template>

            {{-- Kan kopen: eerst de kostenopbouw, dan pas de koop (hfst. 6) --}}
            <template x-if="isOpen && state.viewer?.can_bid && ! state.viewer?.is_seller">
                <div>
                    <template x-if="! costs">
                        <button type="button" :disabled="loading || isBlocked"
                                @click="loading = true; costs = (await buyNow(false))?.costs; loading = false"
                                class="d-btn d-btn-wide d-btn-sq" style="min-height:52px">
                            <span x-show="! loading">Koop voor <span class="tabular" x-text="formatCents(state.buy_now?.price_cents ?? state.price_cents)"></span></span>
                            <span x-show="loading" x-cloak>Even rekenen…</span>
                        </button>
                    </template>

                    <template x-if="costs">
                        <div role="group" aria-label="Bevestig je koop" x-init="$nextTick(() => $el.querySelector('button')?.focus())">
                            <div class="d-costs">
                                <template x-for="line in costs.lines" :key="line.key">
                                    <div class="d-row">
                                        <span><span x-text="line.label"></span><template x-if="line.note"><small x-text="line.note"></small></template></span>
                                        <strong x-text="line.formatted"></strong>
                                    </div>
                                </template>
                                <div class="d-row is-total"><span>Jij betaalt</span><strong x-text="costs.total_formatted"></strong></div>
                            </div>
                            <button type="button" @click="buyNow(true)" :disabled="isBlocked || busy" class="d-btn d-btn-wide d-btn-sq" style="margin-top:10px;min-height:52px">
                                <span x-show="! busy">Bevestigen en kopen</span>
                                <span x-show="busy" x-cloak>Even geduld…</span>
                            </button>
                            <button type="button" @click="costs = null" class="d-link" style="display:block;width:100%;margin-top:8px;text-align:center">Annuleren</button>
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
        <strong>Koop direct:</strong> vaste prijs, geen bieden. Wie het eerst koopt, koopt.
        <a href="{{ route('assurance') }}">Hoe werkt dit?</a>
    </p>
</div>
