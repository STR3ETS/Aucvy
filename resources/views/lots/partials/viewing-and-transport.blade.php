@use('App\Support\Money\Money')

{{--
    Bezichtiging plannen en de transportindicatie (hfst. 7), als twee kaarten
    in de zijkolom van de kavelpagina. Uit lots/show gehaald bij het ontwerp
    van november; de logica is ongewijzigd.
--}}
                    {{-- Bezichtiging: moment voorstellen; het adres volgt per
                         mail, pas na bevestiging van de verkoper (hfst. 7). --}}
                    @if ($lot->viewing_available)
                        <div id="bezichtiging" class="d-side-card">
                            <p class="d-h3" style="font-size:15px;font-weight:700">Bezichtiging plannen</p>

                            @guest
                                <p class="d-note">
                                    Kijken vóór je biedt kan. Log in om een moment voor te stellen.
                                </p>
                                <a href="{{ route('login') }}" class="d-btn d-btn-secondary d-btn-wide d-btn-sq"
                                   style="height:40px;font-size:13px">Inloggen</a>
                            @endguest

                            @auth
                                @if ($lot->seller_id === auth()->id())
                                    <p class="d-note">
                                        Kopers stellen hier een kijkmoment voor; jij bevestigt per mail.
                                    </p>
                                    <a href="{{ route('dashboard.lots.edit', $lot) }}" class="d-btn d-btn-secondary d-btn-wide d-btn-sq" style="height:40px;font-size:13px">Kavel bewerken</a>
                                @elseif ($viewerViewing !== null)
                                    <p class="mt-1 flex items-start gap-2 text-sm {{ $viewerViewing->mayReceiveAddress() ? 'text-aj-success-text' : 'text-aj-muted' }}">
                                        <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
                                        </svg>
                                        @if ($viewerViewing->mayReceiveAddress())
                                            Bevestigd voor {{ $viewerViewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('dddd D MMMM, HH:mm') }}.
                                            Het adres staat in je mail.
                                        @else
                                            Aanvraag verstuurd voor {{ $viewerViewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('dddd D MMMM, HH:mm') }}.
                                            Je ontvangt het adres per mail zodra de verkoper bevestigt.
                                        @endif
                                    </p>
                                @else
                                    <div x-data="{
                                            slot: '',
                                            note: '',
                                            busy: false,
                                            done: null,
                                            error: null,
                                            async submit() {
                                                this.error = null;
                                                if (! this.slot) { this.error = 'Kies eerst een moment.'; return; }
                                                this.busy = true;
                                                try {
                                                    const response = await fetch('/api/lots/{{ $lot->id }}/viewings', {
                                                        method: 'POST',
                                                        headers: {
                                                            'Content-Type': 'application/json',
                                                            'Accept': 'application/json',
                                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                                        },
                                                        body: JSON.stringify({ slot_at: this.slot, note: this.note || null }),
                                                    });
                                                    const data = await response.json().catch(() => ({}));
                                                    if (response.ok) {
                                                        this.done = data.viewing;
                                                    } else {
                                                        this.error = data?.message ?? 'Dat lukte niet - probeer het opnieuw.';
                                                    }
                                                } catch (error) {
                                                    this.error = 'Geen verbinding - probeer het zo opnieuw.';
                                                }
                                                this.busy = false;
                                            },
                                         }">
                                        <template x-if="! done">
                                            <div>
                                                <p class="d-note">
                                                    Stel een moment voor. Het adres ontvang je per mail zodra de verkoper bevestigt.
                                                </p>
                                                <label class="mt-3 block">
                                                    <span class="sr-only">Moment</span>
                                                    <input type="datetime-local" x-model="slot"
                                                           min="{{ now(config('aucvy.display_timezone'))->addHours(2)->format('Y-m-d\TH:i') }}"
                                                           class="d-input"
                                                           style="min-height: 44px">
                                                </label>
                                                <label class="mt-2 block">
                                                    <span class="sr-only">Bericht voor de verkoper</span>
                                                    <input type="text" x-model="note" maxlength="500"
                                                           placeholder="Bericht voor de verkoper (niet verplicht)"
                                                           class="d-input"
                                                           style="min-height: 44px">
                                                </label>
                                                <button type="button" class="d-btn d-btn-wide d-btn-sq"
                                                        style="height:44px;font-size:14px"
                                                        :disabled="busy" @click="submit()">
                                                    <span x-show="! busy">Bezichtiging aanvragen</span>
                                                    <span x-show="busy" x-cloak>Versturen…</span>
                                                </button>
                                                <p x-show="error" x-cloak role="alert"
                                                   class="mt-2 text-xs font-semibold text-aj-error" x-text="error"></p>
                                            </div>
                                        </template>
                                        <template x-if="done">
                                            <p class="d-note">
                                                Aanvraag verstuurd voor <span class="font-semibold" x-text="done.slot_display"></span>.
                                                Je ontvangt het adres per mail zodra de verkoper bevestigt.
                                            </p>
                                        </template>
                                    </div>
                                @endif
                            @endauth
                        </div>
                    @endif

                    {{-- Transportindicatie (hfst. 7): postcode in, prijs uit.
                         Een indicatie met houdbaarheid, geen offerte. --}}
                    @if ($lot->transport_from_cents !== null)
                        <div class="d-side-card"
                             x-data="{
                                postcode: '',
                                busy: false,
                                quote: null,
                                error: null,
                                async calc() {
                                    this.error = null;
                                    this.busy = true;
                                    try {
                                        const response = await fetch('/api/shipping/quote', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'Accept': 'application/json',
                                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                            },
                                            body: JSON.stringify({ lot_id: {{ $lot->id }}, postcode: this.postcode }),
                                        });
                                        const data = await response.json().catch(() => ({}));
                                        if (response.ok) {
                                            this.quote = data.quote;
                                            // De kostenopbouw hieronder springt mee
                                            // met jouw transportbedrag.
                                            if (data.costs) this.$dispatch('shipping-quoted', data.costs);
                                        } else {
                                            this.quote = null;
                                            this.error = data?.message ?? 'Dat lukte niet - probeer het opnieuw.';
                                        }
                                    } catch (error) {
                                        this.quote = null;
                                        this.error = 'Geen verbinding - probeer het zo opnieuw.';
                                    }
                                    this.busy = false;
                                },
                             }">
                            <p class="d-h3" style="font-size:15px;font-weight:700">Transportindicatie</p>
                            <p class="d-note">
                                Vanaf {{ Money::format($lot->transport_from_cents) }} - vul je postcode in
                                voor een indicatie naar jouw adres.
                            </p>
                            <div class="mt-3 flex gap-2">
                                <label class="sr-only" for="shipping-postcode">Postcode</label>
                                <input id="shipping-postcode" type="text" inputmode="text" autocomplete="postal-code"
                                       placeholder="1234 AB" maxlength="7"
                                       x-model="postcode" @keydown.enter.prevent="calc()"
                                       class="d-input tabular"
                                       style="min-height: 44px">
                                <button type="button" class="d-btn d-btn-secondary d-btn-sm" style="height:44px;font-size:13px"
                                        :disabled="busy" @click="calc()">
                                    <span x-show="! busy">Bereken</span>
                                    <span x-show="busy" x-cloak>Even rekenen…</span>
                                </button>
                            </div>
                            <p x-show="quote" x-cloak class="mt-2 text-sm" aria-live="polite">
                                <span class="tabular font-extrabold" x-text="quote?.amount_formatted"></span>
                                <span class="block text-xs text-aj-muted">
                                    Indicatie, 24 uur geldig. Het definitieve bedrag staat in de kostenopbouw.
                                </span>
                            </p>
                            <p x-show="error" x-cloak role="alert"
                               class="mt-2 text-xs font-semibold text-aj-error" x-text="error"></p>
                        </div>
                    @endif

