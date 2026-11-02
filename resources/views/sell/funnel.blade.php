@use('Illuminate\Support\Js')

<x-layouts.public title="Kavel plaatsen" eyebrow="Verkopen"
    intro="Geen account nodig: je ziet eerst wat we voor je maken. Pas bij het publiceren vragen we je in te loggen.">
    <div x-data="funnel({{ Js::from($initial) }}, {{ $feePercentage }}, {{ Js::from($schemas) }})" class="mx-auto max-w-5xl">

        {{-- Conceptoverzicht (briefing sectie 5): hervatten of nieuw beginnen. --}}
        @if ($initial['draft'] !== null || $drafts->isNotEmpty())
            <div class="draft-bar">
                @if ($initial['draft'] !== null)
                    <span>Je werkt aan concept <b class="tabular">#{{ $initial['draft']['public_id'] }}</b>{{ $initial['draft']['title'] ? ': '.$initial['draft']['title'] : '' }}</span>
                @endif
                @if ($drafts->isNotEmpty())
                    <details class="draft-others">
                        <summary>{{ $drafts->count() === 1 ? 'Nog één ander concept' : 'Nog '.$drafts->count().' andere concepten' }}</summary>
                        <ul>
                            @foreach ($drafts as $other)
                                <li>
                                    <a href="{{ route('sell', ['concept' => $other->id]) }}">{{ $other->title ?: 'Zonder titel' }}</a>
                                    <span class="tabular">#{{ $other->public_id }} · {{ $other->completeness_score }}% · {{ $other->updated_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
                @auth
                    <a href="{{ route('sell', ['nieuw' => 1]) }}" class="draft-new">Nieuw concept</a>
                @endauth
            </div>
        @endif

        {{-- Stappenbalk (briefing 13.4): acht chips, klikbaar. --}}
        <nav aria-label="Stappen" class="chip-row -mx-4 px-4 sm:mx-0 sm:px-0" style="display:flex;gap:8px;overflow-x:auto">
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
                        {{-- Dicteren via de spraakherkenning van de browser (briefing stap 1: spraak). --}}
                        <button type="button" class="btn btn-ghost mt-2" style="height:36px;font-size:13px" x-show="canDictate" x-cloak
                                :class="dictating === 'description' && 'is-on'" @click="dictate('description')">
                            <x-v4.icon name="mic" :size="15" stroke="2.2" />
                            <span x-text="dictating === 'description' ? 'Stop met dicteren' : 'Dicteren'"></span>
                        </button>
                    </div>
                </section>

                {{-- 2 · Aanleveren ------------------------------------------ --}}
                <section x-show="step === 1" x-cloak aria-label="Foto's en documenten">
                    <div class="rounded-aj-s border border-aj-border bg-aj-surface p-6">
                        <h2 class="text-lg font-extrabold">Voeg toe wat je hebt</h2>
                        <p class="mt-1 text-sm text-aj-muted">
                            Een paar foto's vanaf je telefoon zijn genoeg. Documenten kunnen ook later.
                        </p>

                        {{-- Slepen, kiezen of fotograferen; voortgang per bestand (briefing sectie 5, stap 1). --}}
                        <div class="dropzone mt-4" :class="dragOver && 'is-over'"
                             @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false"
                             @drop.prevent="uploadPhotos([...$event.dataTransfer.files])">
                            <x-v4.icon name="upload" :size="22" stroke="2" />
                            <p><b>Sleep je foto's hierheen</b> of kies ze hieronder. JPG, PNG of WebP, maximaal 10 MB per stuk.</p>
                            <div class="dropzone-actions">
                                <label class="btn btn-primary" style="height:40px;font-size:13.5px">
                                    Foto's kiezen
                                    <input type="file" accept="image/*" multiple class="sr-only"
                                           @change="uploadPhotos([...$event.target.files]); $event.target.value = ''">
                                </label>
                                <label class="btn btn-ghost" style="height:40px;font-size:13.5px">
                                    <x-v4.icon name="camera" :size="16" stroke="2.2" /> Foto maken
                                    <input type="file" accept="image/*" capture="environment" class="sr-only"
                                           @change="uploadPhotos([...$event.target.files]); $event.target.value = ''">
                                </label>
                                {{-- Typeplaatje of barcode lezen met de barcodelezer van de browser (briefing stap 1). --}}
                                <label class="btn btn-ghost" style="height:40px;font-size:13.5px" x-show="canScan" x-cloak>
                                    <x-v4.icon name="scan-search" :size="16" stroke="2.2" /> Label of barcode scannen
                                    <input type="file" accept="image/*" capture="environment" class="sr-only"
                                           @change="scanBarcode($event.target.files[0]); $event.target.value = ''">
                                </label>
                            </div>
                            <p class="mt-2 text-xs text-aj-muted" x-show="scan.busy" x-cloak>Barcode zoeken…</p>
                            <p class="mt-2 text-xs text-aj-success-text" x-show="scan.result" x-cloak>Barcode <b x-text="scan.result"></b> gevonden en bij de kenmerken gezet.</p>
                            <p class="mt-2 text-xs text-aj-error" x-show="scan.error" x-text="scan.error" x-cloak></p>
                        </div>

                        <ul class="upload-list" x-show="uploads.length" x-cloak>
                            <template x-for="(upload, index) in uploads" :key="index">
                                <li :class="upload.error ? 'is-error' : (upload.pct === 100 ? 'is-done' : '')">
                                    <span class="name" x-text="upload.name"></span>
                                    <span class="bar" x-show="! upload.error"><i :style="'width:' + upload.pct + '%'"></i></span>
                                    <span class="msg" x-text="upload.error ?? (upload.pct === 100 ? 'Klaar' : upload.pct + '%')"></span>
                                    <button type="button" x-show="upload.error" @click="uploads.splice(index, 1)" aria-label="Melding sluiten"><x-v4.icon name="x" :size="14" stroke="2.2" /></button>
                                </li>
                            </template>
                        </ul>

                        <ul class="photo-grid mt-4" x-show="draft?.media.length" x-cloak>
                            <template x-for="(item, index) in draft?.media ?? []" :key="item.id">
                                <li class="photo" :class="index === 0 && 'is-cover'">
                                    <img :src="item.url" alt="">
                                    <span class="cover-tag" x-show="index === 0">Omslag</span>
                                    <button type="button" class="photo-cover" x-show="index !== 0" @click="setCover(item)">Als omslag</button>
                                    <button type="button" class="photo-remove" @click="removePhoto(item)" aria-label="Foto verwijderen">
                                        <x-v4.icon name="x" :size="14" stroke="2.4" />
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <p class="mt-2 text-xs text-aj-muted" x-show="draft?.media.length" x-cloak>De omslag is wat kopers in de lijst zien. Kies een andere foto met "Als omslag".</p>

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

                        {{-- Garantie (publicatiecriterium, besluit Raph 2 november 2026). --}}
                        <div class="mt-5 grid gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm sm:grid-cols-2">
                            <label class="grid gap-1">
                                <span class="font-bold">Garantie</span>
                                <select class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3" style="min-height:40px"
                                        :value="meta.warranty ?? ''" @change="set('warranty', $event.target.value || null)">
                                    <option value="">Kies...</option>
                                    @foreach (config('listings.warranty_options') as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="grid gap-1" x-show="meta.warranty && meta.warranty !== 'none'" x-cloak>
                                <span class="font-bold">Geldig tot</span>
                                <input type="date" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3" style="min-height:40px"
                                       :value="meta.warranty_until ?? ''" @change="set('warranty_until', $event.target.value || null)">
                            </label>
                        </div>

                        {{-- De echtheid van documenten aanvinken (besluit Raph): alleen als er documenten zijn. --}}
                        <label class="mt-3 flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm" x-show="(draft?.documents?.length ?? 0) > 0" x-cloak>
                            <input type="checkbox" class="mt-0.5 size-4"
                                   :checked="meta.documents_authentic === true"
                                   @change="set('documents_authentic', $event.target.checked)">
                            <span>
                                <span class="font-bold">De documenten die ik toevoeg zijn echt en volledig.</span>
                                <span class="block text-xs text-aj-muted">Nodig om te publiceren als je documenten toevoegt. Een vals document leidt tot verwijdering van de kavel en blokkade van het account.</span>
                            </span>
                        </label>

                        {{-- Eigendom (preflight, briefing sectie 5): verklaring of bewijs. --}}
                        <label class="mt-5 flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                            <input type="checkbox" class="mt-0.5 size-4"
                                   :checked="meta.ownership_declared === true"
                                   @change="set('ownership_declared', $event.target.checked)">
                            <span>
                                <span class="font-bold">Ik ben eigenaar van dit object, of gemachtigd om het te verkopen.</span>
                                <span class="block text-xs text-aj-muted">Nodig om te publiceren. Een eigendomsbewijs of factuur als document maakt je kavel sterker en geldt ook als verklaring.</span>
                            </span>
                        </label>
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

                            {{-- Meer aangeleverd = scherpere schatting: dat
                                 moment benoemen we expliciet (preview v4). --}}
                            <p x-show="ivy.sharpened" x-cloak
                               class="mt-3 flex items-center gap-1.5 rounded-aj-sm bg-aj-success/10 px-3 py-2 text-sm font-semibold text-aj-success-text">
                                <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
                                </svg>
                                <span>Je waarde-indicatie werd scherper:
                                    <span class="tabular" x-text="'€ ' + euro(draft?.value_min_cents) + ' – € ' + euro(draft?.value_max_cents)"></span>
                                </span>
                            </p>
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
                            <x-funnel.provenance field="title" />
                        </label>

                        <label class="block">
                            <span class="text-sm font-bold">Omschrijving</span>
                            <span class="block text-xs text-aj-muted">Eerlijk over gebreken - dat verkoopt aantoonbaar beter.</span>
                            <textarea rows="6"
                                      class="mt-1 w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm"
                                      :value="draft?.description ?? ''"
                                      @input="set('description', $event.target.value)"></textarea>
                            <x-funnel.provenance field="description" />
                            <button type="button" class="btn btn-ghost mt-2" style="height:36px;font-size:13px" x-show="canDictate" x-cloak
                                    :class="dictating === 'description' && 'is-on'" @click="dictate('description')">
                                <x-v4.icon name="mic" :size="15" stroke="2.2" />
                                <span x-text="dictating === 'description' ? 'Stop met dicteren' : 'Dicteren'"></span>
                            </button>
                        </label>

                        <p class="rounded-aj-sm border border-aj-warn/40 bg-aj-warn/10 p-3 text-sm" x-show="unconfirmed.length" x-cloak>
                            Ivy heeft velden ingevuld waar ze niet zeker van is. Kijk ze na: corrigeer wat niet klopt, of
                            <button type="button" class="font-bold underline" @click="confirmAll()">bevestig alles in één keer</button>.
                        </p>

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
                                <x-funnel.provenance field="category_id" />
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
                                <x-funnel.provenance field="condition" />
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold">Merk</span>
                                <input type="text" maxlength="80"
                                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="draft?.brand ?? ''"
                                       @input="set('brand', $event.target.value || null)">
                                <x-funnel.provenance field="brand" />
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold">Model of type</span>
                                <input type="text" maxlength="80"
                                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="draft?.model ?? ''"
                                       @input="set('model', $event.target.value || null)">
                                <x-funnel.provenance field="model" />
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold">Bouwjaar</span>
                                <input type="number" min="1900" max="{{ now()->year }}"
                                       class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                       style="min-height:var(--cta-h)"
                                       :value="draft?.year ?? ''"
                                       @input="set('year', $event.target.value ? Number($event.target.value) : null)">
                                <x-funnel.provenance field="year" />
                            </label>
                        </div>

                        {{-- Kenmerken: de velden uit het schema van de categorie, plus wat
                             Ivy of jijzelf er los bij zet (briefing sectie 5, stap 4). --}}
                        <div class="specs">
                            <h3 class="text-sm font-extrabold">Kenmerken</h3>
                            <p class="text-xs text-aj-muted" x-show="! draft?.category_id">Kies eerst een categorie; dan weet Ivy welke kenmerken kopers verwachten.</p>
                            <div class="spec-grid">
                                <template x-for="field in schemaFields" :key="field.key">
                                    <label class="spec">
                                        <span class="spec-label"><span x-text="field.label"></span><small x-show="field.unit" x-text="field.unit"></small></span>
                                        <template x-if="field.type === 'boolean' || field.type === 'bool'">
                                            <select class="spec-input" :value="spec(field.key) === '' ? '' : (spec(field.key) ? '1' : '0')"
                                                    @change="setSpec(field.key, $event.target.value === '' ? '' : $event.target.value === '1')">
                                                <option value="">-</option><option value="1">Ja</option><option value="0">Nee</option>
                                            </select>
                                        </template>
                                        <template x-if="field.options && field.type !== 'boolean'">
                                            <select class="spec-input" :value="spec(field.key)" @change="setSpec(field.key, $event.target.value)">
                                                <option value="">-</option>
                                                <template x-for="option in field.options" :key="option"><option :value="option" x-text="option"></option></template>
                                            </select>
                                        </template>
                                        <template x-if="! field.options && field.type !== 'boolean' && field.type !== 'bool'">
                                            <input class="spec-input" :type="['number', 'integer', 'decimal'].includes(field.type) ? 'number' : 'text'" :step="field.type === 'decimal' ? 'any' : null" :inputmode="['number', 'integer', 'decimal'].includes(field.type) ? 'decimal' : null"
                                                   :value="spec(field.key)" @change="setSpec(field.key, $event.target.value === '' ? '' : (['number', 'integer', 'decimal'].includes(field.type) ? Number($event.target.value) : $event.target.value))">
                                        </template>
                                        <span class="spec-src" x-show="specSource(field.key) === 'ai'">door Ivy</span>
                                    </label>
                                </template>
                                <template x-for="item in extraSpecs" :key="item.key">
                                    <label class="spec">
                                        <span class="spec-label"><span x-text="specLabel(item.key)"></span></span>
                                        <input class="spec-input" type="text" :value="item.value === true ? 'Ja' : (item.value === false ? 'Nee' : item.value)" @change="setSpec(item.key, $event.target.value)">
                                        <span class="spec-src" x-show="item.source === 'ai'">door Ivy</span>
                                        <button type="button" class="spec-remove" @click="setSpec(item.key, '')" aria-label="Kenmerk verwijderen"><x-v4.icon name="x" :size="13" stroke="2.4" /></button>
                                    </label>
                                </template>
                            </div>
                            <form class="spec-add" @submit.prevent="addSpec()">
                                <input type="text" x-model="newSpecKey" maxlength="40" placeholder="Nog een kenmerk, bijvoorbeeld: draagvermogen">
                                <button type="submit" class="btn btn-ghost" style="height:40px;font-size:13px">Toevoegen</button>
                            </form>
                        </div>

                        {{-- Zo ziet het eruit in Google: slug en meta-omschrijving uit titel en tekst. --}}
                        <div class="seo-preview" x-show="draft?.title" x-cloak>
                            <span class="seo-url">{{ rtrim(config('app.url'), '/') }}/kavel/<span x-text="seoSlug"></span></span>
                            <span class="seo-title" x-text="draft?.title"></span>
                            <span class="seo-desc" x-text="seoDescription || 'Schrijf een omschrijving; de eerste 155 tekens worden de samenvatting in Google.'"></span>
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
                        <p class="mt-1 text-sm text-aj-muted">Na het indienen controleert Ivy je kavel op prijs, hoeveelheid, garantie en documenten. Klopt alles, dan staat hij meteen live; anders hoor je wat er nog nodig is.</p>

                        <dl class="mt-4 space-y-1.5 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Titel</dt><dd class="font-bold" x-text="draft?.title ?? '-'"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Verkoopvorm</dt><dd class="font-bold" x-text="({auction: 'Veiling', dutch: 'Afslag', buy_now: 'Koop direct'})[meta.sale_type] ?? '-'"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Foto's</dt><dd class="tabular font-bold" x-text="draft?.media.length ?? 0"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Documenten</dt><dd class="tabular font-bold" x-text="draft?.documents.length ?? 0"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Waarde-indicatie</dt><dd class="tabular font-bold" x-text="draft?.value_min_cents != null ? '€ ' + euro(draft.value_min_cents) + ' – € ' + euro(draft.value_max_cents) : 'nog geen'"></dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-aj-muted">Compleetheid</dt><dd class="tabular font-bold" x-text="completeness.score + '%'"></dd></div>
                        </dl>

                        {{-- Preflight (briefing sectie 5, stap 8): dezelfde lijst als de server afdwingt. --}}
                        <h3 class="mt-6 text-sm font-extrabold">Controle vóór publicatie</h3>
                        <ul class="preflight" aria-label="Controlepunten">
                            <template x-for="check in preflight.checks" :key="check.key">
                                <li :class="check.ok ? 'is-ok' : (check.blocking ? 'is-fail' : 'is-warn')">
                                    <svg class="mark" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.2"
                                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <template x-if="check.ok"><path d="m5 10.5 3 3 7-7"/></template>
                                        <template x-if="! check.ok"><path d="M10 5v6m0 3.5v.5"/></template>
                                    </svg>
                                    <span class="body">
                                        <b x-text="check.label"></b>
                                        <small x-show="! check.ok && check.hint" x-text="check.hint"></small>
                                    </span>
                                    <template x-if="! check.ok && check.route">
                                        <a :href="check.route" class="fix">Oplossen</a>
                                    </template>
                                    <template x-if="! check.ok && ! check.route && check.key !== 'account'">
                                        <button type="button" class="fix" @click="step = check.step">Aanpassen</button>
                                    </template>
                                </li>
                            </template>
                        </ul>

                        <template x-if="! loggedIn">
                            <p class="mt-4 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                                Om te publiceren log je eerst in - je concept blijft bewaard en gaat mee.
                            </p>
                        </template>

                        <button type="button" class="btn btn-primary mt-4" @click="publish()" :disabled="! canPublish">
                            <span x-text="loggedIn ? 'Publiceer deze kavel' : 'Inloggen en publiceren'"></span>
                        </button>
                        <p class="mt-2 text-xs text-aj-muted" x-show="! canPublish" x-cloak>
                            Publiceren kan zodra alle verplichte punten hierboven in orde zijn.
                        </p>
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
