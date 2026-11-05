@use('Illuminate\Support\Js')

@php
    // De kop per stap, zoals in het ontwerp van november.
    $titles = [
        'Wat wil je verkopen?',
        'Voeg toe wat je hebt',
        'Ivy-analyse',
        'De advertentie',
        'Hoe wil je verkopen?',
        'Waar wil je publiceren?',
        'Wat kost het?',
        'Klaar om live te gaan?',
    ];
    $leads = [
        'Kies wat het dichtst in de buurt komt, of vertel het in één zin.',
        'Een paar foto\'s vanaf je telefoon zijn genoeg. Documenten kunnen ook later.',
        'Ivy herkent het object, schrijft een eerste advertentie en schat de waarde. Jij past aan en keurt goed.',
        'Nova en Ivy schrijven een eerste versie. Pas aan wat niet klopt, jij keurt goed.',
        'Kies de vorm die bij je doel past. Je betaalt pas als er verkocht is.',
        'Je kavel komt op Aucvy, in de zoekresultaten en bij de categorie.',
        'Plaatsen is gratis. Je betaalt alleen bij een geslaagde verkoop.',
        'Na het indienen controleert Ivy je kavel op prijs, hoeveelheid, garantie en documenten.',
    ];
    // Ivy naast de stappen: per stap één zin.
    $ivyLines = [
        auth()->check()
            ? 'Vertel me wat je wilt verkopen. Je kunt foto\'s uploaden, spraak gebruiken of een korte beschrijving geven.'
            : 'Vertel me wat je wilt verkopen. Je kunt foto\'s uploaden, spraak gebruiken of een korte beschrijving geven. Geen account nodig: je ziet eerst wat ik voor je maak.',
        'Een paar foto\'s vanaf je telefoon zijn genoeg. Ik herken wat erop staat.',
        'Ik kijk naar je foto\'s en vul in wat ik zeker weet. Wat ik niet weet, vraag ik je.',
        'Lees mijn tekst na. Wat jij aanpast, wint altijd van wat ik schreef.',
        'Een range, geen belofte. De markt bepaalt de eindprijs.',
        'Kopers met een passend alert hoor ik zodra je kavel live staat.',
        'Een kijkdag werkt goed bij zware apparatuur: kopers bieden sneller als ze het object hebben gezien.',
        'Alles staat klaar. Nog even de toets, en dan live.',
    ];
    // Namen per id, ook van de hoofdcategorie: een import zet een kavel soms op het hoofdniveau.
    $categoryNames = $categories->pluck('name', 'id')->all() + $categories->flatMap(fn ($root) => $root->children)->pluck('name', 'id')->all();
    $documentKinds = ['invoice' => 'Factuur', 'warranty' => 'Garantiebewijs', 'ownership' => 'Eigendomsbewijs', 'manual' => 'Handleiding', 'inspection' => 'Keuringscertificaat', 'maintenance' => 'Onderhoudsrapport', 'logbook' => 'Logboek', 'pickup_instruction' => 'Ophaalinstructie', 'nameplate' => 'Typeplaat met serienummer (foto)', 'other' => 'Overig'];
    $closeUrl = auth()->check() ? route('dashboard.lots') : route('home');
@endphp

