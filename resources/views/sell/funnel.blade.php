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
    $vatPercent = \App\Support\Money\Money::vatRatePercent();
    $conditionLabels = collect(\App\Enums\LotCondition::cases())->mapWithKeys(fn ($condition) => [$condition->value => $condition->label()])->all();
    // De knop onderaan zegt wat er na deze stap komt.
    $nextLabels = [0 => 'Volgende: foto\'s', 1 => $ivyEnabled ? 'Laat Ivy kijken' : 'Volgende: advertentie', 2 => 'Door naar de advertentie', 3 => 'Volgende: verkoopvorm', 4 => 'Volgende: promotie', 5 => 'Door naar de kosten', 6 => 'Laatste controle'];
@endphp

{{--
    Kavel plaatsen in acht stappen. De opzet volgt het dashboard: een navy kop,
    daaronder losse kaarten op een zachte vloer. Per stap staat elk onderwerp
    in een eigen kaart, de uitleg staat achter een vraagteken en bijzaken
    (foto maken, barcode, typeplaat, dicteren) zijn pictogramknoppen. Er is
    per scherm één blauwe knop: verder. Het gedrag staat in
    resources/js/funnel.js.
--}}
<x-layouts.funnel title="Kavel plaatsen" description="Plaats je kavel in acht stappen. Geen account nodig om te beginnen: je ziet eerst wat Ivy voor je maakt.">
    <div class="fn-page" x-data="funnel({{ Js::from($initial) }}, {{ $feePercentage }}, {{ Js::from($schemas) }})">
        <div class="fn-card">

            {{-- De navy kop: het concept, de andere concepten, opslaan en sluiten, en de titel van de stap. --}}
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

                        {{-- Conceptoverzicht (briefing sectie 5): hervatten, nieuw beginnen of opruimen. --}}
                        @auth
                            @php($deleteUrl = route('dashboard.lots.destroy-draft', ['lot' => '__LOT__']))
                            <details class="fn-draftmenu" x-data @click.outside="$el.removeAttribute('open')">
                                <summary class="fn-head-btn is-quiet">{{ match ($drafts->count()) { 0 => 'Concepten', 1 => 'Nog één ander concept', default => 'Nog '.$drafts->count().' andere concepten' } }}</summary>
                                <div class="fn-draftmenu-panel">
                                    @if ($drafts->isNotEmpty())
                                        <ul>
                                            @foreach ($drafts as $other)
                                                <li>
                                                    <a href="{{ route('sell', ['concept' => $other->id]) }}">
                                                        <strong>{{ $other->title ?: 'Zonder titel' }}</strong>
                                                        <span class="tabular">#{{ $other->public_id }} · {{ $other->completeness_score }}% · {{ $other->updated_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</span>
                                                    </a>
                                                    <form method="POST" action="{{ route('dashboard.lots.destroy-draft', $other) }}" onsubmit="return confirm('Dit concept verwijderen? Dat kan niet ongedaan worden gemaakt.')">
                                                        @csrf
                                                        <input type="hidden" name="terug" value="verkopen">
                                                        <input type="hidden" name="concept" :value="draft?.id ?? ''">
                                                        <button type="submit" class="d-iconbtn is-danger" data-tip="Verwijderen" aria-label="Concept {{ $other->title ?: 'zonder titel' }} verwijderen"><x-v4.icon name="trash" :size="16" stroke="2" /></button>
                                                    </form>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                    <div class="fn-draftmenu-actions">
                                        <a href="{{ route('sell', ['nieuw' => 1]) }}" class="d-btn d-btn-secondary d-btn-sm">Nieuw concept</a>
                                        <form method="POST" x-show="draft" :action="@js($deleteUrl).replace('__LOT__', draft?.id)" onsubmit="return confirm('Dit concept verwijderen? Dat kan niet ongedaan worden gemaakt.')">
                                            @csrf
                                            <input type="hidden" name="terug" value="verkopen">
                                            <button type="submit" class="d-btn d-btn-secondary d-btn-sm is-danger">Dit concept verwijderen</button>
                                        </form>
                                    </div>
                                </div>
                            </details>
                        @endauth

                        <a class="fn-head-btn" href="{{ $closeUrl }}" @click.prevent="flush().finally(() => window.location.assign(@js($closeUrl)))">Opslaan en sluiten</a>
                    </div>
                </div>
                <p class="fn-kicker">Kavel plaatsen · stap <span x-text="step + 1">1</span> van 8</p>
                <h1 x-text="({{ Js::from($titles) }})[step]">{{ $titles[0] }}</h1>
                <p class="fn-lead" x-text="({{ Js::from($leads) }})[step]">{{ $leads[0] }}</p>
            </header>

            <div class="fn-body">

                {{-- De acht stappen op een rij (briefing 13.4): klikbaar; wat achter je ligt krijgt een vinkje. --}}
                <nav aria-label="Stappen" class="fn-stepper">
                    <template x-for="(name, index) in steps" :key="index">
                        <button type="button" @click="step = index" class="fn-stepper-item"
                                :class="{ 'is-on': step === index, 'is-done': index < step }"
                                :aria-current="step === index ? 'step' : null">
                            <span class="dot" aria-hidden="true">
                                <svg x-show="index < step" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                                <span x-show="index >= step" x-text="index + 1"></span>
                            </span>
                            <span class="name" x-text="name"></span>
                        </button>
                    </template>
                </nav>

                <div class="fn-grid">
                    <div class="fn-main">

                        {{-- 1 · Wat ------------------------------------------------- --}}
                        <section x-show="step === 0" x-cloak aria-label="Wat wil je verkopen" class="d-stack">
                            @if ($resumed)
                                <div class="fn-resume">
                                    <span><strong>Je gaat verder met een concept</strong>{{ $resumed->title ? ': '.$resumed->title : '' }} · {{ $resumed->updated_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }} bijgewerkt.</span>
                                    <a href="{{ route('sell', ['nieuw' => 1]) }}" class="d-btn d-btn-secondary d-btn-sm d-btn-sq">Begin een nieuw concept</a>
                                </div>
                            @endif
                            {{-- Hoeveel: invoer voor Ivy. De echte hoeveelheid zet de verkoper in stap 4. --}}
                            <div class="fn-block d-stack">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Hoeveel verkoop je?</h2>
                                    <x-dashboard.help text="Dit helpt Ivy je foto's te lezen: één object, of een partij. Het aantal en de eenheid vul je later in bij de advertentie." />
                                </div>
                                <div class="fn-options">
                                    <template x-for="option in [{scope: 'single', icon: 'box', label: 'Eén object', hint: 'Een machine, een meubelstuk'}, {scope: 'multiple', icon: 'layers', label: 'Meerdere objecten', hint: 'Een partij of een set'}, {scope: 'inventory', icon: 'layout', label: 'Hele inventaris', hint: 'Een complete veiling'}]" :key="option.scope">
                                        <button type="button" class="fn-option"
                                                :class="meta.scope === option.scope && 'is-on'"
                                                :aria-pressed="meta.scope === option.scope"
                                                @click="set('scope', option.scope)">
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
                                    <p class="fn-note">
                                        <span><strong>Verkopen op Aucvy is alleen voor bedrijven.</strong> Geen account nodig om een concept te maken. Om te publiceren heb je een geverifieerd zakelijk account nodig.</span>
                                    </p>
                                @endguest

                                <p class="fn-note" x-show="['multiple', 'inventory'].includes(meta.scope)" x-cloak>
                                    <span>
                                        <strong>Voeg foto's toe van alles wat je verkoopt.</strong>
                                        Ivy zet de foto's per product bij elkaar en maakt van elk product een eigen kavel met een eigen advertentie.
                                        @auth
                                            Bundelen tot één veiling met één sluitschema doe je daarna bij <a class="d-link" href="{{ route('dashboard.sales') }}">Veilingen</a>; heel veel kavels gaan sneller via <a class="d-link" href="{{ route('dashboard.import') }}">de import</a>.
                                        @else
                                            Bundelen tot één veiling doe je na het inloggen bij Veilingen.
                                        @endauth
                                    </span>
                                </p>
                            </div>

                            <div class="fn-block">
                                <div class="d-titleline">
                                    <h2 class="d-h2"><label for="fn-words">Vertel wat het is</label></h2>
                                    <x-dashboard.help text="Ivy leest dit samen met je foto's. Vertel wat een foto niet laat zien: hoe oud het is, hoe vaak gebruikt, waarom je het verkoopt. Met de microfoon kun je het ook inspreken." />
                                </div>
                                <p class="d-lead">Eén zin is genoeg. Dit is nog niet je advertentie: die schrijft Nova straks, en jij keurt hem goed.</p>
                                <div class="fn-textwrap">
                                    <textarea id="fn-words" rows="3" placeholder="Bijvoorbeeld: gaming-pc, een jaar oud, weinig gebruikt." class="d-textarea"
                                              :value="meta.intake ?? ''"
                                              @input="set('intake', $event.target.value)"></textarea>
                                    {{-- Dicteren via de spraakherkenning van de browser (briefing stap 1: spraak). --}}
                                    <button type="button" class="d-iconbtn" x-show="canDictate" x-cloak data-tip="Dicteren"
                                            :class="dictating === 'intake' && 'is-rec'" :data-tip="dictating === 'intake' ? 'Stop met dicteren' : 'Dicteren'"
                                            :aria-label="dictating === 'intake' ? 'Stop met dicteren' : 'Dicteren'" @click="dictate('intake')">
                                        <x-v4.icon name="mic" :size="17" stroke="2" />
                                    </button>
                                </div>
                            </div>
                        </section>

                        {{-- 2 · Aanleveren ------------------------------------------ --}}
                        <section x-show="step === 1" x-cloak aria-label="Foto's en documenten" class="d-stack">
                            <div class="fn-block d-stack">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Foto's</h2>
                                    <span class="d-count tabular" x-show="draft?.media.length" x-cloak x-text="draft?.media.length ?? 0"></span>
                                    <x-dashboard.help text="JPG, PNG of WebP, maximaal 10 MB per stuk. De hoofdfoto is wat kopers in de lijst zien. Een foto van de barcode of de typeplaat vult merk, model en bouwjaar voor je in; die foto blijft intern." />
                                </div>

                                {{-- Slepen, kiezen of fotograferen; voortgang per bestand (briefing sectie 5, stap 1). --}}
                                <div class="fn-drop" :class="dragOver && 'is-over'" x-data="{ gtin: false }"
                                     @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false"
                                     @drop.prevent="uploadPhotos([...$event.dataTransfer.files])">
                                    <strong>Sleep je foto's hierheen</strong>
                                    <div class="fn-drop-actions">
                                        <label class="d-btn d-btn-secondary d-btn-sq" style="cursor:pointer">
                                            Foto's kiezen
                                            <input type="file" accept="image/*" multiple class="sr-only"
                                                   @change="uploadPhotos([...$event.target.files]); $event.target.value = ''">
                                        </label>
                                        <label class="d-iconbtn" data-tip="Foto maken">
                                            <x-v4.icon name="camera" :size="18" stroke="2" />
                                            <span class="sr-only">Foto maken</span>
                                            <input type="file" accept="image/*" capture="environment" class="sr-only"
                                                   @change="uploadPhotos([...$event.target.files]); $event.target.value = ''">
                                        </label>
                                        {{-- Barcode: de lezer van de browser waar die er is, anders leest Ivy de foto (briefing: scanner voor alle browsers). --}}
                                        <label class="d-iconbtn" data-tip="Barcode scannen">
                                            <x-v4.icon name="barcode" :size="18" stroke="2" />
                                            <span class="sr-only">Barcode scannen</span>
                                            <input type="file" accept="image/*" capture="environment" class="sr-only"
                                                   @change="scanBarcode($event.target.files[0]); $event.target.value = ''">
                                        </label>
                                        {{-- Typeplaat: Ivy leest merk, model, bouwjaar, vermogen en serienummer. De foto blijft intern. --}}
                                        <label class="d-iconbtn" data-tip="Typeplaat lezen">
                                            <x-v4.icon name="tag" :size="18" stroke="2" />
                                            <span class="sr-only">Typeplaat lezen</span>
                                            <input type="file" accept="image/*" capture="environment" class="sr-only"
                                                   @change="scanNameplate($event.target.files[0]); $event.target.value = ''">
                                        </label>
                                    </div>
                                    <button type="button" class="d-link" x-show="! gtin" @click="gtin = true; $nextTick(() => $refs.gtin.focus())">Of typ de productcode (EAN)</button>
                                    <form class="fn-gtin" @submit.prevent="submitGtin()" x-show="gtin" x-cloak>
                                        <label for="gtin-input" class="sr-only">Productcode (EAN)</label>
                                        <input id="gtin-input" x-ref="gtin" type="text" inputmode="numeric" x-model="scan.code" maxlength="20" placeholder="8712345678906" class="d-input" style="margin:0;width:180px;height:40px">
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

                                <ul class="photo-grid" x-show="draft?.media.length" x-cloak>
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
                            </div>

                            <div class="fn-block d-stack">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Documenten</h2>
                                    <span class="d-count tabular" x-show="draft?.documents.length" x-cloak x-text="draft?.documents.length ?? 0"></span>
                                    <x-dashboard.help text="Facturen en eigendomsbewijzen blijven privé. Per document kies je in het kaveldossier of kopers het via het oogje mogen inzien; downloaden kan niet. Documenten kunnen ook later." />
                                </div>

                                <div class="d-chips" x-data="{ kind: 'invoice' }">
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

                                <div class="fn-doclist" x-show="draft?.documents.length" x-cloak>
                                    <template x-for="doc in draft?.documents ?? []" :key="doc.id">
                                        <div>
                                            <strong x-text="doc.name"></strong>
                                            <span class="d-muted" x-text="({{ Js::from($documentKinds) }})[doc.kind] ?? doc.kind"></span>
                                        </div>
                                    </template>
                                </div>

                                {{-- De echtheid van documenten aanvinken (besluit Raph): alleen als er documenten zijn. --}}
                                <label class="fn-check" x-show="(draft?.documents?.length ?? 0) > 0" x-cloak>
                                    <input type="checkbox" :checked="meta.documents_authentic === true" @change="set('documents_authentic', $event.target.checked)">
                                    <span>
                                        <strong>De documenten die ik toevoeg zijn echt en volledig.</strong>
                                        <span>Nodig om te publiceren als je documenten toevoegt. Een vals document leidt tot verwijdering van de kavel en blokkade van het account.</span>
                                    </span>
                                </label>
                            </div>

                            <div class="fn-block d-stack">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Garantie en eigendom</h2>
                                    <x-dashboard.help text="Beide zijn nodig om te publiceren. Een eigendomsbewijs of factuur als document maakt je kavel sterker en geldt ook als verklaring." />
                                </div>

                                {{-- Garantie (publicatiecriterium, besluit Raph 2 november 2026). --}}
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

                                {{-- Eigendom (preflight, briefing sectie 5): verklaring of bewijs. --}}
                                <label class="fn-check">
                                    <input type="checkbox" :checked="meta.ownership_declared === true" @change="set('ownership_declared', $event.target.checked)">
                                    <span>
                                        <strong>Ik ben eigenaar van dit object, of gemachtigd om het te verkopen.</strong>
                                    </span>
                                </label>
                            </div>
                        </section>

                        {{-- 3 · Analyse --------------------------------------------- --}}
                        <section x-show="step === 2" x-cloak aria-label="Analyse" class="d-stack">
                            @if ($ivyEnabled)
                                {{-- Bij het openen van deze stap begint Ivy vanzelf, en opnieuw als de foto's of woorden veranderd zijn. --}}
                                <div class="fn-block d-stack" x-init="$watch('step', (value) => value === 2 && autoAnalyse())">
                                    <div class="fn-row">
                                        <div class="d-titleline">
                                            <h2 class="d-h2">Dit plaats je</h2>
                                            <x-dashboard.help text="Ivy herkent het object, Nova schrijft de advertentie en Atlas schat de waarde uit vergelijkbare verkopen. Een range, geen belofte. Wat jij straks aanpast, wint altijd." />
                                        </div>
                                        <button type="button" class="d-iconbtn" data-tip="Opnieuw laten kijken" aria-label="Opnieuw laten kijken"
                                                x-show="ivy.products.length && ! ivy.running && ! ivy.proposal" x-cloak @click="analyse()">
                                            <x-v4.icon name="refresh" :size="17" stroke="2" />
                                        </button>
                                    </div>

                                    {{-- Niets om naar te kijken: eerst foto's of een zin. --}}
                                    <div class="fn-note" x-show="! ivy.products.length && ! ivy.running && ! (draft?.media?.length || (meta.intake ?? '').trim())" x-cloak>
                                        <span><strong>Ivy heeft nog niets om naar te kijken.</strong> Voeg foto's toe of vertel in stap 1 wat het is.</span>
                                    </div>
                                    <div class="d-chips" x-show="! ivy.products.length && ! ivy.running" x-cloak>
                                        <button type="button" class="d-btn d-btn-secondary d-btn-sm d-btn-sq" x-show="! draft?.media?.length" @click="step = 1">Foto's toevoegen</button>
                                        <button type="button" class="d-btn d-btn-secondary d-btn-sm d-btn-sq" x-show="draft?.media?.length || (meta.intake ?? '').trim()" @click="analyse()">Laat Ivy kijken</button>
                                    </div>

                                    {{-- Wat voor het hele concept geldt: de foto's per product verdelen. --}}
                                    <ol class="fn-ivytasks" x-show="ivy.tasks.length" x-cloak aria-live="polite">
                                        <template x-for="item in ivy.tasks" :key="item.task">
                                            @include('sell.partials.ivy-task', ['conditionLabels' => $conditionLabels])
                                        </template>
                                    </ol>

                                    {{--
                                        Dan het rijtje af, product voor product. Wat aan de beurt is klapt
                                        open en doorloopt zijn stappen; daarna klapt het dicht en gaat het
                                        volgende open. De kop is de kavel zoals hij er nu voor staat: wat nog
                                        uitgezocht wordt is een skelet.
                                    --}}
                                    <div class="fn-products" x-show="ivy.products.length" x-cloak aria-live="polite">
                                        <template x-for="(product, index) in ivy.products" :key="product.id">
                                            <article class="fn-product" :class="['is-' + product.status, product.open && 'is-open']">
                                                <div class="fn-product-head">
                                                    <button type="button" class="fn-product-toggle" @click="product.open = ! product.open" :aria-expanded="product.open">
                                                        <span class="thumb" aria-hidden="true">
                                                            <template x-if="productView(product).cover"><img :src="productView(product).cover" alt=""></template>
                                                            <template x-if="! productView(product).cover"><x-v4.icon name="image" :size="20" stroke="1.6" /></template>
                                                        </span>
                                                        <span class="text">
                                                            <span class="sk fn-sk-title" x-show="productPending(product, 'draft_listing')"></span>
                                                            <strong x-show="! productPending(product, 'draft_listing')" x-text="productView(product).title || 'Nog zonder titel'"></strong>
                                                            <span class="sk fn-sk-line" x-show="productPending(product, 'recognise_lot')"></span>
                                                            <span class="d-muted" x-show="! productPending(product, 'recognise_lot')"
                                                                  x-text="(ivy.products.length > 1 ? 'Kavel ' + (index + 1) + ' van ' + ivy.products.length + (productView(product).subtitle ? ' · ' : '') : '') + (productView(product).subtitle || (ivy.products.length > 1 ? '' : 'Nog niet herkend'))"></span>
                                                        </span>
                                                        <span class="amount">
                                                            <span class="sk fn-sk-amount" x-show="productPending(product, 'estimate_value')"></span>
                                                            <b class="tabular" x-show="! productPending(product, 'estimate_value')" x-text="productView(product).value_min_cents != null ? money(productView(product).value_min_cents) + ' – ' + money(productView(product).value_max_cents) : 'Nog geen indicatie'"></b>
                                                            <span>Waarde-indicatie van Atlas</span>
                                                        </span>
                                                        <span class="state" aria-hidden="true">
                                                            <span class="spin" x-show="product.status === 'busy'"></span>
                                                            <span x-show="product.status === 'done'"><x-v4.icon name="check" :size="14" stroke="3" /></span>
                                                            <span x-show="product.status === 'failed'">!</span>
                                                            <span class="chev" x-show="product.status === 'waiting'"><x-v4.icon name="chevron-down" :size="16" stroke="2" /></span>
                                                        </span>
                                                    </button>
                                                    {{-- Een ander kavel uit de inventaris: openen om de tekst na te kijken en een prijs te kiezen. --}}
                                                    <a class="d-iconbtn" x-show="! product.own" :href="@js(route('sell')) + '?concept=' + product.id" data-tip="Openen" aria-label="Kavel openen"><x-v4.icon name="pencil" :size="17" stroke="2" /></a>
                                                </div>
                                                <ol class="fn-ivytasks" x-show="product.open && product.tasks.length">
                                                    <template x-for="item in product.tasks" :key="item.task">
                                                        @include('sell.partials.ivy-task', ['product' => 'product', 'conditionLabels' => $conditionLabels])
                                                    </template>
                                                </ol>
                                            </article>
                                        </template>
                                    </div>

                                    {{-- Ivy ziet dat de woorden en de foto's niet bij elkaar passen: dat hoort de verkoper, niet de koper. --}}
                                    <p class="fn-note is-warn" x-show="ivy.note && ! ivy.running" x-cloak>
                                        <span><strong>Ivy zag iets.</strong> <span x-text="ivy.note"></span></span>
                                    </p>

                                    <p class="fn-note is-warn" x-show="ivyStale" x-cloak>
                                        <span><strong>Je foto's of woorden zijn veranderd.</strong>
                                            <button type="button" class="d-link" @click="analyse()">Laat Ivy opnieuw kijken</button>, dan passen titel en tekst er weer bij.</span>
                                    </p>

                                    <p class="fn-note is-ok" x-show="! ivy.running && ivyDone && ! ivyStale && ! ivy.proposal" x-cloak>
                                        <span><strong>Ivy is klaar.</strong> Kijk in de volgende stap na wat ze invulde. Wat jij aanpast, wint altijd.</span>
                                    </p>

                                    {{-- Meer aangeleverd = scherpere schatting: dat moment benoemen we expliciet (preview v4). --}}
                                    <p x-show="ivy.sharpened" x-cloak class="fn-note is-ok">
                                        <span>Je waarde-indicatie werd scherper:
                                            <b class="tabular" x-text="money(draft?.value_min_cents) + ' – ' + money(draft?.value_max_cents)"></b></span>
                                    </p>
                                </div>
                            @else
                                {{-- Noodrem om (hfst. 8): geen knop die niets gaat doen, wel de eerlijke handmatige route. --}}
                                <div class="fn-block">
                                    <p class="fn-note">
                                        <span><strong>Ivy staat op dit moment uit.</strong> Je vult de advertentie zelf in bij de volgende stap. Dat werkt net zo goed.</span>
                                    </p>
                                </div>
                            @endif
                        </section>

                        {{-- 4 · Advertentie ----------------------------------------- --}}
                        <section x-show="step === 3" x-cloak aria-label="Advertentie" class="d-stack">
                            <div class="fn-block d-stack">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Tekst</h2>
                                    <x-dashboard.help text="Eerlijk over gebreken: dat verkoopt aantoonbaar beter. Onder elk veld zie je of Ivy of jij het invulde. Wat jij aanpast, wint altijd." />
                                </div>

                                <label class="d-field">Titel
                                    <input type="text" maxlength="150" placeholder="Complete designkeuken met Siemens-apparatuur" class="d-input"
                                           :value="draft?.title ?? ''" @input="set('title', $event.target.value)">
                                    <x-funnel.provenance field="title" />
                                </label>

                                <div class="d-field"><label for="fn-description">Omschrijving</label>
                                    <div class="fn-textwrap" style="margin-top:6px">
                                        <textarea id="fn-description" rows="6" class="d-textarea" placeholder="Eerlijk over gebreken: dat verkoopt aantoonbaar beter."
                                                  :value="draft?.description ?? ''" @input="set('description', $event.target.value)"></textarea>
                                        <button type="button" class="d-iconbtn" x-show="canDictate" x-cloak data-tip="Dicteren"
                                                :class="dictating === 'description' && 'is-rec'" :data-tip="dictating === 'description' ? 'Stop met dicteren' : 'Dicteren'"
                                                :aria-label="dictating === 'description' ? 'Stop met dicteren' : 'Dicteren'" @click="dictate('description')">
                                            <x-v4.icon name="mic" :size="17" stroke="2" />
                                        </button>
                                    </div>
                                    <x-funnel.provenance field="description" />
                                </div>

                                <p class="fn-note is-warn" x-show="unconfirmed.length" x-cloak>
                                    <span>Ivy heeft velden ingevuld waar ze niet zeker van is. Kijk ze na: corrigeer wat niet klopt, of
                                        <button type="button" class="d-link" @click="confirmAll()">bevestig alles in één keer</button>.</span>
                                </p>
                            </div>

                            <div class="fn-block d-stack">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Gegevens</h2>
                                </div>
                                <div class="fn-fields is-three">
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
                                </div>
                            </div>

                            {{-- Kenmerken: de velden uit het schema van de categorie, plus wat Ivy of jijzelf er los bij zet (briefing sectie 5, stap 4). --}}
                            <div class="fn-block specs">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Kenmerken</h2>
                                    <x-dashboard.help text="Ivy weet per categorie welke kenmerken kopers verwachten. Mis je er een, dan zet je hem er zelf bij." />
                                </div>
                                <p class="fn-note" x-show="! draft?.category_id" x-cloak><span>Kies eerst een categorie; dan weet Ivy welke kenmerken kopers verwachten.</span></p>
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
                                    <input type="text" x-model="newSpecKey" maxlength="40" placeholder="Nog een kenmerk, bijvoorbeeld: draagvermogen" class="d-input" style="margin:0" aria-label="Nog een kenmerk">
                                    <button type="submit" class="d-iconbtn" data-tip="Kenmerk toevoegen" aria-label="Kenmerk toevoegen"><x-v4.icon name="plus" :size="18" stroke="2.2" /></button>
                                </form>
                            </div>
                        </section>

                        {{-- 5 · Vorm ------------------------------------------------ --}}
                        <section x-show="step === 4" x-cloak aria-label="Verkoopvorm" class="d-stack">
                            <div class="fn-block">
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
                            </div>

                            <div class="fn-block d-stack" x-show="meta.sale_type" x-cloak>
                                <div class="d-titleline">
                                    <h2 class="d-h2">Prijzen</h2>
                                    <x-dashboard.help text="De reserveprijs is niet zichtbaar voor kopers; ze zien alleen óf hij gehaald is. Een veiling verlengt bij een laat bod." />
                                </div>

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
                                        <span class="d-hint">Daaronder verkoop je niet.</span>
                                    </label>
                                    <label class="d-field">Looptijd
                                        <select class="d-input" :value="meta.duration_days ?? 7" @change="set('duration_days', Number($event.target.value))">
                                            <option value="3">3 dagen</option>
                                            <option value="7">7 dagen</option>
                                            <option value="14">14 dagen</option>
                                        </select>
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

                                {{-- De prijzencontrole uit de preflight, hier al zichtbaar; alleen als er iets niet klopt. --}}
                                <template x-for="check in preflight.checks.filter((item) => item.key === 'price_order' && ! item.ok)" :key="check.key">
                                    <p class="fn-note is-warn"><span><strong x-text="check.label"></strong> <span x-text="check.hint"></span></span></p>
                                </template>
                            </div>

                            {{-- Marge of btw (besluit Don, vraag 3): de verkoper kiest per kavel. --}}
                            <fieldset class="fn-block fn-fieldset">
                                <legend class="sr-only">Btw op deze kavel</legend>
                                <div class="d-titleline" aria-hidden="true">
                                    <h2 class="d-h2">Btw op deze kavel</h2>
                                </div>
                                <div class="fn-options is-two">
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
                            <div class="fn-block">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Altijd inbegrepen</h2>
                                </div>
                                <div class="fn-checklist">
                                    <div><span class="ok" aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></span><span><strong>Aucvy zoekresultaten</strong><span>Je kavel is vindbaar voor alle kopers.</span></span></div>
                                    <div><span class="ok" aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></span><span><strong>Categoriepagina</strong><span x-text="'Bij ' + (({{ Js::from($categoryNames) }})[draft?.category_id] ?? 'de categorie die je kiest') + '.'"></span></span></div>
                                    <div><span class="ok" aria-hidden="true"><x-v4.icon name="check" :size="14" stroke="3" /></span><span><strong>Alerts van kopers</strong><span>Kopers met een passende zoekopdracht krijgen een seintje.</span></span></div>
                                </div>
                            </div>

                            {{-- Verspreiden (briefing nieuwe functies): de deelkit dekt elk kanaal vanaf dag één; koppelingen komen per kanaal. --}}
                            <div class="fn-block">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Extra kanalen</h2>
                                    <x-dashboard.help text="Social push: één planning met drie momenten (start, dag voor sluiting, laatste uur) die je in één keer goedkeurt. Een gekoppeld kanaal plaatst Aucvy zelf; koppelen doe je bij Instellingen, Koppelingen." />
                                </div>
                                <p class="d-lead">Zodra de kavel live staat, krijg je per kanaal een tekst van Nova, een beeld in drie formaten en een link met herkomst. Je plaatst zelf en ziet de klikken per kanaal.</p>
                                <div class="fn-channels">
                                    @foreach (['Marktplaats', 'Facebook', 'Instagram', 'LinkedIn', 'WhatsApp', 'Mail'] as $channel)
                                        <div><span>{{ $channel }}</span><span class="d-tag tone-grey">Deelkit</span></div>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        {{-- 7 · Kosten ---------------------------------------------- --}}
                        <section x-show="step === 6" x-cloak aria-label="Kosten" class="d-stack">
                            <div class="fn-block">
                                <div class="fn-stats">
                                    <div><span>Plaatsen</span><b>Gratis</b><small>geen kosten vooraf</small></div>
                                    <div><span>Succesfee</span><b class="tabular" x-text="fee.toString().replace('.', ',') + '%'"></b><small>alleen bij verkoop</small></div>
                                    <div><span>Opgeld koper</span><b>€ 0</b><small>kopers betalen geen opgeld</small></div>
                                </div>
                            </div>

                            {{-- Rekenvoorbeeld op de startprijs, de vaste prijs of anders € 3.000: geen belofte, wel de som. --}}
                            <div class="fn-block" x-data="{ get basis() { return meta.buy_now_price_cents || meta.start_price_cents || meta.dutch_start_cents || 300000; } }">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Rekenvoorbeeld</h2>
                                    <x-dashboard.help text="Het tarief is een staffel op je omzet van de laatste twaalf maanden en ligt vast op het moment van verkoop. Het voorbeeld rekent met je startprijs of vaste prijs; het is geen belofte." />
                                </div>
                                <p class="d-lead">Bij een eindbod van <b class="tabular" x-text="money(basis)"></b> ontvang jij <b class="tabular" x-text="money(basis - Math.round(basis * fee / 100) - Math.round(Math.round(basis * fee / 100) * {{ $vatPercent }} / 100))"></b>.</p>
                                <div class="d-rows">
                                    <div class="d-row"><span>Eindbod</span><strong x-text="money(basis)"></strong></div>
                                    <div class="d-row"><span x-text="'Succesfee (' + fee.toString().replace('.', ',') + '%)'"></span><strong x-text="'- ' + money(Math.round(basis * fee / 100))"></strong></div>
                                    <div class="d-row"><span>Btw over succesfee ({{ rtrim(rtrim(number_format($vatPercent, 1, ',', ''), '0'), ',') }}%)</span><strong x-text="'- ' + money(Math.round(Math.round(basis * fee / 100) * {{ $vatPercent }} / 100))"></strong></div>
                                    <div class="d-row" style="font-size:15px;font-weight:800"><span>Jij ontvangt</span><strong x-text="money(basis - Math.round(basis * fee / 100) - Math.round(Math.round(basis * fee / 100) * {{ $vatPercent }} / 100))"></strong></div>
                                </div>
                            </div>

                            <div class="fn-block d-stack">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Bezichtigen en ophalen</h2>
                                    <x-dashboard.help text="Bij een kijkdag stellen kopers een moment voor; jij bevestigt, daarna krijgen ze het adres." />
                                </div>
                                <label class="fn-check">
                                    <input type="checkbox" :checked="draft?.viewing_available" @change="set('viewing_available', $event.target.checked)">
                                    <span><strong>Bezichtiging mogelijk (kijkdag)</strong></span>
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
                        </section>

                        {{-- 8 · Live ------------------------------------------------ --}}
                        <section x-show="step === 7" x-cloak aria-label="Controleren en publiceren" class="d-stack">
                            <div class="fn-block d-stack">
                                <p class="fn-note is-ok" x-show="loggedIn && canPublish" x-cloak>
                                    <span><strong>Je kavel staat klaar.</strong> Klopt alles, dan staat hij meteen live. Anders hoor je wat er nog nodig is.</span>
                                </p>
                                <p class="fn-note is-warn" x-show="! loggedIn" x-cloak>
                                    <span><strong>Nog één stap: je zakelijke account.</strong> Je concept is bewaard en gaat mee. Publiceren kan alleen met een geverifieerd zakelijk account. Heb je er al een? Log dan in.</span>
                                </p>
                                <p class="fn-note is-warn" x-show="loggedIn && ! canPublish" x-cloak>
                                    <span><strong>Nog niet alles is in orde.</strong> De punten hieronder moeten eerst af.</span>
                                </p>

                                <article class="fn-lotline">
                                    <span class="thumb" aria-hidden="true">
                                        <template x-if="draft?.media?.length"><img :src="draft.media[0].url" alt=""></template>
                                        <template x-if="! draft?.media?.length"><x-v4.icon name="image" :size="22" stroke="1.6" /></template>
                                    </span>
                                    <div class="text">
                                        <strong x-text="draft?.title || 'Nog zonder titel'"></strong>
                                        <span class="d-muted" x-text="[({auction: 'Veiling', dutch: 'Afslag', buy_now: 'Koop direct'})[meta.sale_type], (meta.duration_days ?? 7) + ' dagen', ({{ Js::from($categoryNames) }})[draft?.category_id]].filter(Boolean).join(' · ')"></span>
                                    </div>
                                    <div class="amount" x-show="meta.start_price_cents || meta.dutch_start_cents || meta.buy_now_price_cents">
                                        <b class="tabular" x-text="money(meta.buy_now_price_cents && meta.sale_type === 'buy_now' ? meta.buy_now_price_cents : (meta.sale_type === 'dutch' ? meta.dutch_start_cents : meta.start_price_cents))"></b>
                                        <span x-text="meta.sale_type === 'buy_now' ? 'Vaste prijs' : 'Startprijs'"></span>
                                    </div>
                                </article>
                            </div>

                            {{-- Preflight (briefing sectie 5, stap 8): dezelfde lijst als de server afdwingt. Wat nog moet staat open, wat in orde is klapt uit. --}}
                            <div class="fn-block" x-data="{ showOk: false }">
                                <div class="d-titleline">
                                    <h2 class="d-h2">Controle vóór publicatie</h2>
                                    <span class="d-count tabular" x-text="preflight.checks.filter((item) => item.ok).length + ' van ' + preflight.checks.length"></span>
                                    <x-dashboard.help text="Dit is dezelfde lijst die Aucvy bij het publiceren afdwingt. Een rood punt is verplicht, een oranje punt is aanbevolen." />
                                </div>
                                <div class="fn-checklist" aria-label="Controlepunten">
                                    <template x-for="check in preflight.checks.filter((item) => ! item.ok)" :key="check.key">
                                        <div>
                                            <span :class="check.blocking ? 'fail' : 'warn'" aria-hidden="true">!</span>
                                            <span>
                                                <strong x-text="check.label"></strong>
                                                <span x-text="check.hint ?? (check.blocking ? 'Nodig om te publiceren' : 'Aanbevolen, niet verplicht')"></span>
                                            </span>
                                            <template x-if="check.route">
                                                <a :href="check.route" class="d-iconbtn" data-tip="Regelen" :aria-label="check.label + ' regelen'"><x-v4.icon name="pencil" :size="17" stroke="2" /></a>
                                            </template>
                                            <template x-if="! check.route && check.key !== 'account'">
                                                <button type="button" class="d-iconbtn" data-tip="Aanpassen" :aria-label="check.label + ' aanpassen'" @click="step = check.step"><x-v4.icon name="pencil" :size="17" stroke="2" /></button>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-for="check in preflight.checks.filter((item) => item.ok)" :key="check.key">
                                        <div x-show="showOk">
                                            <span class="ok" aria-hidden="true">✓</span>
                                            <span><strong x-text="check.label"></strong><span>In orde</span></span>
                                        </div>
                                    </template>
                                </div>
                                <div class="d-more" x-show="preflight.checks.some((item) => item.ok)">
                                    <button type="button" class="d-btn d-btn-secondary d-btn-sm" :aria-expanded="showOk" @click="showOk = ! showOk"
                                            x-text="showOk ? 'Verberg wat in orde is' : 'Toon wat in orde is (' + preflight.checks.filter((item) => item.ok).length + ')'">Toon wat in orde is</button>
                                </div>
                            </div>
                        </section>

                        <p x-show="error" x-cloak role="alert" class="d-soft is-err" style="margin:0" x-text="error"></p>

                        {{-- Navigatie: terug werkt, elke stap is omkeerbaar. --}}
                        <div class="fn-nav">
                            <button type="button" class="d-btn d-btn-secondary d-btn-sq" x-show="step > 0" @click="step--">Terug</button>
                            <span class="d-muted" x-show="saving" x-cloak>Opslaan…</span>
                            <span class="d-muted" style="color:#177249" x-show="saved" x-cloak>Opgeslagen</span>
                            <button type="button" class="d-btn d-btn-sq" style="margin-left:auto" x-show="step < 7 && ! (step === 2 && ivy.proposal)" @click="step++"
                                    x-text="({{ Js::from($nextLabels) }})[step] ?? 'Volgende'">{{ $nextLabels[0] }}</button>
                            <button type="button" class="d-btn d-btn-sq" style="margin-left:auto" x-show="step === 7" x-cloak @click="publish()" :disabled="! canPublish">
                                <span x-text="loggedIn ? 'Publiceer deze kavel' : 'Inloggen en publiceren'">Publiceer deze kavel</span>
                            </button>
                        </div>
                    </div>

                    {{-- Zijkolom: wat bij de stap hoort, dan Ivy en de compleetheidsmeter (briefing 13.4). --}}
                    <aside class="fn-aside">

                        {{-- Stap 4: zo komt hij in de lijst te staan. --}}
                        <div class="fn-preview" x-show="step === 3" x-cloak>
                            <div class="fn-preview-media" x-show="draft?.media?.length"><template x-if="draft?.media?.length"><img :src="draft.media[0].url" alt=""></template></div>
                            <div class="fn-preview-body">
                                <span class="d-tag tone-warn">Voorbeeld</span>
                                <strong x-text="draft?.title || 'Je titel komt hier'"></strong>
                                <span class="d-muted" x-show="draft?.value_min_cents != null" x-text="'Waarde-indicatie ' + money(draft?.value_min_cents) + ' – ' + money(draft?.value_max_cents)"></span>
                            </div>
                        </div>

                        {{-- Stap 4: zo ziet het eruit in Google: slug en meta-omschrijving uit titel en tekst. --}}
                        <div class="fn-sidecard" x-show="step === 3 && draft?.title" x-cloak>
                            <h2 class="d-h3">Zo sta je in Google</h2>
                            <div class="seo-preview">
                                <span class="seo-url">{{ rtrim(config('app.url'), '/') }}/kavel/<span x-text="seoSlug"></span></span>
                                <span class="seo-title" x-text="draft?.title"></span>
                                <span class="seo-desc" x-text="seoDescription || 'Schrijf een omschrijving; de eerste 155 tekens worden de samenvatting in Google.'"></span>
                            </div>
                        </div>

                        {{-- Stap 5: de waarde-indicatie van Atlas naast de prijzen. --}}
                        <div class="fn-sidecard" x-show="step === 4 && draft?.value_min_cents != null" x-cloak>
                            <div class="d-titleline">
                                <h2 class="d-h3">Waarde-indicatie van Atlas</h2>
                                <x-dashboard.help class="is-end" text="Uit vergelijkbare verkopen. Een range, geen belofte." />
                            </div>
                            <div class="d-big tabular" style="font-size:20px;margin-top:6px" x-text="money(draft?.value_min_cents) + ' – ' + money(draft?.value_max_cents)"></div>
                        </div>

                        {{-- Stap 6: zo staat hij in de resultaten. --}}
                        <div class="fn-sidecard" x-show="step === 5" x-cloak>
                            <h2 class="d-h3">Zo staat hij in de resultaten</h2>
                            <article class="fn-lotline is-small" style="margin-top:12px">
                                <span class="thumb" aria-hidden="true">
                                    <template x-if="draft?.media?.length"><img :src="draft.media[0].url" alt=""></template>
                                    <template x-if="! draft?.media?.length"><x-v4.icon name="image" :size="20" stroke="1.6" /></template>
                                </span>
                                <div class="text">
                                    <strong x-text="draft?.title || 'Je titel komt hier'"></strong>
                                    <span class="d-muted" x-show="meta.start_price_cents || meta.dutch_start_cents || meta.buy_now_price_cents">
                                        <span x-text="meta.sale_type === 'buy_now' ? 'Vaste prijs' : 'Startprijs'"></span>
                                        <b class="tabular" style="color:#0e1526" x-text="money(meta.buy_now_price_cents && meta.sale_type === 'buy_now' ? meta.buy_now_price_cents : (meta.sale_type === 'dutch' ? meta.dutch_start_cents : meta.start_price_cents))"></b>
                                    </span>
                                </div>
                            </article>
                        </div>

                        {{-- Stap 8: de samenvatting. --}}
                        <div class="fn-sidecard" x-show="step === 7" x-cloak>
                            <h2 class="d-h3">Samenvatting</h2>
                            <div class="d-rows" style="margin-top:6px">
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

                        {{-- Compleetheid: een ring met het percentage, en wat er nog bij kan. --}}
                        <div class="fn-sidecard" x-data="{ allMissing: false }">
                            <div class="fn-complete">
                                <span class="d-ring" role="progressbar" :aria-valuenow="completeness.score" aria-valuemin="0" aria-valuemax="100" aria-label="Compleetheid">
                                    <svg viewBox="0 0 100 100" aria-hidden="true">
                                        <circle class="track" cx="50" cy="50" r="38"></circle>
                                        <circle class="value" cx="50" cy="50" r="38" pathLength="100" style="transition:stroke-dasharray .5s" :stroke-dasharray="Math.max(4, completeness.score) + ' 100'"></circle>
                                    </svg>
                                    <b class="d-ring-center tabular" x-text="completeness.score + '%'"></b>
                                </span>
                                <div>
                                    <h2 class="d-h3">Compleetheid</h2>
                                    <p class="d-sub" style="margin-top:4px" x-text="completeness.hint"></p>
                                </div>
                            </div>

                            <div class="fn-divide" x-show="completeness.missing.length" x-cloak>
                                <h3 style="margin:0;font-size:13px;font-weight:700">Wat nog ontbreekt</h3>
                                <ul class="fn-missing">
                                    <template x-for="(key, index) in completeness.missing" :key="key">
                                        <li x-show="allMissing || index < 3" x-text="({{ Js::from(__('funnel.missing')) }})[key] ?? key"></li>
                                    </template>
                                </ul>
                                <button type="button" class="d-link" style="margin-top:8px" x-show="! allMissing && completeness.missing.length > 3" @click="allMissing = true"
                                        x-text="'En nog ' + (completeness.missing.length - 3) + ' ' + (completeness.missing.length - 3 === 1 ? 'punt' : 'punten')"></button>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</x-layouts.funnel>
