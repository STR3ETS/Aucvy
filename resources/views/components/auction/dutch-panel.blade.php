{{--
    Afslag: de prijs zakt, jij beslist wanneer je toeslaat.

    Geen schuifknop om af te mijnen - een knop plus een expliciete bevestiging.
    Per ongeluk € 13.750 uitgeven mag niet één tik kosten (mobiele buildlijst).
--}}
<div class="panel-hero rounded-aj-s p-5" x-cloak
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

    {{-- Het stippenveld uit de hero, achter de inhoud. --}}
    <canvas class="dots" x-data="dotField" aria-hidden="true"></canvas>

    <p class="text-xs text-white/70">Huidige prijs</p>
    <p class="tabular text-3xl font-extrabold" :class="priceDropped && 'price-drop'"
       x-text="formatCents(state.price_cents)"></p>

    <template x-if="dutchDropIn !== null">
        <div class="mt-2">
            <p class="text-xs text-white/70">
                Daalt over <span class="tabular font-extrabold text-white" x-text="dutchDropIn"></span>s
                met <span class="tabular font-semibold text-white/90" x-text="formatCents(state.dutch.step_cents)"></span>
            </p>
            {{-- Het leeglopende balkje: je ziet de stap aankomen. --}}
            <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-white/15" aria-hidden="true">
                <div class="h-full rounded-full bg-white/80"
                     style="transition:width 1s linear"
                     :style="`width:${Math.max(0, Math.min(100, dutchDropIn / state.dutch.interval_seconds * 100))}%`"></div>
            </div>
        </div>
    </template>

    <template x-if="state.dutch.floor_reached">
        {{-- Een teller die nergens heen loopt is erger dan geen teller. --}}
        <p class="mt-1 text-xs text-amber-200">
            De bodemprijs is bereikt. Lager wordt hij niet.
        </p>
    </template>

    <template x-if="(state.watching_now ?? 0) > 0">
        <p class="mt-1 flex items-center gap-1 text-xs text-white/70">
            <svg class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10Z"/><circle cx="10" cy="10" r="2.4"/>
            </svg>
            <span x-text="state.watching_now"></span> kijken nu - wie het eerst toeslaat koopt
        </p>
    </template>

    <div class="mt-4">
        @guest
            <a href="{{ route('login') }}"
               class="flex w-full items-center justify-center rounded-aj-sm bg-white px-5 text-sm font-semibold text-aj-navy"
               style="min-height: var(--spacing-cta)">
                Inloggen om af te mijnen
            </a>
        @endguest

        @auth
            <template x-if="state.viewer?.is_seller">
                <p class="rounded-aj-sm bg-white/10 px-3 py-3 text-sm">
                    Dit is je eigen kavel.
                </p>
            </template>

            <template x-if="state.status === 'closed'">
                <div>
                    {{-- Wie hem zelf afmijnde, krijgt geen mededeling maar
                         een moment - plus de route naar de betaling. --}}
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
                                Jij was de snelste. Rond de betaling af, dan komt hij jouw kant op.
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
                        <p class="rounded-aj-sm bg-white/10 px-3 py-3 text-sm">
                            Deze kavel is al afgemijnd.
                        </p>
                    </template>
                </div>
            </template>

            <template x-if="! state.viewer?.can_bid && ! state.viewer?.is_seller && state.status !== 'closed'">
                <p class="rounded-aj-sm bg-white/10 px-3 py-3 text-sm">
                    Rond eerst je verificatie af - dat duurt twee minuten.
                </p>
            </template>

            <template x-if="state.viewer?.can_bid && ! state.viewer?.is_seller && state.status !== 'closed'">
                <div>
                    {{-- De borg vóóraf, niet pas bij het afmijnen (hfst. 9). --}}
                    <template x-if="needsDeposit">
                        <div class="rounded-aj-sm bg-white/12 p-3 text-sm">
                            <p>
                                Boven <span class="tabular" x-text="formatCents(state.viewer?.deposit?.threshold_cents)"></span>
                                vragen we eerst een borg van
                                <span class="tabular" x-text="formatCents(state.viewer?.deposit?.threshold_cents)"></span> -
                                die je terugkrijgt als je niet koopt.
                            </p>
                            <button type="button" @click="arrangeDeposit()"
                                    class="mt-2 rounded-aj-sm bg-white px-3 py-2 text-xs font-extrabold text-aj-navy">
                                Borg regelen
                            </button>
                            <p class="mt-2 text-xs text-white/60">
                                Daarna kun je direct afmijnen - of wacht tot de prijs onder de drempel zakt.
                            </p>
                        </div>
                    </template>

                    <template x-if="! needsDeposit && ! confirming">
                        <button type="button" :disabled="isBlocked" @click="confirming = true"
                                class="flex w-full items-center justify-center rounded-aj-sm bg-white px-5
                                       text-sm font-semibold text-aj-navy disabled:opacity-60"
                                style="min-height: var(--spacing-cta)">
                            Mijn af voor <span class="tabular ml-1" x-text="formatCents(state.price_cents)"></span>
                        </button>
                    </template>

                    <template x-if="! needsDeposit && confirming">
                        <div class="rounded-aj-sm bg-white/10 p-3">
                            <p class="text-sm">
                                Je koopt deze kavel voor
                                <span class="tabular font-semibold" x-text="formatCents(state.price_cents)"></span>.
                                De veiling sluit daarmee direct.
                            </p>
                            <p class="mt-1 text-xs text-white/60">
                                Zakt de prijs precies nu, dan reken je af tegen de nieuwe, lagere prijs.
                            </p>
                            <div class="mt-3 flex gap-2">
                                <button type="button" @click="acceptDutch()"
                                        class="flex flex-1 items-center justify-center rounded-aj-sm bg-white px-4
                                               text-sm font-semibold text-aj-navy"
                                        style="min-height: var(--spacing-cta)">
                                    Ja, afmijnen
                                </button>
                                <button type="button" @click="confirming = false"
                                        class="flex items-center justify-center rounded-aj-sm bg-white/10 px-4 text-sm"
                                        style="min-height: var(--spacing-cta)">
                                    Annuleren
                                </button>
                            </div>
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
        <span class="font-bold text-white">Afslag:</span>
        de prijs zakt vanzelf, wie het eerst toeslaat koopt.
        <a href="{{ route('assurance') }}" class="font-semibold text-white underline">Hoe werkt dit?</a>
    </p>
</div>
