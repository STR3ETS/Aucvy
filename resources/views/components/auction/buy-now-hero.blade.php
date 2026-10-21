{{--
    Het hoofdpaneel voor een koop-direct-kavel: vaste prijs, geen bieden.
    Zelfde hero-uitstraling als het biedpaneel, maar de handeling is de
    tweetrap uit hfst. 6: eerst de kostenopbouw zien, dan pas kopen.
--}}
<div class="panel-hero rounded-aj-s p-5" x-cloak x-data="{ costs: null, loading: false }">

    {{-- Het stippenveld uit de hero, achter de inhoud. --}}
    <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>

    {{-- Statusbalk ------------------------------------------------------- --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <template x-if="isOpen">
            <span class="flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-xs font-extrabold text-aj-navy">
                <span class="size-1.5 rounded-full bg-aj-success" aria-hidden="true"></span>
                Koop direct
            </span>
        </template>
        <template x-if="state.status === 'closed'">
            <span class="rounded-full bg-white/20 px-2.5 py-1 text-xs font-extrabold text-white">
                Verkocht
            </span>
        </template>

        <span class="flex items-center gap-1.5 text-sm text-white/80">
            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="10" cy="10" r="7.25"/>
                <path d="M10 6v4l2.5 2"/>
            </svg>
            <x-auction.countdown theme="dark" />
        </span>
    </div>

    {{-- Prijs ------------------------------------------------------------- --}}
    <p class="mt-4 text-xs text-white/70">Vaste prijs</p>
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <p class="tabular text-3xl font-extrabold" x-text="formatCents(state.buy_now?.price_cents ?? state.price_cents)"></p>
        <p class="flex flex-wrap items-center gap-x-1.5 text-xs text-white/70">
            <span>geen bieden, wie het eerst koopt</span>
            <template x-if="(state.watching_now ?? 0) > 0">
                <span class="inline-flex items-center gap-1">·
                    <svg class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10Z"/><circle cx="10" cy="10" r="2.4"/>
                    </svg>
                    <span x-text="state.watching_now"></span> kijken nu
                </span>
            </template>
        </p>
    </div>

    {{-- Wat er te doen is ------------------------------------------------- --}}
    <div class="mt-4">
        @guest
            <a href="{{ route('login') }}"
               class="flex w-full items-center justify-center rounded-aj-sm bg-white px-5 text-sm font-extrabold text-aj-navy"
               style="min-height: var(--spacing-cta)">
                Inloggen om te kopen
            </a>
        @endguest

        @auth
            <template x-if="state.viewer?.is_seller">
                <p class="rounded-aj-sm bg-white/12 px-3 py-3 text-sm text-white/90">
                    Dit is je eigen kavel.
                </p>
            </template>

            <template x-if="! state.viewer?.is_seller && ! state.viewer?.can_bid && isOpen">
                <div class="rounded-aj-sm bg-white/12 p-3 text-sm">
                    <p>Rond eerst je verificatie af - dat duurt twee minuten.</p>
                    <a href="{{ route('verification.overview') }}"
                       class="mt-2 inline-block rounded-aj-sm bg-white px-3 py-2 text-xs font-extrabold text-aj-navy">
                        Verificatie starten
                    </a>
                </div>
            </template>

            {{-- Gesloten: gekocht door jou, of door een ander --}}
            <template x-if="state.status === 'closed'">
                <div>
                    <template x-if="state.viewer?.state === 'won'">
                        <div class="celebrate rounded-aj-sm bg-white/15 p-5 text-center">
                            <svg class="mx-auto size-10 text-amber-300" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="1.7" stroke-linecap="round"
                                 stroke-linejoin="round" aria-hidden="true">
                                <path d="M8 21h8m-4-4v4M7 4h10v4a5 5 0 0 1-10 0V4Z"/>
                                <path d="M7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3"/>
                            </svg>
                            <p class="mt-2 text-xl font-extrabold">Hij is van jou!</p>
                            <p class="mt-1 text-sm text-white/85">
                                Rond de betaling af, dan komt hij jouw kant op.
                            </p>
                            <template x-if="state.viewer?.won_order_number">
                                <a :href="'/bestellingen/' + state.viewer.won_order_number"
                                   class="mt-4 inline-flex items-center justify-center rounded-aj-sm bg-white px-5 text-sm font-extrabold text-aj-navy"
                                   style="min-height: var(--spacing-cta)">
                                    Naar je bestelling
                                </a>
                            </template>
                        </div>
                    </template>
                    <template x-if="state.viewer?.state !== 'won'">
                        <p class="rounded-aj-sm bg-white/12 px-3 py-3 text-sm text-white/90">
                            Deze kavel is verkocht.
                        </p>
                    </template>
                </div>
            </template>

            {{-- Kan kopen: eerst de kostenopbouw, dan pas de koop (hfst. 6) --}}
            <template x-if="isOpen && state.viewer?.can_bid && ! state.viewer?.is_seller">
                <div>
                    <template x-if="! costs">
                        <button type="button"
                                :disabled="loading || isBlocked"
                                @click="loading = true; costs = (await buyNow(false))?.costs; loading = false"
                                class="flex w-full items-center justify-center rounded-aj-sm bg-white px-5 text-sm font-extrabold text-aj-navy disabled:opacity-60"
                                style="min-height: var(--spacing-cta)">
                            <span x-show="! loading">Koop voor <span class="tabular ml-1" x-text="formatCents(state.buy_now?.price_cents ?? state.price_cents)"></span></span>
                            <span x-show="loading" x-cloak>Even rekenen…</span>
                        </button>
                    </template>

                    <template x-if="costs">
                        <div class="rounded-aj-sm bg-white/12 p-3">
                            <dl class="space-y-1 text-sm">
                                <template x-for="line in costs.lines" :key="line.key">
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-white/75">
                                            <span x-text="line.label"></span>
                                            <template x-if="line.note">
                                                <span class="block text-xs text-white/55" x-text="line.note"></span>
                                            </template>
                                        </dt>
                                        <dd class="tabular font-semibold" x-text="line.formatted"></dd>
                                    </div>
                                </template>
                                <div class="flex justify-between gap-3 border-t border-white/15 pt-2">
                                    <dt class="font-extrabold">Totaal</dt>
                                    <dd class="tabular font-extrabold" x-text="costs.total_formatted"></dd>
                                </div>
                            </dl>

                            <button type="button" @click="buyNow(true)"
                                    class="mt-3 flex w-full items-center justify-center rounded-aj-sm bg-white px-4 text-sm font-extrabold text-aj-navy"
                                    style="min-height: var(--spacing-cta)">
                                Bevestigen en kopen
                            </button>
                            <button type="button" @click="costs = null"
                                    class="mt-1 w-full text-xs text-white/70 underline">
                                Annuleren
                            </button>
                        </div>
                    </template>
                </div>
            </template>
        @endauth
    </div>

    <template x-if="error">
        <p role="alert" class="mt-3 rounded-aj-sm bg-aj-navy/40 px-3 py-2 text-sm font-semibold text-red-200"
           x-text="error.message"></p>
    </template>

    {{-- Hoe deze verkoopvorm werkt, in één regel ---------------------------- --}}
    <p class="mt-4 border-t border-white/15 pt-3 text-xs text-white/70">
        <span class="font-bold text-white">Koop direct:</span>
        vaste prijs, geen bieden. Wie het eerst koopt, koopt.
        <a href="{{ route('assurance') }}" class="font-semibold text-white underline">Hoe werkt dit?</a>
    </p>
</div>
