@use('App\Enums\DocumentKind')
@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

@php
    $firstMedia = $lot->media->first()?->path;
    $shareImage = $firstMedia === null
        ? null
        : (Str::startsWith($firstMedia, ['http://', 'https://']) ? $firstMedia : asset($firstMedia));
    $isLive = $lot->status->isPublic() && $auction !== null && ! $auction->isClosed();
    $structured = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $lot->title,
        'description' => Str::limit($lot->description, 300),
        'sku' => $lot->public_id,
        'url' => route('lots.show', $lot),
        'image' => array_values(array_filter($lot->media->map(fn ($media) => Str::startsWith($media->path, ['http://', 'https://']) ? $media->path : asset($media->path))->all())),
        'brand' => $lot->brand ? ['@type' => 'Brand', 'name' => $lot->brand] : null,
        'category' => $lot->category?->name,
        'offers' => [
            '@type' => 'Offer',
            'url' => route('lots.show', $lot),
            'priceCurrency' => 'EUR',
            'price' => number_format($lot->currentPriceCents() / 100, 2, '.', ''),
            'availability' => $isLive ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'itemCondition' => 'https://schema.org/UsedCondition',
            'seller' => ['@type' => 'Organization', 'name' => $lot->seller?->publicName() ?? config('app.name')],
        ],
    ];
@endphp

