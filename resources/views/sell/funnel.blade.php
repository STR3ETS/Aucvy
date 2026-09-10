@use('Illuminate\Support\Js')

<x-layouts.public title="Kavel plaatsen">
    <div x-data="funnel({{ Js::from($initial) }}, {{ $feePercentage }})" class="mx-auto max-w-5xl">

        <h1 class="text-3xl font-extrabold tracking-[-0.03em]">Kavel plaatsen</h1>
        <p class="mt-1 text-aj-muted">
            Geen account nodig - je ziet eerst wat we voor je maken. Pas bij het publiceren vragen we je in te loggen.
        </p>

        {{-- Stappenbalk (briefing 13.4): acht chips, klikbaar. --}}
        <nav aria-label="Stappen" class="chip-row mt-6 -mx-4 px-4 sm:mx-0 sm:px-0" style="display:flex;gap:8px;overflow-x:auto">
            <template x-for="(name, index) in steps" :key="index">
                <button type="button" @click="step = index"
                        class="btn"
                        :class="step === index ? 'btn-primary' : 'btn-ghost'"
                        style="height:40px;font-size:14px;padding:0 14px;flex:none"
                        :aria-current="step === index ? 'step' : null">
                    <span x-text="(index + 1) + ' · ' + name"></span>
                </button>
            </template>
        </nav>

        <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_260px] lg:items-start">
            <div class="space-y-5">

                {{-- 1 · Wat ------------------------------------------------- --}}
                <section x-show="step === 0" x-cloak aria-label="Wat wil je verkopen">
                    <div class="rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <h2 class="text-lg font-extrabold">Wat wil je verkopen?</h2>
                        <p class="mt-1 text-sm text-aj-muted">Kies wat het dichtst in de buurt komt - of vertel het in één zin.</p>

                        <div class="mt-4 grid gap-2 sm:grid-cols-3">
                            <template x-for="option in [{q: 1, label: 'Eén object'}, {q: 10, label: 'Meerdere objecten'}, {q: 100, label: 'Hele inventaris'}]" :key="option.q">
                                <button type="button" class="btn"
                                        :class="draft?.quantity === option.q ? 'btn-primary' : 'btn-ghost'"
                                        @click="set('quantity', option.q)">
                                    <span x-text="option.label"></span>
                                </button>
                            </template>
                        </div>

                        <label class="mt-4 block">
                            <span class="text-sm font-bold">In je eigen woorden</span>
                            <textarea rows="3" placeholder="Ik stop met mijn restaurant en wil alles verkopen."
                                      class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm"
                                      :value="draft?.description ?? ''"
                                      @input="set('description', $event.target.value)"></textarea>
                        </label>
                    </div>
                </section>

                {{-- 2 · Aanleveren ------------------------------------------ --}}
                <section x-show="step === 1" x-cloak aria-label="Foto's en documenten">
                    <div class="rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <h2 class="text-lg font-extrabold">Voeg toe wat je hebt</h2>
                        <p class="mt-1 text-sm text-aj-muted">
                            Een paar foto's vanaf je telefoon zijn genoeg. Documenten kunnen ook later.
                        </p>

                        <label class="mt-4 flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-aj-sm border-2 border-dashed border-aj-border-strong p-6 text-center text-sm">
                            <span class="font-bold text-aj-blue-text">Foto's kiezen</span>
                            <span class="text-aj-muted">JPG of PNG, maximaal 10 MB per stuk</span>
                            <input type="file" accept="image/*" multiple class="sr-only"
                                   @change="uploadPhotos([...$event.target.files]); $event.target.value = ''">
                        </label>

                        <p x-show="uploading" x-cloak class="mt-2 text-sm text-aj-muted">Bezig met uploaden…</p>

                        <ul class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-5" x-show="draft?.media.length" x-cloak>
                            <template x-for="item in draft?.media ?? []" :key="item.id">
                                <li class="relative">
                                    <img :src="item.url" alt="" class="aspect-square w-full rounded-aj-sm object-cover">
                                    <button type="button" @click="removePhoto(item)"
                                            class="absolute right-1 top-1 flex size-7 items-center justify-center rounded-aj-sm bg-aj-navy/80 text-white"
                                            aria-label="Foto verwijderen">
                                        <svg class="size-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                             stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                            <path d="m5 5 10 10M15 5 5 15"/>
                                        </svg>
                                    </button>
                                </li>
                            </template>
                        </ul>

                        <div class="mt-6 border-t border-aj-border pt-4">
                            <h3 class="text-sm font-extrabold">Documenten</h3>
                            <p class="mt-1 text-xs text-aj-muted">
                                Facturen en eigendomsbewijzen blijven privé en gaan nooit publiek op de kavelpagina.
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-2" x-data="{ kind: 'invoice' }">
                                <select x-model="kind"
                                        class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                                        style="min-height:40px" aria-label="Soort document">
                                    <option value="invoice">Factuur</option>
                                    <option value="warranty">Garantiebewijs</option>
                                    <option value="ownership">Eigendomsbewijs</option>
                                    <option value="manual">Handleiding</option>
                                    <option value="other">Overig</option>
                                </select>
                                <label class="btn btn-ghost" style="height:40px;font-size:14px">
                                    PDF kiezen
                                    <input type="file" accept="application/pdf" class="sr-only"
                                           @change="uploadDocument($event.target.files[0], kind); $event.target.value = ''">
                                </label>
                            </div>

                            <ul class="mt-3 space-y-1 text-sm" x-show="draft?.documents.length" x-cloak>
                                <template x-for="doc in draft?.documents ?? []" :key="doc.id">
                                    <li class="flex items-center gap-2">
                                        <span class="size-1.5 bg-aj-blue" aria-hidden="true"></span>
                                        <span x-text="doc.name"></span>
                                        <span class="text-xs text-aj-muted" x-text="'· ' + doc.kind"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </section>

                {{-- 3 · Analyse --------------------------------------------- --}}
                <section x-show="step === 2" x-cloak aria-label="Analyse">
                    <div class="rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <div class="flex items-center gap-3">
                            <x-ui.agent-avatar agent="ivy" class="size-10" />
                            <h2 class="text-lg font-extrabold">
                                Ivy-analyse
                                <span class="block text-xs font-normal text-aj-muted">AI-specialist</span>
                            </h2>
                        </div>

                        @if ($ivyEnabled)
                            <p class="mt-1 text-sm text-aj-muted">
                                Ivy bekijkt je foto's en omschrijving: ze herkent het object, schrijft een eerste
                                advertentie en schat de waarde. Alles wat ze invult pas je in de volgende stap aan -
                                en zelf invullen werkt net zo goed.
                            </p>

                            <button type="button" class="btn btn-primary mt-4"
                                    :disabled="ivy.running" @click="analyse()">
                                <span x-text="ivy.running ? 'Ivy kijkt mee…' : 'Laat Ivy meekijken'"></span>
                            </button>

                            {{-- Voortgang per taak, met het zekerheidslabel uit de
                                 ConfidencePolicy - nooit een kale modelbelofte (hfst. 8). --}}
                            <ul class="mt-4 space-y-2 text-sm" x-show="ivy.notes.length || ivy.running" x-cloak
                                aria-live="polite">
                                <template x-for="note in ivy.notes" :key="note.id">
                                    <li class="flex items-start gap-2">
                                        <img :src="'/images/agents/' + note.agent + '.jpg'" :alt="note.agent + ', AI-specialist'"
                                             class="mt-0.5 size-5 shrink-0 rounded-full object-cover" loading="lazy">
                                        <span>
                                            <span class="font-bold" x-text="ivyLabel(note.task)"></span>
                                            <span class="text-aj-muted" x-text="'- ' + ivyNote(note)"></span>
                                        </span>
                                    </li>
                                </template>
                                <li x-show="ivy.running" x-cloak class="text-aj-muted">
                                    <span x-text="(ivy.current ? ivyLabel(ivy.current) : 'Bezig') + '…'"></span>
                                </li>
                            </ul>
                        @else
                            {{-- Noodrem om (hfst. 8): geen knop die niets gaat
                                 doen, wel de eerlijke handmatige route. --}}
                            <p class="mt-1 text-sm text-aj-muted">
                                Ivy staat op dit moment uit. Je vult de advertentie zelf in bij de
                                volgende stap - dat werkt net zo goed.
                            </p>
                        @endif

                        <button type="button" class="btn {{ $ivyEnabled ? 'btn-ghost' : 'btn-primary' }} mt-4"
                                @click="step = 3">
                            Door naar de advertentie
                        </button>
                    </div>
                </section>

                {{-- 4 · Advertentie ----------------------------------------- --}}
                <section x-show="step === 3" x-cloak aria-label="Advertentie">
                    <div class="space-y-4 rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <h2 class="text-lg font-extrabold">De advertentie</h2>

                        <label class="block">
                            <span class="text-sm font-bold">Titel</span>
                            <input type="text" maxlength="150"
                                   placeholder="Complete designkeuken met Siemens-apparatuur"
                                   class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                   style="min-height:var(--cta-h)"
                                   :value="draft?.title ?? ''"
                                   @input="set('title', $event.target.value)">
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold">Omschrijving</span>
                            <span class="block text-xs text-aj-muted">Eerlijk over gebreken - dat verkoopt aantoonbaar beter.</span>
                            <textarea rows="6"
                                      class="mt-1 w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm"
                                      :value="draft?.description ?? ''"
                                      @input="set('description', $event.target.value)"></textarea>
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-bold">Categorie</span>
                                <select class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                        style="min-height:var(--cta-h)"
                                        :value="draft?.category_id ?? ''"
                                        @change="set('category_id', $event.target.value ? Number($event.target.value) : null)">
                                    <option value="">Kies een categorie</option>
                                    @foreach ($categories as $root)
                                        <optgroup label="{{ $root->name }}">
                                            @foreach ($root->children as $child)
                                                <option value="{{ $child->id }}">{{ $child->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold">Conditie</span>
                                <select class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                        style="min-height:var(--cta-h)"
                                        :value="draft?.condition ?? ''"
                                        @change="set('condition', $event.target.value || null)">
                                    <option value="">Kies de conditie</option>
                                    @foreach (\App\Enums\LotCondition::cases() as $condition)
                                        <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold">Merk</span>
                                <input type="text" maxlength="80"
                                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="draft?.brand ?? ''"
                                       @input="set('brand', $event.target.value || null)">
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold">Bouwjaar</span>
                                <input type="number" min="1900" max="{{ now()->year }}"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="draft?.year ?? ''"
                                       @input="set('year', $event.target.value ? Number($event.target.value) : null)">
                            </label>
                        </div>
                    </div>
                </section>

                {{-- 5 · Vorm ------------------------------------------------ --}}
                <section x-show="step === 4" x-cloak aria-label="Verkoopvorm">
                    <div class="space-y-4 rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <h2 class="text-lg font-extrabold">Hoe wil je verkopen?</h2>

                        <div class="grid gap-2 sm:grid-cols-3">
                            <template x-for="form in [{v: 'auction', label: 'Veiling'}, {v: 'dutch', label: 'Afslag'}, {v: 'buy_now', label: 'Koop direct'}]" :key="form.v">
                                <button type="button" class="btn"
                                        :class="meta.sale_type === form.v ? 'btn-primary' : 'btn-ghost'"
                                        @click="set('sale_type', form.v)">
                                    <span x-text="form.label"></span>
                                </button>
                            </template>
                        </div>

                        {{-- Veiling --}}
                        <div x-show="meta.sale_type === 'auction'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-bold">Startprijs (€)</span>
                                <input type="text" inputmode="decimal"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="euro(meta.start_price_cents)"
                                       @change="setEuro('start_price_cents', $event.target.value)">
                            </label>
                            <label class="block">
                                <span class="text-sm font-bold">Reserveprijs (€, optioneel)</span>
                                <span class="block text-xs text-aj-muted">Nooit publiek - alleen óf hij gehaald is.</span>
                                <input type="text" inputmode="decimal"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="euro(meta.reserve_price_cents)"
                                       @change="setEuro('reserve_price_cents', $event.target.value)">
                            </label>
                        </div>

                        {{-- Afslag --}}
                        <div x-show="meta.sale_type === 'dutch'" x-cloak class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="text-sm font-bold">Startprijs (€)</span>
                                <input type="text" inputmode="decimal"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="euro(meta.dutch_start_cents)"
                                       @change="setEuro('dutch_start_cents', $event.target.value)">
                            </label>
                            <label class="block">
                                <span class="text-sm font-bold">Bodemprijs (€)</span>
                                <span class="block text-xs text-aj-muted">Daaronder zakt hij nooit.</span>
                                <input type="text" inputmode="decimal"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="euro(meta.dutch_floor_cents)"
                                       @change="setEuro('dutch_floor_cents', $event.target.value)">
                            </label>
                            <label class="block">
                                <span class="text-sm font-bold">Stap per daling (€)</span>
                                <input type="text" inputmode="decimal"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="euro(meta.dutch_step_cents)"
                                       @change="setEuro('dutch_step_cents', $event.target.value)">
                            </label>
                            <label class="block">
                                <span class="text-sm font-bold">Interval (seconden)</span>
                                <input type="number" min="5" max="600" placeholder="20"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="meta.dutch_interval_s ?? ''"
                                       @change="set('dutch_interval_s', Number($event.target.value) || null)">
                            </label>
                        </div>

                        {{-- Koop direct --}}
                        <div x-show="meta.sale_type === 'buy_now'" x-cloak>
                            <label class="block sm:max-w-xs">
                                <span class="text-sm font-bold">Vaste prijs (€)</span>
                                <input type="text" inputmode="decimal"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="euro(meta.buy_now_price_cents)"
                                       @change="setEuro('buy_now_price_cents', $event.target.value)">
                            </label>
                        </div>

                        <label class="block sm:max-w-xs" x-show="meta.sale_type" x-cloak>
                            <span class="text-sm font-bold">Looptijd</span>
                            <select class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                    style="min-height:var(--cta-h)"
                                    :value="meta.duration_days ?? 7"
                                    @change="set('duration_days', Number($event.target.value))">
                                <option value="3">3 dagen</option>
                                <option value="7">7 dagen</option>
                                <option value="14">14 dagen</option>
                            </select>
                        </label>
                    </div>
                </section>

                {{-- 6 · Promotie -------------------------------------------- --}}
                <section x-show="step === 5" x-cloak aria-label="Promotie">
                    <div class="rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <h2 class="text-lg font-extrabold">Waar wil je publiceren?</h2>
                        <p class="mt-1 text-sm text-aj-muted">
                            Je kavel komt op Aucvy, in de zoekresultaten en bij de categorie.
                            {{-- Campagnebeheer raakt promotiebudget en valt buiten de MVP
                                 (beslissing hfst. 2b). Eerlijk benoemen, niet nabouwen. --}}
                            Extra kanalen - Marktplaats, sociale media - komen later; die vragen een promotiebudget.
                        </p>
                        <button type="button" class="btn btn-primary mt-4" @click="step = 6">
                            Door naar de kosten
                        </button>
                    </div>
                </section>

                {{-- 7 · Kosten ---------------------------------------------- --}}
                <section x-show="step === 6" x-cloak aria-label="Kosten">
                    <div class="rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <h2 class="text-lg font-extrabold">Wat kost het?</h2>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between gap-4 border-b border-aj-border pb-2">
                                <dt class="text-aj-muted">Plaatsen</dt>
                                <dd class="font-bold">Gratis</dd>
                            </div>
                            <div class="flex justify-between gap-4 border-b border-aj-border pb-2">
                                <dt class="text-aj-muted">Succesfee - alleen bij verkoop</dt>
                                <dd class="tabular font-bold" x-text="fee.toString().replace('.', ',') + '%'"></dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-aj-muted">Opgeld voor de koper</dt>
                                <dd class="font-bold">€ 0</dd>
                            </div>
                        </dl>

                        <div class="mt-6 space-y-2 border-t border-aj-border pt-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" class="rounded"
                                       :checked="draft?.viewing_available"
                                       @change="set('viewing_available', $event.target.checked)">
                                Bezichtiging mogelijk (kijkdag)
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" class="rounded"
                                       :checked="draft?.pickup_only"
                                       @change="set('pickup_only', $event.target.checked)">
                                Alleen zelf ophalen
                            </label>
                            <label class="block sm:max-w-xs" x-show="! draft?.pickup_only" x-cloak>
                                <span class="text-sm font-bold">Transport vanaf (€)</span>
                                <input type="text" inputmode="decimal"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="euro(draft?.transport_from_cents)"
                                       @change="setEuro('transport_from_cents', $event.target.value)">
                            </label>
                        </div>
                    </div>
                </section>

                {{-- 8 · Live ------------------------------------------------ --}}
                <section x-show="step === 7" x-cloak aria-label="Controleren en publiceren">
                    <div class="rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <h2 class="text-lg font-extrabold">Klaar om live te gaan?</h2>

                        <dl class="mt-4 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Titel</dt><dd class="font-bold" x-text="draft?.title ?? '-'"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Verkoopvorm</dt><dd class="font-bold" x-text="({auction: 'Veiling', dutch: 'Afslag', buy_now: 'Koop direct'})[meta.sale_type] ?? '-'"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Foto's</dt><dd class="tabular font-bold" x-text="draft?.media.length ?? 0"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Documenten</dt><dd class="tabular font-bold" x-text="draft?.documents.length ?? 0"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Compleetheid</dt><dd class="tabular font-bold" x-text="completeness.score + '%'"></dd></div>
                        </dl>

                        <template x-if="! loggedIn">
                            <p class="mt-4 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                                Om te publiceren log je eerst in - je concept blijft bewaard en gaat mee.
                            </p>
                        </template>

                        <button type="button" class="btn btn-primary mt-4" @click="publish()">
                            <span x-text="loggedIn ? 'Publiceer deze kavel' : 'Inloggen en publiceren'"></span>
                        </button>
                    </div>
                </section>

                <p x-show="error" x-cloak role="alert"
                   class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error"
                   x-text="error"></p>

                {{-- Navigatie: terug werkt, elke stap is omkeerbaar. --}}
                <div class="flex items-center gap-3">
                    <button type="button" class="btn btn-ghost" x-show="step > 0" @click="step--">
                        <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M16 10H4m5-5-5 5 5 5"/>
                        </svg>
                        Terug
                    </button>
                    <span class="text-sm text-aj-muted" x-show="saving" x-cloak>Opslaan…</span>
                    <span class="text-sm text-aj-success-text" x-show="saved" x-cloak>Opgeslagen</span>
                    <button type="button" class="btn btn-primary ml-auto" x-show="step < 7" @click="step++">Volgende</button>
                </div>
            </div>

            {{-- Zijkolom: de compleetheidsmeter (briefing 13.4). --}}
            <aside class="rounded-aj-s border border-aj-border bg-aj-surface p-5 lg:sticky lg:top-24">
                <h2 class="text-sm font-extrabold">Compleetheid</h2>
                <div class="mt-2 h-2 overflow-hidden rounded-aj-sm bg-aj-bg" role="progressbar"
                     :aria-valuenow="completeness.score" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full bg-aj-blue transition-all duration-500"
                         :style="'width:' + completeness.score + '%'"></div>
                </div>
                <p class="tabular mt-2 text-sm font-bold" x-text="completeness.score + '%'"></p>
                <p class="mt-1 text-xs text-aj-muted" x-text="completeness.hint"></p>

                <div class="mt-4 border-t border-aj-border pt-3" x-show="completeness.missing.length" x-cloak>
                    <h3 class="text-xs font-extrabold text-aj-soft">Wat nog ontbreekt</h3>
                    <ul class="mt-2 space-y-1 text-xs text-aj-muted">
                        <template x-for="key in completeness.missing" :key="key">
                            <li x-text="({{ Js::from(__('funnel.missing')) }})[key] ?? key"></li>
                        </template>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.public>
