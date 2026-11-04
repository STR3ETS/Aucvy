{{--
    Koop-direct naast een veiling. Twee stappen: eerst de kostenopbouw, dan
    pas kopen. De knop verdwijnt zodra er tot aan of boven de vaste prijs
    geboden is - met uitleg, niet met een fout op het moment dat iemand
    dacht te kopen (hfst. 6). Opmaak: het groene blok uit het ontwerp.
--}}
<div class="d-buynow" x-cloak x-data="{ costs: null, loading: false }">

    <template x-if="state.buy_now?.active">
        <div class="d-stack-tight">
            <div class="d-buynow-head">
                <strong>Koop direct, niet wachten</strong>
                <span class="d-muted">vaste prijs, geen bieden</span>
            </div>

            @auth
                <template x-if="! state.viewer?.is_seller && state.viewer?.can_bid">
                    <div>
                        <template x-if="! costs">
                            <button type="button" :disabled="loading || isBlocked"
                                    @click="loading = true; costs = (await buyNow(false))?.costs; loading = false"
                                    class="d-btn d-btn-wide d-btn-sq" style="min-height:52px;background:#177249;border-color:#177249">
                                <span x-show="! loading">Koop voor <span class="tabular" x-text="formatCents(state.buy_now.price_cents)"></span></span>
                                <span x-show="loading">Even rekenen…</span>
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
                                <button type="button" @click="buyNow(true)" :disabled="isBlocked || busy" class="d-btn d-btn-wide d-btn-sq" style="margin-top:10px;background:#177249;border-color:#177249">
                                    <span x-show="! busy">Bevestigen en kopen</span>
                                    <span x-show="busy" x-cloak>Even geduld…</span>
                                </button>
                                <button type="button" @click="costs = null" class="d-link" style="display:block;width:100%;margin-top:8px;text-align:center">Annuleren</button>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="state.viewer?.is_seller || ! state.viewer?.can_bid">
                    <p class="d-note">Vaste prijs <strong class="tabular" x-text="formatCents(state.buy_now.price_cents)"></strong>.</p>
                </template>
            @endauth

            @guest
                <a href="{{ route('login') }}" class="d-btn d-btn-wide d-btn-sq" style="background:#177249;border-color:#177249">Inloggen om te kopen voor <span class="tabular" x-text="formatCents(state.buy_now.price_cents)"></span></a>
            @endguest

            <p class="d-note">Direct van jou, de veiling stopt meteen. De kosten zie je vóór je bevestigt.</p>
        </div>
    </template>

    <template x-if="state.buy_now && ! state.buy_now.active">
        <div>
            <strong class="d-muted" style="font-weight:700">Koop direct vervallen</strong>
            <p class="d-note">
                Er is boven de vaste prijs van
                <span class="tabular" x-text="formatCents(state.buy_now.price_cents)"></span>
                geboden. Vanaf nu loopt het via de veiling.
            </p>
        </div>
    </template>
</div>