@push('head')
    <script type="application/ld+json">{!! json_encode(array_filter($structured, fn ($value) => $value !== null), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

<x-layouts.public :title="$lot->title" :heading="$translation['title'] ?? $lot->title" :description="Str::limit($lot->description, 155)"
    :eyebrow="$lot->category?->name ?? 'Kavel'" :image="$shareImage" og-type="product" :canonical="route('lots.show', $lot)">
    {{-- Het kruimelpad staat in de paginakop; de titel is de kop zelf. --}}
    <x-slot:crumbs>
        <a href="{{ route('lots.index') }}">Veilingen</a>
        @if ($lot->category)
            @if ($lot->category->parent)
                <span aria-hidden="true">/</span>
                <a href="{{ route('lots.category', $lot->category->parent) }}">{{ $lot->category->parent->name }}</a>
            @endif
            <span aria-hidden="true">/</span>
            <a href="{{ route('lots.category', $lot->category) }}">{{ $lot->category->name }}</a>
        @endif
    </x-slot:crumbs>

    {{--
        Eén Alpine-component bezit de stand; alles eronder leest eruit.
        Dat voorkomt de klassieke fout waarbij het biedpaneel iets anders toont
        dan de teller ernaast (hfst. 11).
    --}}
    <div @if ($state) x-data="auction({{ Js::from($state) }})" @endif
         data-lot-nav
         @if ($nextLot) data-next="{{ route('lots.show', $nextLot) }}" @endif
         @if ($previousLot) data-prev="{{ route('lots.show', $previousLot) }}" @endif>

        @if ($nextLot || $previousLot)
            <p class="-mt-2 hidden items-center justify-end gap-1 text-xs text-aj-muted lg:flex" aria-hidden="true">
                Blader
                <kbd class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-1.5 py-0.5 font-sans font-bold">&larr;</kbd>
                <kbd class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-1.5 py-0.5 font-sans font-bold">&rarr;</kbd>
            </p>
        @endif

        {{--
            Prijswijzigingen worden voorgelezen, maar afgeknepen tot één melding
            per drie seconden. Elke bieding voorlezen maakt de pagina onbruikbaar
            met een schermlezer (hfst. 11).
        --}}
        <div aria-live="polite" class="sr-only" x-text="announcement"></div>

        {{--
            Op mobiel: foto's eerst, dan pas prijs en bod. Je moet eerst zien
            wát je koopt (hfst. 12). Op desktop staat het biedpaneel rechts.
        --}}
        <div class="mt-4 grid gap-8 lg:grid-cols-[1.6fr_1fr] lg:items-start">

            <div class="space-y-6">
                <x-lot.media-gallery :lot="$lot" />

                <div>
                    <ul class="flex flex-wrap gap-2 text-sm">
                        @if ($lot->location)
                            <li class="rounded-full bg-aj-bg px-3 py-1">{{ $lot->location->publicLabel() }}</li>
                        @endif
                        @if ($lot->transport_from_cents)
                            <li class="rounded-full bg-aj-bg px-3 py-1">
                                Transport vanaf {{ Money::format($lot->transport_from_cents) }}
                            </li>
                        @endif
                        <li class="rounded-full bg-aj-bg px-3 py-1">{{ $lot->condition?->label() }}</li>
                        @if ($lot->quantity > 1)
                            <li class="rounded-full bg-aj-bg px-3 py-1">{{ $lot->quantity }} × {{ config('listings.unit_options.'.$lot->unit, $lot->unit) }}</li>
                        @endif
                        @if ($lot->warranty !== null && $lot->warranty !== 'none')
                            <li class="rounded-full bg-aj-bg px-3 py-1">
                                {{ config('listings.warranty_options.'.$lot->warranty) }}@if ($lot->warranty_until) tot {{ $lot->warranty_until->isoFormat('D MMM YYYY') }}@endif
                            </li>
                        @endif
                        @if ($lot->documents->contains(fn ($document) => $document->kind === DocumentKind::Invoice))
                            <li class="rounded-full bg-aj-bg px-3 py-1">Aankoopfactuur aanwezig</li>
                        @endif
                    </ul>
                </div>

                <x-lot.seller-card :lot="$lot" :stats="$sellerStats" />

                {{-- Waarde-indicatie: alleen als alle drie de velden er zijn, en
                     nooit als exact bedrag bij lage zekerheid (hfst. 8). --}}
                @if ($lot->hasValueEstimate())
                    <div class="rounded-aj-s border border-aj-violet/20 bg-aj-violet/5 p-4">
                        <div class="flex items-start gap-3">
                            <x-ui.agent-avatar agent="ivy" class="size-9 shrink-0" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                                    <p class="text-sm font-extrabold text-aj-violet">
                                        Marktadvies van Ivy
                                        <span class="block text-xs font-normal text-aj-muted">AI-specialist</span>
                                    </p>
                                    <p class="text-right text-sm font-bold">
                                        Marktwaarde
                                        <span class="font-normal text-aj-muted">
                                            (indicatie) · {{ $lot->value_confidence->label() }}
                                        </span>
                                        <span class="tabular block">
                                            {{ Money::format($lot->value_min_cents) }} –
                                            {{ Money::format($lot->value_max_cents) }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{--
                            De prijsas als balk, over de volle kaartbreedte: groen
                            (onder de indicatie, gunstig voor de koper) via amber
                            naar rood (erboven). Het felle, afgeronde segment ís de
                            marktwaarde; de vage baan is de volledige as. Het donkere
                            streepje is het huidige bod - alleen bij voldoende
                            zekerheid, want bij lage zekerheid is die vergelijking
                            geen koopargument (hfst. 8).
                        --}}
                        @php
                            $axisMin = (int) floor($lot->value_min_cents * 0.7);
                            $axisMax = max((int) ceil($lot->value_max_cents * 1.3), $axisMin + 100);
                            $axisPosition = fn (int $cents): float => round(max(2, min(98, ($cents - $axisMin) / ($axisMax - $axisMin) * 100)), 1);
                            $bandLeft = $axisPosition($lot->value_min_cents);
                            $bandWidth = max(2, $axisPosition($lot->value_max_cents) - $bandLeft);
                            $axisGradient = 'linear-gradient(90deg,#1c8a5b 0%,#b4690e 55%,#c0392b 100%)';

                            // Het streepje wijst naar de prijs die hier telt: de
                            // vaste prijs bij koop direct, anders de live stand.
                            $markerPriceExpression = $lot->sale_type === SaleType::BuyNow
                                ? '(state.buy_now?.price_cents ?? state.price_cents)'
                                : 'state.price_cents';
                            $markerLabel = match ($lot->sale_type) {
                                SaleType::BuyNow => 'vaste prijs',
                                SaleType::Dutch => 'huidige prijs',
                                default => 'huidig bod',
                            };
                        @endphp
                        <div class="mt-3">
                            <div class="relative h-2.5"
                                 title="Marktwaarde {{ Money::format($lot->value_min_cents) }} – {{ Money::format($lot->value_max_cents) }}">
                                <div class="absolute inset-0 rounded-full opacity-25"
                                     style="background:{{ $axisGradient }}"></div>

                                {{-- Zelfde verloop, uitgelijnd op de as (background-size
                                     en -position), zodat de kleuren exact doorlopen. --}}
                                <div class="absolute inset-y-0 rounded-full"
                                     style="left:{{ $bandLeft }}%;width:{{ $bandWidth }}%;
                                            background-image:{{ $axisGradient }};
                                            background-size:{{ round(10000 / $bandWidth, 2) }}% 100%;
                                            background-position-x:{{ round(100 * $bandLeft / (100 - $bandWidth), 2) }}%"></div>

                                @if ($state !== null && $lot->mayShowValueComparison())
                                    <div class="absolute -top-2 h-[26px] w-[3px] -translate-x-1/2 rounded-full bg-aj-navy"
                                         :style="`left: ${Math.max(2, Math.min(98, (({{ $markerPriceExpression }} ?? 0) - {{ $axisMin }}) / {{ $axisMax - $axisMin }} * 100))}%`"
                                         title="{{ ucfirst($markerLabel) }}"></div>
                                @endif
                            </div>

                            @if ($state !== null && $lot->mayShowValueComparison())
                                <p class="mt-1.5 text-[11px] text-aj-muted">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="inline-block h-3 w-[3px] rounded-full bg-aj-navy"></span>
                                        {{ $markerLabel }}
                                    </span>
                                </p>
                            @endif
                        </div>

                        @unless ($lot->mayShowValueComparison())
                            <p class="mt-1 text-xs text-aj-muted">
                                Te weinig vergelijkbare verkopen voor een scherpe schatting -
                                een bezichtiging is hier verstandig.
                            </p>
                        @endunless

                    </div>
                @endif

                @if ($lot->sale && $lot->sale->isPublished())
                    {{-- Onderdeel van een veiling met meerdere kavels (Auction Studio). --}}
                    <p class="rounded-aj-sm border border-aj-blue/30 bg-aj-blue/5 px-4 py-3 text-sm">
                        Onderdeel van de veiling <a class="font-bold underline" href="{{ route('sales.show', $lot->sale) }}">{{ $lot->sale->title }}</a>
                        <span class="text-aj-muted">· {{ $lot->sale->lots()->count() }} kavels, sluiten vanaf {{ $lot->sale->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span>
                    </p>
                @endif

                {{-- Beschrijving naast het vertrouwensblok, zoals in het ontwerp. --}}
                <div class="grid gap-5 sm:grid-cols-[1.4fr_1fr] sm:items-start">
                    <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5">
                        <h2 class="text-lg font-extrabold">{{ $translation ? 'Description' : 'Omschrijving' }}</h2>
                        {{-- Vertaling door Ivy (briefing stap 4): op verzoek, met de Nederlandse tekst als bindende versie. --}}
                        @if ($translation)
                            <p class="mt-2 rounded-aj-sm border border-aj-border bg-aj-bg px-3 py-2 text-xs text-aj-muted" lang="en">Automatically translated by Ivy. The Dutch text is the binding version. <a class="font-bold underline" href="{{ route('lots.show', $lot) }}" hreflang="nl">Toon Nederlands</a></p>
                            <p class="mt-2 whitespace-pre-line text-sm text-aj-soft" lang="en">{{ $translation['description'] }}</p>
                        @else
                            @if ($hasTranslation)
                                <p class="mt-1 text-xs text-aj-muted"><a class="font-bold underline" href="{{ route('lots.show', ['lot' => $lot, 'lang' => 'en']) }}" hreflang="en" lang="en">Read in English</a></p>
                            @endif
                            <p class="mt-2 whitespace-pre-line text-sm text-aj-soft">{{ $lot->description }}</p>
                        @endif
                    </section>

                    <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Vertrouwen">
                        <h2 class="text-lg font-extrabold">Vertrouwen</h2>
                        <ul class="mt-3 space-y-2.5 text-sm">
                            @php
                                $trustPoints = array_values(array_filter([
                                    $lot->company?->isVerified() ? 'Verkoper geverifieerd' : null,
                                    'Kosten vóór het bieden',
                                    $auction !== null ? 'Biedhistorie vastgelegd' : null,
                                    $lot->viewing_available ? 'Bezichtiging mogelijk' : null,
                                    $lot->pickup_only ? 'Zelf ophalen in overleg' : 'Transport- en ophaalopties',
                                    'Support bij geschillen',
                                ]));
                            @endphp
                            @foreach ($trustPoints as $point)
                                <li class="flex items-center gap-2.5">
                                    <svg class="size-4 shrink-0 text-aj-success" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $point }}
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('assurance') }}" class="mt-4 inline-block text-sm font-bold text-aj-blue-text underline">
                            Hoe werkt zekerheid bij Aucvy?
                        </a>
                    </section>
                </div>

                @if ($lot->specifications)
                    <section>
                        <h2 class="text-lg font-extrabold">Specificaties</h2>
                        <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                            @php $schemaKeys = []; @endphp
                            @foreach ($lot->category?->effectiveSpecificationsSchema() ?? [] as $field)
                                @php
                                    $schemaKeys[] = $field['key'];
                                    $value = $lot->specifications[$field['key']] ?? null;
                                    $value = is_array($value) || $value === null ? $value : ['value' => $value];
                                @endphp
                                @if ($value !== null && ($value['value'] ?? null) !== null)
                                    <div class="flex justify-between gap-3 border-b border-aj-border py-1">
                                        <dt class="text-aj-muted">{{ $field['label'] }}</dt>
                                        <dd class="font-semibold">
                                            @if (is_bool($value['value']))
                                                {{ $value['value'] ? 'Ja' : 'Nee' }}
                                            @else
                                                {{ $value['value'] }}{{ isset($field['unit']) ? ' '.$field['unit'] : '' }}
                                            @endif
                                            {{-- De herkomst blijft navolgbaar: door Ivy ingevuld
                                                 of door de verkoper (hfst. 5). --}}
                                            @if (($value['source'] ?? null) === 'ai')
                                                <span class="text-xs font-normal text-aj-violet">· door Ivy</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            @endforeach
                            {{-- Kenmerken buiten het schema: wat Ivy of de verkoper er los bij zette. --}}
                            @foreach (collect($lot->specifications)->except($schemaKeys) as $key => $value)
                                @php $entry = is_array($value) ? $value : ['value' => $value]; @endphp
                                @if (($entry['value'] ?? null) !== null && ! is_array($entry['value']))
                                    <div class="flex justify-between gap-3 border-b border-aj-border py-1">
                                        <dt class="text-aj-muted">{{ ucfirst(str_replace('_', ' ', (string) $key)) }}</dt>
                                        <dd class="font-semibold">
                                            @if (is_bool($entry['value'])) {{ $entry['value'] ? 'Ja' : 'Nee' }} @else {{ $entry['value'] }} @endif
                                            @if (($entry['source'] ?? null) === 'ai')
                                                <span class="text-xs font-normal text-aj-violet">· door Ivy</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </section>
                @endif

                {{--
                    Documenten staan op een private disk (hfst. 15); hier staat
                    wát er is en of Lex het bekeken heeft, niet het bestand zelf.
                --}}
                @if ($lot->documents->isNotEmpty())
                    <section id="documenten" aria-label="Documenten">
                        <h2 class="text-lg font-extrabold">Documenten</h2>
                        <ul class="mt-2 space-y-2">
                            @foreach ($lot->documents as $document)
                                <li class="flex flex-wrap items-center gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">
                                    <svg class="size-5 shrink-0 text-aj-muted" viewBox="0 0 20 20" fill="none"
                                         stroke="currentColor" stroke-width="1.6" stroke-linecap="round"
                                         stroke-linejoin="round" aria-hidden="true">
                                        <path d="M11.5 2.5h-6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-10m-4-4 4 4m-4-4v4h4"/>
                                    </svg>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold">{{ $document->kind->label() }}</span>
                                        <span class="block truncate text-xs text-aj-muted">
                                            {{ $document->original_filename }}
                                            · {{ number_format($document->size_bytes / 1024, 0, ',', '.') }} kB
                                        </span>
                                    </span>
                                    @if ($document->wasCheckedByIvy())
                                        <span class="flex shrink-0 items-center gap-1.5 text-xs font-bold text-aj-success-text">
                                            <x-ui.agent-avatar agent="lex" class="size-5" />
                                            Gecontroleerd door Lex
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            {{-- Zijkolom ---------------------------------------------------- --}}
            <aside class="space-y-4 lg:sticky lg:top-4">
                @if ($state === null)
                    @if ($lot->status === \App\Enums\LotStatus::Sold)
                        <x-ui.empty-state
                            title="Deze kavel is verkocht"
                            body="De verkoop is afgerond. Bekijk vergelijkbare kavels hieronder of blader verder.">
                            <x-slot:action>
                                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
                            </x-slot:action>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state
                            title="Deze kavel loopt nog niet"
                            body="Er is nog geen verkooproute gekoppeld." />
                    @endif
                @else
                    {{--
                        Eén verkoopvorm tegelijk. Drie tabs plus biedpaneel plus
                        koop-direct naast elkaar is keuzestress op 390px
                        (hfst. 12).
                    --}}
                    @if ($lot->sale_type === SaleType::Dutch)
                        <x-auction.dutch-panel />
                    @elseif ($lot->sale_type === SaleType::BuyNow)
                        {{-- Koop direct heeft geen biedpaneel: vaste prijs,
                             wie het eerst koopt (hfst. 6). --}}
                        <x-auction.buy-now-hero />
                    @else
                        <x-auction.bid-panel :auction="$auction" />

                        {{-- Een veiling kan daarnaast een koop-direct-prijs
                             hebben; die vervalt zodra er overheen geboden is. --}}
                        @if ($auction->buy_now_price_cents !== null)
                            <x-auction.buy-now-panel />
                        @endif
                    @endif

                    {{-- Volgen en delen: licht, naast de zware koopacties. --}}
                    <div class="flex gap-2"
                         x-data="{
                            watching: {{ $isWatching ? 'true' : 'false' }},
                            count: {{ $watcherCount }},
                            busy: false,
                            copied: false,
                            async toggle() {
                                if (this.busy) return;
                                this.busy = true;
                                try {
                                    const response = await fetch('/api/lots/{{ $lot->id }}/watch', {
                                        method: this.watching ? 'DELETE' : 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                            'Accept': 'application/json',
                                        },
                                    });
                                    if (response.ok) {
                                        this.watching = ! this.watching;
                                        this.count += this.watching ? 1 : -1;
                                    }
                                } catch (error) {
                                    // Geen verbinding: de knop blijft bruikbaar.
                                }
                                this.busy = false;
                            },
                            async share() {
                                const url = window.location.href;
                                if (navigator.share) {
                                    try { await navigator.share({ title: {{ Js::from($lot->title) }}, url }); } catch (error) {}
                                    return;
                                }
                                try {
                                    await navigator.clipboard.writeText(url);
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                } catch (error) {
                                    window.prompt('Kopieer de link:', url);
                                }
                            },
                         }">
                        @auth
                            <button type="button" class="btn btn-ghost flex-1" @click="toggle()"
                                    :aria-pressed="watching">
                                <svg class="size-4" viewBox="0 0 20 20" :fill="watching ? 'currentColor' : 'none'"
                                     stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10 17s-6.5-4.1-6.5-8.6a3.6 3.6 0 0 1 6.5-2.1 3.6 3.6 0 0 1 6.5 2.1C16.5 12.9 10 17 10 17Z"/>
                                </svg>
                                <span x-text="watching ? 'Gevolgd' : 'Volgen'"></span>
                                <span class="tabular text-aj-muted" x-show="count > 0" x-text="count"></span>
                            </button>
                        @endauth
                        @guest
                            <a href="{{ route('login') }}" class="btn btn-ghost flex-1">
                                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                     stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10 17s-6.5-4.1-6.5-8.6a3.6 3.6 0 0 1 6.5-2.1 3.6 3.6 0 0 1 6.5 2.1C16.5 12.9 10 17 10 17Z"/>
                                </svg>
                                Volgen
                            </a>
                        @endguest

                        <button type="button" class="btn btn-ghost flex-1" @click="share()">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="5" cy="10" r="2.2"/><circle cx="15" cy="4.5" r="2.2"/><circle cx="15" cy="15.5" r="2.2"/>
                                <path d="m7 9 6-3.6M7 11l6 3.6"/>
                            </svg>
                            <span x-text="copied ? 'Gekopieerd!' : 'Delen'"></span>
                        </button>

                        {{-- Web push (hfst. 14): "overboden" hoort binnen twee
                             seconden op je scherm, ook met de site dicht. --}}
                        @auth
                            <button type="button" class="btn btn-ghost flex-1" x-data="pushToggle"
                                    x-show="supported && ! denied" x-cloak
                                    :disabled="busy" @click="toggle()"
                                    :aria-pressed="enabled"
                                    :title="enabled ? 'Meldingen staan aan op dit apparaat' : 'Krijg direct een melding als je overboden wordt'">
                                <svg class="size-4" viewBox="0 0 20 20" :fill="enabled ? 'currentColor' : 'none'"
                                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                     stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10 3a4.5 4.5 0 0 0-4.5 4.5c0 3.2-1 4.4-1.8 5.2h12.6c-.8-.8-1.8-2-1.8-5.2A4.5 4.5 0 0 0 10 3Z"/>
                                    <path d="M8.3 15.7a1.8 1.8 0 0 0 3.4 0"/>
                                </svg>
                                <span x-text="enabled ? 'Meldingen aan' : 'Meldingen'"></span>
                            </button>
                        @endauth
                    </div>

                    @auth
                        @if ($lot->seller_id === auth()->id())
                            {{-- Eigenaar: delen met herkomst per kanaal (briefing stap 10) en snel naar bewerken. --}}
                            <div class="rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4">
                                <p class="text-sm font-extrabold">Jouw kavel delen</p>
                                <p class="mt-1 text-xs text-aj-muted">Elke link heeft een eigen herkomst, zodat je ziet wat werkt.</p>
                                <x-lots.share :lot="$lot" compact class="mt-3" />
                                @unless ($lot->viewing_available)
                                    <a href="{{ route('dashboard.lots.edit', $lot) }}" class="btn btn-ghost mt-3 w-full justify-center" style="height:40px;font-size:13px">Kavel bewerken</a>
                                @endunless
                            </div>
                        @endif
                    @endauth

                    {{-- Bezichtiging: moment voorstellen; het adres volgt per
                         mail, pas na bevestiging van de verkoper (hfst. 7). --}}
                    @if ($lot->viewing_available)
                        <div id="bezichtiging" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4">
                            <p class="text-sm font-extrabold">Bezichtiging plannen</p>

                            @guest
                                <p class="mt-1 text-sm text-aj-muted">
                                    Kijken vóór je biedt kan. Log in om een moment voor te stellen.
                                </p>
                                <a href="{{ route('login') }}" class="btn btn-ghost mt-3 w-full justify-center"
                                   style="height:40px;font-size:13px">Inloggen</a>
                            @endguest

                            @auth
                                @if ($lot->seller_id === auth()->id())
                                    <p class="mt-1 text-sm text-aj-muted">
                                        Kopers stellen hier een kijkmoment voor; jij bevestigt per mail.
                                    </p>
                                    <a href="{{ route('dashboard.lots.edit', $lot) }}" class="btn btn-ghost mt-3 w-full justify-center" style="height:40px;font-size:13px">Kavel bewerken</a>
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
                                                <p class="mt-1 text-sm text-aj-muted">
                                                    Stel een moment voor. Het adres ontvang je per mail zodra de verkoper bevestigt.
                                                </p>
                                                <label class="mt-3 block">
                                                    <span class="sr-only">Moment</span>
                                                    <input type="datetime-local" x-model="slot"
                                                           min="{{ now(config('aucvy.display_timezone'))->addHours(2)->format('Y-m-d\TH:i') }}"
                                                           class="w-full rounded-aj-sm border border-aj-border-strong bg-white px-3 text-sm text-aj-text"
                                                           style="min-height: 44px">
                                                </label>
                                                <label class="mt-2 block">
                                                    <span class="sr-only">Bericht voor de verkoper</span>
                                                    <input type="text" x-model="note" maxlength="500"
                                                           placeholder="Bericht voor de verkoper (niet verplicht)"
                                                           class="w-full rounded-aj-sm border border-aj-border-strong bg-white px-3 text-sm text-aj-text"
                                                           style="min-height: 44px">
                                                </label>
                                                <button type="button" class="btn btn-primary mt-3 w-full justify-center"
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
                                            <p class="mt-1 text-sm text-aj-muted">
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
                        <div class="rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4"
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
                            <p class="text-sm font-extrabold">Transportindicatie</p>
                            <p class="mt-1 text-xs text-aj-muted">
                                Vanaf {{ Money::format($lot->transport_from_cents) }} - vul je postcode in
                                voor een indicatie naar jouw adres.
                            </p>
                            <div class="mt-3 flex gap-2">
                                <label class="sr-only" for="shipping-postcode">Postcode</label>
                                <input id="shipping-postcode" type="text" inputmode="text" autocomplete="postal-code"
                                       placeholder="1234 AB" maxlength="7"
                                       x-model="postcode" @keydown.enter.prevent="calc()"
                                       class="tabular w-full rounded-aj-sm border border-aj-border-strong bg-white px-3 text-sm text-aj-text"
                                       style="min-height: 44px">
                                <button type="button" class="btn btn-ghost shrink-0" style="height:44px;font-size:13px"
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

                    @if ($lot->sale_type === SaleType::Auction && ! $auction->isClosed() && config('aucvy.ai.enabled'))
                        <x-auction.ivy-advice :lot="$lot" />
                    @endif

                    {{--
                        De kostenopbouw. Standaard met het vanaf-tarief voor
                        transport; berekent de kijker een postcode-indicatie,
                        dan komt de vers berekende opbouw van de server ervoor
                        in de plaats (nooit client-side rekenen, hfst. 13.3).
                    --}}
                    <div x-data="{ liveCosts: null }" @shipping-quoted.window="liveCosts = $event.detail">
                        <template x-if="! liveCosts">
                            <div>
                                <x-lot.cost-breakdown
                                    id="kosten"
                                    :costs="$costs"
                                    :note="__('money.notes.estimate')"
                                    transport-note="Vanaf-tarief - bereken hierboven je postcode" />
                            </div>
                        </template>

                        <template x-if="liveCosts">
                            <div id="kosten" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4">
                                <p class="text-sm font-extrabold">{{ __('Kosten vóór je biedt') }}</p>
                                <dl class="mt-3 space-y-1.5 text-sm">
                                    <template x-for="line in liveCosts.lines" :key="line.key">
                                        <div class="flex items-baseline justify-between gap-4">
                                            <dt class="text-aj-muted">
                                                <span x-text="line.label"></span>
                                                <template x-if="line.note">
                                                    <span class="block text-xs" x-text="line.note"></span>
                                                </template>
                                            </dt>
                                            <dd class="tabular shrink-0 font-semibold" x-text="line.formatted"></dd>
                                        </div>
                                    </template>
                                    <div class="flex items-baseline justify-between gap-4 border-t border-aj-border pt-2">
                                        <dt class="font-extrabold">{{ __('money.lines.total') }}</dt>
                                        <dd class="tabular shrink-0 text-lg font-extrabold" x-text="liveCosts.total_formatted"></dd>
                                    </div>
                                </dl>
                                <p class="mt-3 text-xs text-aj-muted">
                                    Met jouw transportindicatie - definitief na bevestiging.
                                </p>
                            </div>
                        </template>
                    </div>

                    {{-- Bij koop direct valt er geen biedhistorie te tonen. --}}
                    @if ($lot->sale_type !== SaleType::BuyNow)
                        <x-auction.bid-history />
                    @endif
                @endif
            </aside>
        </div>

        {{-- Vergelijkbare kavels: dezelfde kaart als op de veilingenpagina. --}}
        @if ($similarLots->isNotEmpty())
            <section id="vergelijkbaar" class="mt-12" aria-label="Vergelijkbare kavels">
                <h2 class="text-xl font-extrabold tracking-tight">Vergelijkbare kavels</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($similarLots as $similarLot)
                        <x-lot.card :lot="$similarLot" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
