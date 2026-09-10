{{--
    Koop-direct. Twee stappen: eerst de kostenopbouw, dan pas kopen.

    De knop verdwijnt zodra er tot aan of boven de vaste prijs geboden is -
    met uitleg, niet met een fout op het moment dat iemand dacht te kopen
    (hfst. 6).
--}}
<div class="rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4"
     x-cloak
     x-data="{ costs: null, loading: false }">

    <template x-if="state.buy_now?.active">
        <div>
            <p class="text-sm font-extrabold">Koop direct</p>
            <p class="tabular mt-1 text-xl font-extrabold"
               x-text="formatCents(state.buy_now.price_cents)"></p>

            @auth
                <template x-if="! state.viewer?.is_seller && state.viewer?.can_bid">
                    <div class="mt-3">
                        <template x-if="! costs">
                            <button type="button"
                                    :disabled="loading || isBlocked"
                                    @click="loading = true; costs = (await buyNow(false))?.costs; loading = false"
                                    class="flex w-full items-center justify-center rounded-aj-sm
                                           border border-aj-border-strong px-4 text-sm font-semibold
                                           disabled:opacity-60"
                                    style="min-height: var(--spacing-cta)">
                                <span x-show="! loading">Wat kost dit totaal?</span>
                                <span x-show="loading">Even rekenen…</span>
                            </button>
                        </template>

                        <template x-if="costs">
                            <div>
                                <dl class="space-y-1 text-sm">
                                    <template x-for="line in costs.lines" :key="line.key">
                                        <div class="flex justify-between gap-3">
                                            <dt class="text-aj-muted">
                                                <span x-text="line.label"></span>
                                                <template x-if="line.note">
                                                    <span class="block text-xs" x-text="line.note"></span>
                                                </template>
                                            </dt>
                                            <dd class="tabular font-semibold" x-text="line.formatted"></dd>
                                        </div>
                                    </template>
                                    <div class="flex justify-between gap-3 border-t border-aj-border pt-2">
                                        <dt class="font-extrabold">Totaal</dt>
                                        <dd class="tabular font-extrabold" x-text="costs.total_formatted"></dd>
                                    </div>
                                </dl>

                                <button type="button" @click="buyNow(true)"
                                        class="mt-3 flex w-full items-center justify-center rounded-aj-sm
                                               bg-aj-blue px-4 text-sm font-semibold text-white"
                                        style="min-height: var(--spacing-cta)">
                                    Bevestigen en kopen
                                </button>
                                <button type="button" @click="costs = null"
                                        class="mt-1 w-full text-xs text-aj-muted underline">
                                    Annuleren
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
            @endauth

            @guest
                <a href="{{ route('login') }}"
                   class="mt-3 flex w-full items-center justify-center rounded-aj-sm
                          border border-aj-border-strong px-4 text-sm font-semibold"
                   style="min-height: var(--spacing-cta)">
                    Inloggen om te kopen
                </a>
            @endguest
        </div>
    </template>

    <template x-if="state.buy_now && ! state.buy_now.active">
        <div>
            <p class="text-sm font-extrabold text-aj-muted">Koop direct vervallen</p>
            <p class="mt-1 text-sm text-aj-muted">
                Er is boven de vaste prijs van
                <span class="tabular" x-text="formatCents(state.buy_now.price_cents)"></span>
                geboden. Vanaf nu loopt het via de veiling.
            </p>
        </div>
    </template>
</div>
