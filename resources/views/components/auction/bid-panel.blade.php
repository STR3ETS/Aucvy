@props(['auction'])

{{--
    De acht biedstates uit hoofdstuk 13.3, elk met een eigen tekst:

      kan bieden · hoogste bieder · overboden · slotfase · niet geverifieerd
      · eigen kavel · gesloten · gewonnen · verloren

    Geen generieke fout, nergens. "Je bent nog niet geverifieerd" heeft een
    knop naar de verificatiestap; "je bod is te laag" heeft een bedrag.

    De kaart draagt dezelfde blauwe gloed als de hero op home (.panel-hero):
    dit is hét actiepunt van de pagina en mag dat gewicht hebben.
--}}
<div class="panel-hero rounded-aj-s p-5" x-cloak>

    {{-- Het stippenveld uit de hero, achter de inhoud. --}}
    <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>

    {{-- Statusbalk: wat voor moment is dit, en hoelang nog ----------------- --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <template x-if="status === 'live'">
            <span class="flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-xs font-extrabold text-aj-navy">
                <span class="size-1.5 animate-pulse rounded-full bg-aj-error" aria-hidden="true"></span>
                LIVE
            </span>
        </template>
        <template x-if="status === 'closing'">
            <span class="flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-xs font-extrabold text-aj-navy">
                <span class="size-1.5 animate-pulse rounded-full bg-aj-warn" aria-hidden="true"></span>
                Slotfase
            </span>
        </template>
        <template x-if="status === 'closed'">
            <span class="rounded-full bg-white/20 px-2.5 py-1 text-xs font-extrabold text-white">
                Gesloten
            </span>
        </template>
        <template x-if="status === 'paused'">
            <span class="rounded-full bg-white/20 px-2.5 py-1 text-xs font-extrabold text-white">
                Gepauzeerd
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

    {{-- Stand -------------------------------------------------------------- --}}
    <p class="mt-4 text-xs text-white/70">Huidig bod</p>
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <p class="tabular text-3xl font-extrabold" x-text="formatCents(state.price_cents)"></p>
        <p class="flex flex-wrap items-center gap-x-1.5 text-xs text-white/70">
            <span><span x-text="state.bidder_count"></span> bieders</span>
            <template x-if="state.extension_count > 0">
                <span>· <span x-text="state.extension_count"></span>× verlengd</span>
            </template>
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

    {{-- Jouw positie, direct bij de prijs waar hij over gaat. --}}
    <template x-if="state.viewer?.state === 'highest' && isOpen">
        <p class="mt-2 flex items-center gap-2 rounded-aj-sm bg-white/12 px-3 py-2 text-sm font-semibold text-emerald-200">
            <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
            </svg>
            Je bent de hoogste bieder.
        </p>
    </template>

    <template x-if="state.viewer?.state === 'outbid' && isOpen">
        <p class="mt-2 flex items-center gap-2 rounded-aj-sm bg-white/12 px-3 py-2 text-sm font-semibold text-amber-200">
            <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M10 3 2.5 16h15L10 3Zm0 5v4m0 2.5v.5"/>
            </svg>
            Je bent overboden.
        </p>
    </template>

    {{-- Slotfase: de klok springt zichtbaar, en er staat bij waaróm.
         Dit is een verkoopargument - verstop het niet (hfst. 13.3). --}}
    <template x-if="status === 'closing'">
        <p class="mt-3 rounded-aj-sm bg-white/12 px-3 py-2 text-xs text-amber-100">
            Een bod nu verlengt de veiling met een minuut, zodat anderen kunnen reageren.
        </p>
    </template>

    {{-- Verbinding kwijt: nooit stilzwijgend een bevroren scherm ----------- --}}
    <template x-if="connection === 'reconnecting'">
        <p class="mt-3 rounded-aj-sm bg-white/12 px-3 py-2 text-xs text-white/90">
            Verbinding kwijt - we halen de stand op.
        </p>
    </template>

    <template x-if="connection === 'stale'">
        <p class="mt-3 rounded-aj-sm bg-aj-navy/40 px-3 py-2 text-xs text-white">
            De stand kon niet ververst worden. Bieden staat uit tot we weer verbinding hebben -
            bieden op een verouderde stand kost geld.
        </p>
    </template>

    {{-- Wat er te doen is ------------------------------------------------- --}}
    <div class="mt-4">
        @guest
            <a href="{{ route('login') }}"
               class="flex w-full items-center justify-center rounded-aj-sm bg-white px-5 text-sm font-semibold text-aj-navy"
               style="min-height: var(--spacing-cta)">
                Inloggen om te bieden
            </a>
        @endguest

        @auth
            {{-- Eigen kavel --}}
            <template x-if="state.viewer?.is_seller">
                <p class="rounded-aj-sm bg-white/12 px-3 py-3 text-sm text-white/90">
                    Dit is je eigen kavel. Bieden op je eigen kavel kan niet.
                </p>
            </template>

            {{-- Niet geverifieerd, met de route ernaartoe --}}
            <template x-if="isOpen && ! state.viewer?.is_seller && ! state.viewer?.can_bid">
                <div class="rounded-aj-sm bg-white/12 p-3 text-sm">
                    <p>Rond eerst je verificatie af; een medewerker beoordeelt die, meestal binnen een werkdag.</p>
                    <p class="mt-1 text-xs text-white/70">
                        Nodig vóór je eerste bod, niet vóór het rondkijken.
                    </p>
                    <a href="{{ route('verification.overview') }}"
                       class="mt-2 inline-block rounded-aj-sm bg-white px-3 py-2 text-xs font-extrabold text-aj-navy">
                        Verificatie starten
                    </a>
                </div>
            </template>

            {{-- Gesloten: gewonnen of verloren --}}
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
                            <p class="mt-2 text-xl font-extrabold">Gewonnen - hij is van jou!</p>
                            <p class="mt-1 text-sm text-white/85">
                                Het hoogste bod was van jou. Rond de betaling af, dan komt hij jouw kant op.
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
                            <span x-show="state.viewer?.state === 'lost'">Je hebt niet gewonnen.</span>
                            <span x-show="state.viewer?.state !== 'lost'">Deze veiling is gesloten.</span>
                        </p>
                    </template>

                    {{-- Gerealiseerde prijs en biednaam zijn openbaar (vraag 13, besluit Don). --}}
                    @php $soldOrder = \App\Support\PlatformFacts::publicSoldPrices() ? $auction->lot?->order : null; @endphp
                    @if ($soldOrder)
                        <p class="mt-2 text-sm text-white/90">
                            Verkocht voor <span class="tabular font-extrabold">{{ \App\Support\Money\Money::format($soldOrder->hammer_cents) }}</span>
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
                --}}
                {{-- De formulierlogica en het geldformaat staan in bid-form.js. --}}
                <div x-data="bidForm">

                    {{-- Dicht: één duidelijke actie. Het formulier komt pas
                         na de klik - dat houdt de kaart in rust overzichtelijk. --}}
                    <div x-show="! open">
                        <button type="button" @click="open = true"
                                class="flex w-full items-center justify-center gap-2 rounded-aj-sm bg-white px-5 text-sm font-extrabold text-aj-navy"
                                style="min-height: var(--spacing-cta)">
                            Bied nu
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 10h12m-5-5 5 5-5 5"/>
                            </svg>
                        </button>
                        <template x-if="state.viewer?.max_amount_cents">
                            <p class="mt-2 text-center text-xs text-white/70">
                                Je stapt automatisch mee tot
                                <span class="tabular font-semibold text-white"
                                      x-text="formatCents(state.viewer.max_amount_cents)"></span>
                            </p>
                        </template>
                    </div>

                    <div x-show="open" x-cloak>

                    {{-- Snel kiezen: het minimum en de twee volgende
                         staffelstappen, rechtstreeks uit de veilingstand. --}}
                    <div class="flex gap-2">
                        <template x-for="quick in (state.quick_bid_cents ?? [])" :key="quick">
                            <button type="button" @click="setAmountCents(quick)"
                                    class="tabular flex-1 rounded-aj-sm border px-2 text-sm font-bold"
                                    :class="amountCents === quick
                                        ? 'border-white bg-white text-aj-navy'
                                        : 'border-white/30 bg-white/10 text-white'"
                                    style="min-height: 44px"
                                    x-text="formatCents(quick)"></button>
                        </template>
                    </div>

                    <label class="sr-only" for="bid-amount">Bedrag in euro's</label>
                    <div class="relative mt-2">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg font-bold text-aj-muted"
                              aria-hidden="true">&euro;</span>
                        <input id="bid-amount" type="text" inputmode="decimal" autocomplete="off"
                               x-model="amountText"
                               x-ref="amountInput"
                               @input="reformat($event, 'amountText')"
                               :disabled="isBlocked"
                               @blur="if (tooLow) setAmountCents(state.minimum_bid_cents)"
                               class="tabular w-full rounded-aj-sm border border-aj-border-strong bg-white pl-9 pr-3 text-lg font-bold text-aj-text"
                               style="min-height: var(--spacing-cta)">
                    </div>

                    <p class="mt-1.5 text-xs" :class="tooLow ? 'font-semibold text-amber-200' : 'text-white/70'">
                        Minimum <span class="tabular" x-text="formatCents(state.minimum_bid_cents)"></span>
                        <span x-show="tooLow" x-cloak>- je bedrag ligt eronder</span>
                    </p>

                    {{-- Automatisch meestappen, direct onder het bedrag. De
                         schakelaar staat al aan zodra je hem aanklikt, ook
                         terwijl het limietbedrag nog ingevuld wordt. --}}
                    <div class="mt-2 rounded-aj-sm border border-white/25 bg-white/10 p-3">
                        <div class="flex items-center gap-3">
                            <button type="button" role="switch"
                                    :aria-checked="(state.viewer?.max_amount_cents || showMax) ? 'true' : 'false'"
                                    @click="state.viewer?.max_amount_cents ? cancelMaxBid() : showMax = ! showMax"
                                    class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                                    :class="(state.viewer?.max_amount_cents || showMax) ? 'bg-emerald-400' : 'bg-white/25'"
                                    aria-label="Automatisch meestappen">
                                <span class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-all"
                                      :class="(state.viewer?.max_amount_cents || showMax) ? 'left-[22px]' : 'left-0.5'"></span>
                            </button>
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-bold">
                                    Automatisch meestappen
                                    <template x-if="state.viewer?.max_amount_cents">
                                        <span>tot <span class="tabular"
                                              x-text="formatCents(state.viewer.max_amount_cents)"></span></span>
                                    </template>
                                </p>
                                <p class="text-xs text-white/70">
                                    <span x-show="! state.viewer?.max_amount_cents">
                                        We bieden voor je terug, nooit boven jouw limiet.
                                    </span>
                                    <span x-show="state.viewer?.max_amount_cents" x-cloak>
                                        Uitzetten kan altijd - je huidige bod blijft staan.
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div x-show="showMax && ! state.viewer?.max_amount_cents" x-cloak class="mt-2 flex gap-2">
                            <label class="sr-only" for="max-amount">Maximum in euro's</label>
                            <div class="relative w-full">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-aj-muted"
                                      aria-hidden="true">&euro;</span>
                                <input id="max-amount" type="text" inputmode="decimal" autocomplete="off"
                                       x-model="maxText"
                                       @input="reformat($event, 'maxText')"
                                       class="tabular w-full rounded-aj-sm border border-aj-border-strong bg-white pl-8 pr-3 text-aj-text"
                                       style="min-height: var(--spacing-cta)">
                            </div>
                            <button type="button" @click="setMaxBid(maxCents)" :disabled="! (maxCents > 0)"
                                    class="shrink-0 rounded-aj-sm bg-white px-4 text-sm font-semibold text-aj-navy disabled:opacity-60"
                                    style="min-height: var(--spacing-cta)">
                                Instellen
                            </button>
                        </div>

                        <p x-show="showMax && ! state.viewer?.max_amount_cents" x-cloak class="mt-1 text-xs text-white/60">
                            Niemand ziet dit bedrag. Je betaalt alleen wat nodig is om voor te blijven.
                        </p>
                    </div>

                    {{--
                        Bieden is slepen, geen klik: van links naar rechts, als
                        bewuste handeling die niet per ongeluk gebeurt. Ook met
                        het toetsenbord bedienbaar (pijltjes naar rechts).
                        Te laag of borg nodig: het spoor verdwijnt en de uitleg
                        eronder zegt waarom.
                    --}}
                    <div x-show="pendingBid === null && ! isBlocked && ! tooLow && ! needsDeposit"
                         x-data="slideConfirm(() => placeBid(amountCents))"
                         class="mt-2">
                        <div x-ref="track"
                             class="slide-confirm"
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
                            <div class="fill" :style="`width: calc(${progress} * (100% - 8px) / 100 + 8px)`"></div>
                            <span class="label" :class="progress > 55 && 'label-past'">
                                Sleep om <span class="tabular">&nbsp;<span x-text="formatCents(amountCents)"></span>&nbsp;</span> te bieden
                            </span>
                            <div class="handle" aria-hidden="true"
                                 :class="dragging && 'dragging'"
                                 :style="`left: calc(4px + ${progress} * (100% - 52px) / 100)`"
                                 @pointerdown="startDrag($event)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M6 17l5-5-5-5"/>
                                    <path d="M13 17l5-5-5-5"/>
                                </svg>
                            </div>
                        </div>
                    </div>

                    {{-- Je eigen bod verschijnt direct als "wordt verwerkt";
                         de getóónde prijs verandert pas na bevestiging. --}}
                    <p x-show="pendingBid !== null" x-cloak
                       class="mt-2 flex items-center justify-center rounded-aj-sm bg-white/15 px-5 text-sm font-semibold text-white"
                       style="min-height: var(--spacing-cta)">
                        Wordt verwerkt…
                    </p>

                    {{-- De borg: vooraf op de pagina, niet pas bij het klikken
                         (hfst. 9). Alleen voor een bod dat verder geldig is. --}}
                    <div x-show="! tooLow && needsDeposit"
                         x-cloak class="mt-3 rounded-aj-sm bg-white/12 p-3 text-sm">
                        <p>
                            Boven <span class="tabular" x-text="formatCents(state.viewer?.deposit?.threshold_cents)"></span>
                            vragen we eerst een borg van
                            <span class="tabular" x-text="formatCents(state.viewer?.deposit?.threshold_cents)"></span> -
                            die je terugkrijgt als je niet wint.
                        </p>
                        <template x-if="state.viewer?.deposit?.available">
                            <button type="button" @click="arrangeDeposit()"
                                    class="mt-2 rounded-aj-sm bg-white px-3 py-2 text-xs font-extrabold text-aj-navy">
                                Borg regelen
                            </button>
                        </template>
                        <template x-if="! state.viewer?.deposit?.available">
                            {{-- Eerlijk: zonder betaalprovider is er geen reservering, dus ook geen knop die altijd faalt. --}}
                            <p class="mt-2 text-xs text-white/70">{{ __('auctions.deposit.provider_missing') }}</p>
                        </template>
                    </div>


                    {{-- Terug naar het overzicht, zonder iets te doen. --}}
                    <button type="button" @click="open = false"
                            class="mt-3 w-full text-center text-sm font-semibold text-white/80">
                        Annuleren
                    </button>

                    </div>
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
        <span class="font-bold text-white">Veiling:</span>
        de prijs loopt op, het hoogste bod bij sluiting wint.
        <a href="{{ route('assurance') }}" class="font-semibold text-white underline">Hoe werkt dit?</a>
    </p>
</div>