<x-layouts.funnel title="Kavel plaatsen" description="Plaats je kavel in acht stappen. Geen account nodig om te beginnen: je ziet eerst wat Ivy voor je maakt.">
    <div class="fn-page" x-data="funnel({{ Js::from($initial) }}, {{ $feePercentage }}, {{ Js::from($schemas) }})">
        <div class="fn-card">

            {{-- De navy kop: het concept, opslaan en sluiten, en de titel van de stap. --}}
            <header class="fn-head" style="background-image:url('{{ asset('images/design/dash-head.png') }}')">
                <div class="fn-head-top">
                    <a class="fn-brand" href="{{ route('home') }}" aria-label="Aucvy - home">
                        <svg width="20" height="20" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>
                        Aucvy
                    </a>
                    <div class="fn-head-status">
                        <span x-show="draft" x-cloak>
                            Concept <b class="tabular" x-text="'#' + draft?.public_id">#{{ $initial['draft']['public_id'] ?? '' }}</b>
                            · <span x-text="saving ? 'opslaan…' : 'automatisch opgeslagen'">automatisch opgeslagen</span>
                        </span>
                        <a class="fn-head-btn" href="{{ $closeUrl }}" @click.prevent="flush().finally(() => window.location.assign(@js($closeUrl)))">Opslaan en sluiten</a>
                    </div>
                </div>
                <p class="fn-kicker">Kavel plaatsen · stap <span x-text="step + 1">1</span> van 8</p>
                <h1 x-text="({{ Js::from($titles) }})[step]">{{ $titles[0] }}</h1>
                <p class="fn-lead" x-text="({{ Js::from($leads) }})[step]">{{ $leads[0] }}</p>
            </header>

            <div class="fn-body">

                {{-- Conceptoverzicht (briefing sectie 5): hervatten of nieuw beginnen. --}}
                @if ($drafts->isNotEmpty() || auth()->check())
                    <div class="fn-drafts">
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
                            <a href="{{ route('sell', ['nieuw' => 1]) }}" class="d-link" style="margin-left:auto">Nieuw concept</a>
                        @endauth
                    </div>
                @endif

                {{-- Stappenbalk (briefing 13.4): acht chips, klikbaar; wat achter je ligt krijgt een vinkje. --}}
                <nav aria-label="Stappen" class="fn-steps">
                    <template x-for="(name, index) in steps" :key="index">
                        <button type="button" @click="step = index"
                                class="d-step"
                                :class="{ 'is-on': step === index, 'is-done': index < step }"
                                :aria-current="step === index ? 'step' : null">
                            <svg x-show="index < step" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                            <span x-text="(index + 1) + ' · ' + name"></span>
                        </button>
                    </template>
                </nav>

                <div class="fn-grid">
                    <div class="fn-main">

                        {{-- 1 · Wat ------------------------------------------------- --}}
                        <section x-show="step === 0" x-cloak aria-label="Wat wil je verkopen" class="d-stack">
                            <div class="fn-options">
                                <template x-for="option in [{q: 1, icon: 'box', label: 'Eén object', hint: 'Een los item'}, {q: 10, icon: 'layers', label: 'Meerdere objecten', hint: 'Een aantal items'}, {q: 100, icon: 'layout', label: 'Hele inventaris', hint: 'Complete veiling'}]" :key="option.q">
                                    <button type="button" class="fn-option"
                                            :class="((draft?.quantity ?? 0) === 0 ? false : (option.q === 1 ? draft.quantity === 1 : (option.q === 10 ? (draft.quantity > 1 && draft.quantity < 100) : draft.quantity >= 100))) && 'is-on'"
                                            :aria-pressed="(draft?.quantity ?? 0) !== 0 && (option.q === 1 ? draft.quantity === 1 : (option.q === 10 ? (draft.quantity > 1 && draft.quantity < 100) : draft.quantity >= 100))"
                                            @click="set('quantity', option.q)">
                                        <span class="fn-option-icon" aria-hidden="true">
                                            <span x-show="option.icon === 'box'"><x-v4.icon name="box" :size="20" stroke="2" /></span>
                                            <span x-show="option.icon === 'layers'"><x-v4.icon name="layers" :size="20" stroke="2" /></span>
                                            <span x-show="option.icon === 'layout'"><x-v4.icon name="layout" :size="20" stroke="2" /></span>
                                        </span>
                                        <strong x-text="option.label"></strong>
                                        <span x-text="option.hint"></span>
                                    </button>
                                </template>
                            </div>

                            @guest
                                <div class="d-callout-info">
                                    <span class="mark" aria-hidden="true">i</span>
                                    <div>
                                        <strong>Verkopen op Aucvy is alleen voor bedrijven</strong>
                                        Geen account nodig om een concept te maken. Om te publiceren heb je een geverifieerd zakelijk account nodig.
                                    </div>
                                </div>
                            @endguest

                            <div class="d-callout-info" x-show="(draft?.quantity ?? 0) >= 100" x-cloak>
                                <span class="mark" aria-hidden="true">i</span>
                                <div>
                                    <strong>Veel kavels tegelijk? Bundel ze in één veiling</strong>
                                    Eén sluitschema, één bezichtiging en één set voorwaarden. Dit concept wordt één kavel;
                                    @auth
                                        de rest zet je erbij via <a class="d-link" href="{{ route('dashboard.sales') }}">Veilingen</a> of <a class="d-link" href="{{ route('dashboard.import') }}">de import</a>.
                                    @else
                                        de rest zet je er na het inloggen bij via Veilingen of de import.
                                    @endauth
                                </div>
                            </div>

                            <label class="d-field">In je eigen woorden
                                <textarea rows="4" placeholder="Ik stop met mijn restaurant en wil alles verkopen." class="d-textarea" style="margin-top:6px"
                                          :value="draft?.description ?? ''"
                                          @input="set('description', $event.target.value)"></textarea>
                            </label>
                            <div class="d-chips">
                                {{-- Dicteren via de spraakherkenning van de browser (briefing stap 1: spraak). --}}
                                <button type="button" class="d-btn d-btn-secondary d-btn-sq" x-show="canDictate" x-cloak
                                        :class="dictating === 'description' && 'is-on'" @click="dictate('description')">
                                    <x-v4.icon name="mic" :size="15" stroke="2.2" />
                                    <span x-text="dictating === 'description' ? 'Stop met dicteren' : 'Dicteren'">Dicteren</span>
                                </button>
                                <button type="button" class="d-btn d-btn-secondary d-btn-sq" @click="step = 1">Upload foto's</button>
                            </div>

                            <div>
                                <span class="fn-suggest-label">Bijvoorbeeld</span>
                                <div class="d-chips" style="margin-top:8px">
                                    @foreach (['Ik stop met mijn restaurant en wil de volledige keukeninventaris verkopen.', 'Kantoorinventaris na een verhuizing: bureaus, stoelen en kasten.', 'Eén machine uit mijn werkplaats, nog in gebruik.'] as $example)
                                        <button type="button" class="d-chip" style="font-weight:600" @click="set('description', @js($example))">{{ Str::limit($example, 44) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        {{-- 2 · Aanleveren ------------------------------------------ --}}
                        <section x-show="step === 1" x-cloak aria-label="Foto's en documenten" class="d-stack">
                            {{-- Slepen, kiezen of fotograferen; voortgang per bestand (briefing sectie 5, stap 1). --}}
                            <div class="fn-drop" :class="dragOver && 'is-over'"
                                 @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false"
                                 @drop.prevent="uploadPhotos([...$event.dataTransfer.files])">
                                <strong>Sleep je foto's hierheen</strong>
                                <span>JPG, PNG of WebP, maximaal 10 MB per stuk.</span>
                                <div class="d-chips" style="justify-content:center;margin-top:10px">
                                    <label class="d-btn d-btn-sq" style="cursor:pointer">
                                        Foto's kiezen
                                        <input type="file" accept="image/*" multiple class="sr-only"
                                               @change="uploadPhotos([...$event.target.files]); $event.target.value = ''">
                                    </label>
                                    <label class="d-btn d-btn-secondary d-btn-sq" style="cursor:pointer">
                                        Foto maken
                                        <input type="file" accept="image/*" capture="environment" class="sr-only"
                                               @change="uploadPhotos([...$event.target.files]); $event.target.value = ''">
                                    </label>
                                    {{-- Barcode: de lezer van de browser waar die er is, anders leest Ivy de foto (briefing: scanner voor alle browsers). --}}
                                    <label class="d-btn d-btn-secondary d-btn-sq" style="cursor:pointer">
                                        Barcode scannen
                                        <input type="file" accept="image/*" capture="environment" class="sr-only"
                                               @change="scanBarcode($event.target.files[0]); $event.target.value = ''">
                                    </label>
                                    {{-- Typeplaat: Ivy leest merk, model, bouwjaar, vermogen en serienummer. De foto blijft intern. --}}
                                    <label class="d-btn d-btn-secondary d-btn-sq" style="cursor:pointer">
                                        Typeplaat lezen
                                        <input type="file" accept="image/*" capture="environment" class="sr-only"
                                               @change="scanNameplate($event.target.files[0]); $event.target.value = ''">
                                    </label>
                                </div>
                                <form class="fn-gtin" @submit.prevent="submitGtin()">
                                    <label for="gtin-input">Of typ de productcode (EAN)</label>
                                    <input id="gtin-input" type="text" inputmode="numeric" x-model="scan.code" maxlength="20" placeholder="8712345678906" class="d-input" style="margin:0;width:180px;height:40px">
                                    <button type="submit" class="d-btn d-btn-secondary d-btn-sm d-btn-sq" style="min-height:40px" :disabled="scan.busy || !scan.code">Opzoeken</button>
                                </form>
                                <p class="d-hint" x-show="scan.busy" x-cloak x-text="scan.busyText"></p>
                                <p class="d-hint" style="color:#177249;font-weight:700" x-show="scan.result" x-text="scan.result" x-cloak></p>
                                <p class="d-hint is-warn" x-show="scan.error" x-text="scan.error" x-cloak></p>
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

                            <div x-show="draft?.media.length" x-cloak>
                                <h2 class="fn-h2">Geüpload (<span x-text="draft?.media.length ?? 0"></span> <span x-text="(draft?.media.length ?? 0) === 1 ? 'foto' : 'foto\'s'"></span>)</h2>
                                <ul class="photo-grid" style="margin-top:8px">
                                    <template x-for="(item, index) in draft?.media ?? []" :key="item.id">
                                        <li class="photo" :class="index === 0 && 'is-cover'">
                                            <img :src="item.url" alt="">
                                            <span class="cover-tag" x-show="index === 0">Hoofdfoto</span>
                                            <button type="button" class="photo-cover" x-show="index !== 0" @click="setCover(item)">Als hoofdfoto</button>
                                            <button type="button" class="photo-remove" @click="removePhoto(item)" aria-label="Foto verwijderen">
                                                <x-v4.icon name="x" :size="14" stroke="2.4" />
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                                <p class="d-hint">De hoofdfoto is wat kopers in de lijst zien. Kies een andere met "Als hoofdfoto".</p>
                            </div>

                            <div class="fn-divide">
                                <h2 class="fn-h2">Documenten</h2>
                                <p class="d-sub">Facturen en eigendomsbewijzen blijven privé. Per document kies je in het kaveldossier of kopers het via het oogje mogen inzien; downloaden kan niet.</p>

                                <div class="d-chips" style="margin-top:10px" x-data="{ kind: 'invoice' }">
                                    <select x-model="kind" class="d-select" style="margin:0;min-width:170px" aria-label="Soort document">
                                        @foreach ($documentKinds as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <label class="d-btn d-btn-secondary d-btn-sq" style="cursor:pointer">
                                        Bestand kiezen
                                        <input type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="sr-only"
                                               @change="uploadDocument($event.target.files[0], kind); $event.target.value = ''">
                                    </label>
                                </div>

                                <div class="d-stack-tight" style="margin-top:10px;gap:8px" x-show="draft?.documents.length" x-cloak>
                                    <template x-for="doc in draft?.documents ?? []" :key="doc.id">
                                        <div class="d-soft" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px">
                                            <strong x-text="doc.name" style="font-size:14px"></strong>
                                            <span class="d-muted" x-text="({{ Js::from($documentKinds) }})[doc.kind] ?? doc.kind"></span>
                                        </div>
                                    </template>
                                </div>

                                {{-- De echtheid van documenten aanvinken (besluit Raph): alleen als er documenten zijn. --}}
                                <label class="fn-check" style="margin-top:10px" x-show="(draft?.documents?.length ?? 0) > 0" x-cloak>
                                    <input type="checkbox" :checked="meta.documents_authentic === true" @change="set('documents_authentic', $event.target.checked)">
                                    <span>
                                        <strong>De documenten die ik toevoeg zijn echt en volledig.</strong>
                                        <span>Nodig om te publiceren als je documenten toevoegt. Een vals document leidt tot verwijdering van de kavel en blokkade van het account.</span>
                                    </span>
                                </label>
                            </div>

                            {{-- Garantie (publicatiecriterium, besluit Raph 2 november 2026). --}}
                            <div class="d-soft" style="padding:14px">
                                <div class="fn-fields">
                                    <label class="d-field">Garantie
                                        <select class="d-input" :value="meta.warranty ?? ''" @change="set('warranty', $event.target.value || null)">
                                            <option value="">Kies...</option>
                                            @foreach (config('listings.warranty_options') as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="d-field" x-show="meta.warranty && meta.warranty !== 'none'" x-cloak>Geldig tot
                                        <input type="date" class="d-input" :value="meta.warranty_until ?? ''" @change="set('warranty_until', $event.target.value || null)">
                                    </label>
                                </div>
                            </div>

                            {{-- Eigendom (preflight, briefing sectie 5): verklaring of bewijs. --}}
                            <label class="fn-check is-card">
                                <input type="checkbox" :checked="meta.ownership_declared === true" @change="set('ownership_declared', $event.target.checked)">
                                <span>
                                    <strong>Ik ben eigenaar van dit object, of gemachtigd om het te verkopen.</strong>
                                    <span>Nodig om te publiceren. Een eigendomsbewijs of factuur als document maakt je kavel sterker en geldt ook als verklaring.</span>
                                </span>
                            </label>
                        </section>

                        {{-- 3 · Analyse --------------------------------------------- --}}
                        <section x-show="step === 2" x-cloak aria-label="Analyse" class="d-stack">
                            @if ($ivyEnabled)
                                <div class="d-callout-ok" x-show="! ivy.running && (ivy.notes.length || draft?.value_min_cents != null)" x-cloak>
                                    <span class="mark" aria-hidden="true"><x-v4.icon name="check" :size="20" stroke="3" /></span>
                                    <div>
                                        <strong>Ivy heeft je kavel herkend</strong>
                                        <span>Kijk in de volgende stap na wat ze invulde. Wat jij aanpast, wint altijd.</span>
                                    </div>
                                </div>

                                <div class="fn-row">
                                    <h2 class="fn-h2">Dit plaats je</h2>
                                    <button type="button" class="d-btn d-btn-secondary d-btn-sq" :disabled="ivy.running" @click="analyse()">
                                        <span x-text="ivy.running ? 'Ivy kijkt mee…' : (ivy.notes.length || draft?.value_min_cents != null ? 'Laat opnieuw analyseren' : 'Laat Ivy meekijken')">Laat Ivy meekijken</span>
                                    </button>
                                </div>

                                <div class="fn-lotcards">
                                    <article class="fn-lotcard is-on">
                                        <div class="fn-lotcard-media">
                                            <template x-if="draft?.media?.length"><img :src="draft.media[0].url" alt=""></template>
                                        </div>
                                        <div class="fn-lotcard-body">
                                            <strong x-text="draft?.title || 'Nog zonder titel'">Nog zonder titel</strong>
                                            <span class="d-muted" x-text="({{ Js::from($categoryNames) }})[draft?.category_id] ?? 'Nog geen categorie'"></span>
                                            <span class="d-muted" style="margin-top:6px">Waarde-indicatie van Atlas</span>
                                            <b class="tabular" x-text="draft?.value_min_cents != null ? money(draft.value_min_cents) + ' – ' + money(draft.value_max_cents) : 'Nog geen indicatie'"></b>
                                        </div>
                                    </article>
                                </div>

                                {{-- Voortgang per taak, met het zekerheidslabel uit de ConfidencePolicy - nooit een kale modelbelofte (hfst. 8). --}}
                                <ul class="fn-notes" x-show="ivy.notes.length || ivy.running" x-cloak aria-live="polite">
                                    <template x-for="note in ivy.notes" :key="note.id">
                                        <li>
                                            <img :src="'/images/agents/' + note.agent + '.jpg'" :alt="note.agent + ', AI-specialist'" loading="lazy">
                                            <span><strong x-text="ivyLabel(note.task)"></strong> <span class="d-muted" x-text="'- ' + ivyNote(note)"></span></span>
                                        </li>
                                    </template>
                                    <li x-show="ivy.running" x-cloak class="d-muted">
                                        <span x-text="(ivy.current ? ivyLabel(ivy.current) : 'Bezig') + '…'"></span>
                                    </li>
                                </ul>

                                {{-- Meer aangeleverd = scherpere schatting: dat moment benoemen we expliciet (preview v4). --}}
                                <p x-show="ivy.sharpened" x-cloak class="d-soft is-ok" style="margin:0">
                                    Je waarde-indicatie werd scherper:
                                    <span class="tabular" x-text="money(draft?.value_min_cents) + ' – ' + money(draft?.value_max_cents)"></span>
                                </p>

                                <div class="d-callout-info">
                                    <span class="mark" aria-hidden="true">i</span>
                                    <div>
                                        <strong>Een range, geen belofte</strong>
                                        De waarde-indicatie komt uit vergelijkbare verkopen, met de bron erbij. Zelf invullen werkt net zo goed.
                                    </div>
                                </div>
                            @else
                                {{-- Noodrem om (hfst. 8): geen knop die niets gaat doen, wel de eerlijke handmatige route. --}}
                                <div class="d-callout-info">
                                    <span class="mark" aria-hidden="true">i</span>
                                    <div>
                                        <strong>Ivy staat op dit moment uit</strong>
                                        Je vult de advertentie zelf in bij de volgende stap. Dat werkt net zo goed.
                                    </div>
                                </div>
                            @endif
                        </section>

                        {{-- 4 · Advertentie ----------------------------------------- --}}
                        <section x-show="step === 3" x-cloak aria-label="Advertentie" class="d-stack">
                            <label class="d-field">Titel
                                <input type="text" maxlength="150" placeholder="Complete designkeuken met Siemens-apparatuur" class="d-input"
                                       :value="draft?.title ?? ''" @input="set('title', $event.target.value)">
                                <x-funnel.provenance field="title" />
                            </label>

                            <label class="d-field">Omschrijving
                                <textarea rows="6" class="d-textarea" style="margin-top:6px" placeholder="Eerlijk over gebreken: dat verkoopt aantoonbaar beter."
                                          :value="draft?.description ?? ''" @input="set('description', $event.target.value)"></textarea>
                                <x-funnel.provenance field="description" />
                            </label>
                            <div>
                                <button type="button" class="d-btn d-btn-secondary d-btn-sq" x-show="canDictate" x-cloak
                                        :class="dictating === 'description' && 'is-on'" @click="dictate('description')">
                                    <x-v4.icon name="mic" :size="15" stroke="2.2" />
                                    <span x-text="dictating === 'description' ? 'Stop met dicteren' : 'Dicteren'">Dicteren</span>
                                </button>
                            </div>

                            <p class="d-soft is-warn" style="margin:0;font-weight:500;color:#0e1526" x-show="unconfirmed.length" x-cloak>
                                Ivy heeft velden ingevuld waar ze niet zeker van is. Kijk ze na: corrigeer wat niet klopt, of
                                <button type="button" class="d-link" @click="confirmAll()">bevestig alles in één keer</button>.
                            </p>

                            <div class="fn-fields is-four">
                                <label class="d-field">Categorie
                                    <select class="d-input" :value="draft?.category_id ?? ''"
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

                                <label class="d-field">Conditie
                                    <select class="d-input" :value="draft?.condition ?? ''" @change="set('condition', $event.target.value || null)">
                                        <option value="">Kies de conditie</option>
                                        @foreach (\App\Enums\LotCondition::cases() as $condition)
                                            <option value="{{ $condition->value }}">{{ $condition->label() }}</option>
                                        @endforeach
                                    </select>
                                    <x-funnel.provenance field="condition" />
                                </label>

                                <label class="d-field">Merk
                                    <input type="text" maxlength="80" class="d-input" :value="draft?.brand ?? ''" @input="set('brand', $event.target.value || null)">
                                    <x-funnel.provenance field="brand" />
                                </label>

                                <label class="d-field">Model of type
                                    <input type="text" maxlength="80" class="d-input" :value="draft?.model ?? ''" @input="set('model', $event.target.value || null)">
                                    <x-funnel.provenance field="model" />
                                </label>

                                <label class="d-field">Bouwjaar
                                    <input type="number" min="1900" max="{{ now()->year }}" class="d-input tabular" :value="draft?.year ?? ''"
                                           @input="set('year', $event.target.value ? Number($event.target.value) : null)">
                                    <x-funnel.provenance field="year" />
                                </label>

                                {{-- Hoeveelheid met eenheid (publicatiecriterium, besluit Raph 2 november 2026). --}}
                                <div class="d-field">Hoeveelheid en eenheid
                                    <div style="display:flex;gap:6px">
                                        <input type="number" min="1" max="100000" class="d-input tabular" style="width:90px" aria-label="Aantal"
                                               :value="draft?.quantity ?? 1" @change="set('quantity', Math.max(1, parseInt($event.target.value || '1', 10)))">
                                        <select class="d-input" aria-label="Eenheid" :value="draft?.unit ?? 'stuk'" @change="set('unit', $event.target.value)">
                                            @foreach (config('listings.unit_options') as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Kenmerken: de velden uit het schema van de categorie, plus wat Ivy of jijzelf er los bij zet (briefing sectie 5, stap 4). --}}
                            <div class="specs fn-divide">
                                <h2 class="fn-h2">Kenmerken</h2>
                                <p class="d-sub" x-text="draft?.category_id ? 'Ivy weet per categorie welke kenmerken kopers verwachten.' : 'Kies eerst een categorie; dan weet Ivy welke kenmerken kopers verwachten.'">Ivy weet per categorie welke kenmerken kopers verwachten.</p>
                                <div class="spec-grid">
                                    {{-- Merk, model en bouwjaar staan hierboven al als eigen veld. --}}
                                    <template x-for="field in schemaFields.filter((item) => ! ['brand', 'model', 'build_year'].includes(item.key))" :key="field.key">
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
                                <form class="fn-specadd" @submit.prevent="addSpec()">
                                    <input type="text" x-model="newSpecKey" maxlength="40" placeholder="Nog een kenmerk, bijvoorbeeld: draagvermogen" class="d-input" style="margin:0">
                                    <button type="submit" class="d-btn d-btn-secondary d-btn-sq">Toevoegen</button>
                                </form>
                            </div>

                            {{-- Zo ziet het eruit in Google: slug en meta-omschrijving uit titel en tekst. --}}
                            <div class="seo-preview" x-show="draft?.title" x-cloak>
                                <span class="seo-url">{{ rtrim(config('app.url'), '/') }}/kavel/<span x-text="seoSlug"></span></span>
                                <span class="seo-title" x-text="draft?.title"></span>
                                <span class="seo-desc" x-text="seoDescription || 'Schrijf een omschrijving; de eerste 155 tekens worden de samenvatting in Google.'"></span>
                            </div>
                        </section>

                        {{-- 5 · Vorm ------------------------------------------------ --}}
                        <section x-show="step === 4" x-cloak aria-label="Verkoopvorm" class="d-stack">
                            <div class="fn-options">
                                <template x-for="form in [{v: 'auction', label: 'Veiling', hint: 'Oplopend bieden. De markt bepaalt de prijs.'}, {v: 'dutch', label: 'Afslag', hint: 'De prijs daalt in stappen. Wie afmijnt, koopt.'}, {v: 'buy_now', label: 'Koop direct', hint: 'Een vaste prijs, meteen te kopen.'}]" :key="form.v">
                                    <button type="button" class="fn-option" :class="meta.sale_type === form.v && 'is-on'" :aria-pressed="meta.sale_type === form.v" @click="set('sale_type', form.v)">
                                        {{-- Het tarief is voor elke vorm hetzelfde: de staffel op je omzet. --}}
                                        <b class="fn-option-fee tabular" x-text="fee.toString().replace('.', ',') + '%'"></b>
                                        <span>bij verkoop</span>
                                        <strong x-text="form.label" style="margin-top:10px"></strong>
                                        <span x-text="form.hint"></span>
                                    </button>
                                </template>
                            </div>

                            <div class="d-card" x-show="meta.sale_type" x-cloak>
                                {{-- Veiling --}}
                                <div x-show="meta.sale_type === 'auction'" x-cloak class="fn-fields is-three">
                                    <label class="d-field">Startprijs
                                        <span class="d-input-wrap"><i aria-hidden="true">&euro;</i>
                                            <input type="text" inputmode="decimal" class="d-input is-amount tabular" :value="euro(meta.start_price_cents)" @change="setEuro('start_price_cents', $event.target.value)">
                                        </span>
                                        <span class="d-hint">Hier begint het bieden.</span>
                                    </label>
                                    <label class="d-field">Reserveprijs
                                        <span class="d-input-wrap"><i aria-hidden="true">&euro;</i>
                                            <input type="text" inputmode="decimal" class="d-input is-amount tabular" :value="euro(meta.reserve_price_cents)" @change="setEuro('reserve_price_cents', $event.target.value)">
                                        </span>
                                        <span class="d-hint">Niet zichtbaar voor kopers; alleen óf hij gehaald is.</span>
                                    </label>
                                    <label class="d-field">Looptijd
                                        <select class="d-input" :value="meta.duration_days ?? 7" @change="set('duration_days', Number($event.target.value))">
                                            <option value="3">3 dagen</option>
                                            <option value="7">7 dagen</option>
                                            <option value="14">14 dagen</option>
                                        </select>
                                        <span class="d-hint">Met verlenging bij een laat bod.</span>
                                    </label>
                                </div>

                                {{-- Afslag --}}
                                <div x-show="meta.sale_type === 'dutch'" x-cloak class="fn-fields is-three">
                                    <label class="d-field">Startprijs
                                        <span class="d-input-wrap"><i aria-hidden="true">&euro;</i>
                                            <input type="text" inputmode="decimal" class="d-input is-amount tabular" :value="euro(meta.dutch_start_cents)" @change="setEuro('dutch_start_cents', $event.target.value)">
                                        </span>
                                        <span class="d-hint">Hier begint de prijs.</span>
                                    </label>
                                    <label class="d-field">Bodemprijs
                                        <span class="d-input-wrap"><i aria-hidden="true">&euro;</i>
                                            <input type="text" inputmode="decimal" class="d-input is-amount tabular" :value="euro(meta.dutch_floor_cents)" @change="setEuro('dutch_floor_cents', $event.target.value)">
                                        </span>
                                        <span class="d-hint">Daaronder zakt hij nooit.</span>
                                    </label>
                                    <label class="d-field">Stap per daling
                                        <span class="d-input-wrap"><i aria-hidden="true">&euro;</i>
                                            <input type="text" inputmode="decimal" class="d-input is-amount tabular" :value="euro(meta.dutch_step_cents)" @change="setEuro('dutch_step_cents', $event.target.value)">
                                        </span>
                                    </label>
                                    <label class="d-field">Interval (seconden)
                                        <input type="number" min="5" max="600" placeholder="20" class="d-input tabular" :value="meta.dutch_interval_s ?? ''" @change="set('dutch_interval_s', Number($event.target.value) || null)">
                                    </label>
                                    <label class="d-field">Looptijd
                                        <select class="d-input" :value="meta.duration_days ?? 7" @change="set('duration_days', Number($event.target.value))">
                                            <option value="3">3 dagen</option>
                                            <option value="7">7 dagen</option>
                                            <option value="14">14 dagen</option>
                                        </select>
                                    </label>
                                </div>

                                {{-- Koop direct --}}
                                <div x-show="meta.sale_type === 'buy_now'" x-cloak class="fn-fields is-three">
                                    <label class="d-field">Vaste prijs
                                        <span class="d-input-wrap"><i aria-hidden="true">&euro;</i>
                                            <input type="text" inputmode="decimal" class="d-input is-amount tabular" :value="euro(meta.buy_now_price_cents)" @change="setEuro('buy_now_price_cents', $event.target.value)">
                                        </span>
                                        <span class="d-hint">Wie het eerst koopt, koopt.</span>
                                    </label>
                                    <label class="d-field">Looptijd
                                        <select class="d-input" :value="meta.duration_days ?? 7" @change="set('duration_days', Number($event.target.value))">
                                            <option value="3">3 dagen</option>
                                            <option value="7">7 dagen</option>
                                            <option value="14">14 dagen</option>
                                        </select>
                                    </label>
                                </div>
                            </div>

                            {{-- De prijzencontrole uit de preflight, hier al zichtbaar. --}}
                            <template x-for="check in preflight.checks.filter((item) => item.key === 'price_order' && meta.sale_type)" :key="check.key">
                                <div :class="check.ok ? 'd-callout-ok' : 'd-callout-warn'">
                                    <span class="mark" aria-hidden="true" x-text="check.ok ? '✓' : '!'"></span>
                                    <div class="text">
                                        <strong x-text="check.ok ? 'Prijzen staan in de juiste volgorde' : check.label"></strong>
                                        <span x-text="check.ok ? 'Start, reserve en vaste prijs kloppen ten opzichte van elkaar.' : check.hint"></span>
                                    </div>
                                </div>
                            </template>

                            {{-- Marge of btw (besluit Don, vraag 3): de verkoper kiest per kavel. --}}
                            <fieldset class="fn-fieldset">
                                <legend class="fn-h2">Btw op deze kavel</legend>
                                <div class="fn-options is-two" style="margin-top:8px">
                                    <label class="fn-option" :class="(draft?.vat_regime ?? 'margin') === 'margin' && 'is-on'">
                                        <input type="radio" name="vat_regime" value="margin" class="sr-only" :checked="(draft?.vat_regime ?? 'margin') === 'margin'" @change="set('vat_regime', 'margin')">
                                        <strong>Margeregeling</strong>
                                        <span>Gebruikt goed, geen btw op de factuur; wel de vermelding "bijzondere regeling, gebruikte goederen".</span>
                                    </label>
                                    <label class="fn-option" :class="draft?.vat_regime === 'vat_21' && 'is-on'">
                                        <input type="radio" name="vat_regime" value="vat_21" class="sr-only" :checked="draft?.vat_regime === 'vat_21'" @change="set('vat_regime', 'vat_21')">
                                        <strong>Btw 21%</strong>
                                        <span>Btw-goed: de koper betaalt 21% over het bod en kan die als ondernemer terugvragen.</span>
                                    </label>
                                </div>
                            </fieldset>
                        </section>

                        {{-- 6 · Promotie -------------------------------------------- --}}
                        <section x-show="step === 5" x-cloak aria-label="Promotie" class="d-stack">
                            <div>
                                <h2 class="fn-h2">Altijd inbegrepen</h2>
                                <div class="fn-checklist" style="margin-top:10px">
                                    <div><span class="ok" aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></span><span><strong>Aucvy zoekresultaten</strong><span>Je kavel is vindbaar voor alle kopers.</span></span></div>
                                    <div><span class="ok" aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></span><span><strong>Categoriepagina</strong><span x-text="'Bij ' + (({{ Js::from($categoryNames) }})[draft?.category_id] ?? 'de categorie die je kiest') + '.'"></span></span></div>
                                    <div><span class="ok" aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></span><span><strong>Alerts van kopers</strong><span>Kopers met een passende zoekopdracht krijgen een seintje.</span></span></div>
                                </div>
                            </div>

                            {{-- Verspreiden (briefing nieuwe functies): de deelkit dekt elk kanaal vanaf dag één; koppelingen komen per kanaal. --}}
                            <div>
                                <h2 class="fn-h2">Extra kanalen</h2>
                                <p class="d-sub">Zodra de kavel live staat, krijg je per kanaal een tekst van Nova, een beeld in drie formaten en een link met herkomst. Je plaatst zelf en ziet de klikken per kanaal.</p>
                                <div class="fn-channels">
                                    @foreach (['Marktplaats', 'Facebook', 'Instagram', 'LinkedIn', 'WhatsApp', 'Mail'] as $channel)
                                        <div><span>{{ $channel }}</span><span class="d-tag tone-grey">Deelkit</span></div>
                                    @endforeach
                                </div>
                                <p class="d-hint">Social push: één planning met drie momenten (start, dag voor sluiting, laatste uur) die je in één keer goedkeurt. Een gekoppeld kanaal plaatst Aucvy zelf; koppelen doe je bij Instellingen, Koppelingen.</p>
                            </div>
                        </section>

                        {{-- 7 · Kosten ---------------------------------------------- --}}
                        <section x-show="step === 6" x-cloak aria-label="Kosten" class="d-stack">
                            <div class="d-tiles" style="grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
                                <div class="d-tile tone-green"><div class="d-tile-label" style="color:#177249">Plaatsen</div><div class="d-tile-value">Gratis</div><div class="d-tile-sub">geen kosten vooraf</div></div>
                                <div class="d-tile tone-purple"><div class="d-tile-label" style="color:#6d28d9">Succesfee</div><div class="d-tile-value tabular" x-text="fee.toString().replace('.', ',') + '%'"></div><div class="d-tile-sub">alleen bij verkoop</div></div>
                                <div class="d-tile tone-blue"><div class="d-tile-label" style="color:#1e56e0">Opgeld koper</div><div class="d-tile-value">€ 0</div><div class="d-tile-sub">kopers betalen geen opgeld</div></div>
                            </div>

                            {{-- Rekenvoorbeeld op de startprijs, de vaste prijs of anders € 3.000: geen belofte, wel de som. --}}
                            <div class="d-soft" style="padding:16px" x-data="{ get basis() { return meta.buy_now_price_cents || meta.start_price_cents || meta.dutch_start_cents || 300000; } }">
                                <strong style="font-size:14px">Rekenvoorbeeld bij een eindbod van <span class="tabular" x-text="money(basis)"></span></strong>
                                <div class="d-rows" style="margin-top:8px">
                                    <div class="d-row"><span>Eindbod</span><strong x-text="money(basis)"></strong></div>
                                    <div class="d-row"><span x-text="'Succesfee (' + fee.toString().replace('.', ',') + '%)'"></span><strong x-text="'- ' + money(Math.round(basis * fee / 100))"></strong></div>
                                    <div class="d-row"><span>Btw over succesfee ({{ rtrim(rtrim(number_format(\App\Support\Money\Money::vatRatePercent(), 1, ',', ''), '0'), ',') }}%)</span><strong x-text="'- ' + money(Math.round(Math.round(basis * fee / 100) * {{ \App\Support\Money\Money::vatRatePercent() }} / 100))"></strong></div>
                                    <div class="d-row" style="font-size:15px;font-weight:800"><span>Jij ontvangt</span><strong x-text="money(basis - Math.round(basis * fee / 100) - Math.round(Math.round(basis * fee / 100) * {{ \App\Support\Money\Money::vatRatePercent() }} / 100))"></strong></div>
                                </div>
                                <p class="d-hint">Het tarief is een staffel op je omzet van de laatste twaalf maanden en ligt vast op het moment van verkoop.</p>
                            </div>

                            <div class="d-card">
                                <h2 class="fn-h2">Bezichtigen en ophalen</h2>
                                <div class="d-stack-tight" style="margin-top:10px">
                                    <label class="fn-check">
                                        <input type="checkbox" :checked="draft?.viewing_available" @change="set('viewing_available', $event.target.checked)">
                                        <span><strong>Bezichtiging mogelijk (kijkdag)</strong><span>Kopers stellen een moment voor; jij bevestigt, daarna krijgen ze het adres.</span></span>
                                    </label>
                                    <label class="fn-check">
                                        <input type="checkbox" :checked="draft?.pickup_only" @change="set('pickup_only', $event.target.checked)">
                                        <span><strong>Alleen zelf ophalen</strong><span>Geen transport via Aucvy.</span></span>
                                    </label>
                                    <label class="d-field" style="max-width:260px" x-show="! draft?.pickup_only" x-cloak>Transport vanaf
                                        <span class="d-input-wrap"><i aria-hidden="true">&euro;</i>
                                            <input type="text" inputmode="decimal" class="d-input is-amount tabular" :value="euro(draft?.transport_from_cents)" @change="setEuro('transport_from_cents', $event.target.value)">
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </section>

                        {{-- 8 · Live ------------------------------------------------ --}}
                        <section x-show="step === 7" x-cloak aria-label="Controleren en publiceren" class="d-stack">
                            <div class="d-callout-ok" x-show="loggedIn && canPublish" x-cloak>
                                <span class="mark" aria-hidden="true"><x-v4.icon name="check" :size="20" stroke="3" /></span>
                                <div>
                                    <strong>Je kavel staat klaar</strong>
                                    <span>Klopt alles, dan staat hij meteen live. Anders hoor je wat er nog nodig is.</span>
                                </div>
                            </div>
                            <div class="d-callout-warn" x-show="! loggedIn" x-cloak>
                                <span class="mark" aria-hidden="true">!</span>
                                <div class="text">
                                    <strong>Nog één stap: je zakelijke account</strong>
                                    <span>Je concept is bewaard en gaat mee. Publiceren kan alleen met een geverifieerd zakelijk account. Heb je er al een? Log dan in.</span>
                                </div>
                            </div>
                            <div class="d-callout-warn" x-show="loggedIn && ! canPublish" x-cloak>
                                <span class="mark" aria-hidden="true">!</span>
                                <div class="text">
                                    <strong>Nog niet alles is in orde</strong>
                                    <span>De punten met een uitroepteken hieronder moeten eerst af.</span>
                                </div>
                            </div>

                            <article class="d-listrow">
                                <template x-if="draft?.media?.length"><img class="d-listrow-thumb" :src="draft.media[0].url" alt=""></template>
                                <template x-if="! draft?.media?.length"><span class="d-listrow-thumb" aria-hidden="true"></span></template>
                                <div class="d-listrow-body">
                                    <strong x-text="draft?.title || 'Nog zonder titel'"></strong>
                                    <span class="d-listrow-meta" x-text="[({auction: 'Veiling', dutch: 'Afslag', buy_now: 'Koop direct'})[meta.sale_type], (meta.duration_days ?? 7) + ' dagen', ({{ Js::from($categoryNames) }})[draft?.category_id]].filter(Boolean).join(' · ')"></span>
                                </div>
                                <div class="d-listrow-right" x-show="meta.start_price_cents || meta.dutch_start_cents || meta.buy_now_price_cents">
                                    <b x-text="money(meta.buy_now_price_cents && meta.sale_type === 'buy_now' ? meta.buy_now_price_cents : (meta.sale_type === 'dutch' ? meta.dutch_start_cents : meta.start_price_cents))"></b>
                                    <span x-text="meta.sale_type === 'buy_now' ? 'Vaste prijs' : 'Startprijs'"></span>
                                </div>
                            </article>

                            {{-- Preflight (briefing sectie 5, stap 8): dezelfde lijst als de server afdwingt. --}}
                            <div>
                                <h2 class="fn-h2">Controle vóór publicatie</h2>
                                <div class="fn-checklist" style="margin-top:10px" aria-label="Controlepunten">
                                    <template x-for="check in preflight.checks" :key="check.key">
                                        <div>
                                            <span :class="check.ok ? 'ok' : (check.blocking ? 'fail' : 'warn')" aria-hidden="true" x-text="check.ok ? '✓' : '!'"></span>
                                            <span>
                                                <strong x-text="check.label"></strong>
                                                <span x-show="check.ok">In orde</span>
                                                <span x-show="! check.ok" x-text="check.hint ?? (check.blocking ? 'Nodig om te publiceren' : 'Aanbevolen, niet verplicht')"></span>
                                            </span>
                                            <template x-if="! check.ok && check.route">
                                                <a :href="check.route" class="d-link">Regelen</a>
                                            </template>
                                            <template x-if="! check.ok && ! check.route && check.key !== 'account'">
                                                <button type="button" class="d-link" @click="step = check.step">Aanpassen</button>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                <p class="d-hint" x-show="! canPublish" x-cloak>Publiceren kan zodra alle verplichte punten hierboven in orde zijn.</p>
                            </div>
                        </section>

                        <p x-show="error" x-cloak role="alert" class="d-soft is-err" style="margin:0" x-text="error"></p>

                        {{-- Navigatie: terug werkt, elke stap is omkeerbaar. --}}
                        <div class="fn-nav">
                            <button type="button" class="d-btn d-btn-secondary d-btn-sq" x-show="step > 0" @click="step--">Terug</button>
                            <span class="d-muted" x-show="saving" x-cloak>Opslaan…</span>
                            <span class="d-muted" style="color:#177249" x-show="saved" x-cloak>Opgeslagen</span>
                            <button type="button" class="d-btn d-btn-sq" style="margin-left:auto" x-show="step < 7" @click="step++"
                                    x-text="({2: 'Door naar de advertentie', 5: 'Door naar de kosten'})[step] ?? 'Volgende'">Volgende</button>
                            <button type="button" class="d-btn d-btn-sq" style="margin-left:auto" x-show="step === 7" x-cloak @click="publish()" :disabled="! canPublish">
                                <span x-text="loggedIn ? 'Publiceer deze kavel' : 'Inloggen en publiceren'">Publiceer deze kavel</span>
                            </button>
                        </div>
                    </div>

                    {{-- Zijkolom: wat bij de stap hoort, dan Ivy en de compleetheidsmeter (briefing 13.4). --}}
                    <aside class="fn-aside">

                        {{-- Stap 4: zo komt hij in de lijst te staan. --}}
                        <div class="fn-preview" x-show="step === 3" x-cloak>
                            <div class="fn-preview-media"><template x-if="draft?.media?.length"><img :src="draft.media[0].url" alt=""></template></div>
                            <div class="fn-preview-body">
                                <span class="d-tag tone-warn">Voorbeeld</span>
                                <strong x-text="draft?.title || 'Je titel komt hier'"></strong>
                                <span class="d-muted" x-show="draft?.value_min_cents != null" x-text="'Waarde-indicatie ' + money(draft?.value_min_cents) + ' – ' + money(draft?.value_max_cents)"></span>
                            </div>
                        </div>

                        {{-- Stap 5: de waarde-indicatie van Atlas naast de prijzen. --}}
                        <div class="d-soft" style="padding:16px" x-show="step === 4 && draft?.value_min_cents != null" x-cloak>
                            <span class="d-muted">Waarde-indicatie van Atlas</span>
                            <div class="d-big" style="font-size:20px;margin-top:4px" x-text="money(draft?.value_min_cents) + ' – ' + money(draft?.value_max_cents)"></div>
                            <p class="d-hint">Uit vergelijkbare verkopen. Een range, geen belofte.</p>
                        </div>

                        {{-- Stap 6: zo staat hij in de resultaten. --}}
                        <div class="d-card" style="padding:12px" x-show="step === 5" x-cloak>
                            <template x-if="draft?.media?.length"><img :src="draft.media[0].url" alt="" style="width:92px;height:72px;object-fit:cover;border-radius:10px"></template>
                            <div style="display:flex;justify-content:space-between;gap:10px;margin-top:10px">
                                <div style="min-width:0"><strong style="display:block;font-size:14px" x-text="draft?.title || 'Je titel komt hier'"></strong><span class="d-muted">Zo staat hij in de resultaten</span></div>
                                <div style="text-align:right;flex:none" x-show="meta.start_price_cents || meta.dutch_start_cents || meta.buy_now_price_cents">
                                    <b class="tabular" x-text="money(meta.buy_now_price_cents && meta.sale_type === 'buy_now' ? meta.buy_now_price_cents : (meta.sale_type === 'dutch' ? meta.dutch_start_cents : meta.start_price_cents))"></b>
                                    <span class="d-muted" style="display:block" x-text="meta.sale_type === 'buy_now' ? 'Vaste prijs' : 'Startprijs'"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Stap 8: de samenvatting. --}}
                        <div class="d-soft" style="padding:6px 16px" x-show="step === 7" x-cloak>
                            <div class="d-rows">
                                <div class="d-row"><span class="d-muted">Verkoopvorm</span><strong x-text="({auction: 'Veiling', dutch: 'Afslag', buy_now: 'Koop direct'})[meta.sale_type] ?? '-'"></strong></div>
                                <div class="d-row"><span class="d-muted">Foto's</span><strong x-text="draft?.media.length ?? 0"></strong></div>
                                <div class="d-row"><span class="d-muted">Documenten</span><strong x-text="draft?.documents.length ?? 0"></strong></div>
                                <div class="d-row"><span class="d-muted">Waarde-indicatie</span><strong x-text="draft?.value_min_cents != null ? money(draft.value_min_cents) + ' – ' + money(draft.value_max_cents) : 'nog geen'"></strong></div>
                                <div class="d-row"><span class="d-muted">Kijkmoment</span><strong x-text="draft?.viewing_available ? 'Op aanvraag' : 'Geen'"></strong></div>
                                <div class="d-row"><span class="d-muted">Ophalen</span><strong x-text="draft?.pickup_only ? 'Alleen zelf ophalen' : 'Ophalen of transport'"></strong></div>
                            </div>
                        </div>

                        <div class="d-insight">
                            <div class="d-insight-head">
                                <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
                                <div>
                                    <strong style="color:#0e1526;font-size:15px">Ivy</strong>
                                    <span style="font-weight:500;color:#4d5566;font-size:13px">Je AI-verkoopassistent</span>
                                </div>
                            </div>
                            <p class="d-prose" style="margin-top:10px;color:#0e1526" x-text="step === 7 && ! canPublish ? (loggedIn ? 'Bijna. Loop de punten met een uitroepteken nog even na, dan kan je kavel live.' : 'Je concept blijft bewaard. Log in met je zakelijke account, dan zetten we hem samen live.') : ({{ Js::from($ivyLines) }})[step]">{{ $ivyLines[0] }}</p>
                        </div>

                        <div class="d-card" style="padding:16px">
                            <h2 class="d-h3" style="font-size:15px">Compleetheid</h2>
                            <div class="d-depth-bar" style="height:8px;border-radius:4px;margin-top:10px" role="progressbar" :aria-valuenow="completeness.score" aria-valuemin="0" aria-valuemax="100">
                                <i style="transition:width .5s;border-radius:4px" :style="'width:' + completeness.score + '%'"></i>
                            </div>
                            <p class="tabular" style="margin:10px 0 0;font-size:16px;font-weight:800" x-text="completeness.score + '%'"></p>
                            <p class="d-sub" x-text="completeness.hint"></p>

                            <div class="fn-divide" x-show="completeness.missing.length" x-cloak>
                                <h3 style="margin:0;font-size:13px;font-weight:700">Wat nog ontbreekt</h3>
                                <ul class="fn-missing">
                                    <template x-for="key in completeness.missing" :key="key">
                                        <li x-text="({{ Js::from(__('funnel.missing')) }})[key] ?? key"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</x-layouts.funnel>
