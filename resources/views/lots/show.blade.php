@use('App\Enums\DocumentKind')
@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

@php
    // Elke kavel een eigen deelbeeld (briefing nieuwe functies): liggend, 1200 × 630.
    $shareImage = route('lots.share-image', [$lot, 'landscape']);
    [$shareWidth, $shareHeight] = \App\Distribution\ShareVisual::dimensions('landscape');
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

    // De regel onder de titel en de staat op de foto, zoals in het ontwerp van november.
    $saleLabel = match ($lot->sale_type) {
        SaleType::Dutch => 'afslag',
        SaleType::BuyNow => 'koop direct',
        default => 'veiling',
    };
    $lead = implode(' · ', array_values(array_filter([
        $lot->location?->city,
        $lot->condition ? mb_strtolower($lot->condition->label()) : null,
        $saleLabel,
    ])));
    $lastChance = $auction !== null && ! $auction->isClosed() && $auction->ends_at->isBefore(now()->addDay());
    $galleryStatus = match (true) {
        $auction === null || $auction->isClosed() => ['tone-grey', $lot->status === \App\Enums\LotStatus::Sold ? 'Verkocht' : 'Gesloten'],
        $lot->sale_type === SaleType::Dutch => ['tone-atlas', 'Afslag'],
        $lot->sale_type === SaleType::BuyNow => ['tone-ok', 'Koop direct'],
        $lastChance => ['tone-warn', 'Laatste kans'],
        default => ['tone-live', 'Live'],
    };
    $priceNow = $lot->currentPriceCents();
    $facts = array_values(array_filter([
        $lot->documents->contains(fn ($document) => $document->kind === DocumentKind::Invoice) ? ['file', 'Aankoopfactuur aanwezig'] : null,
        $lot->location ? ['eye', $lot->location->publicLabel().($lot->viewing_available ? ', bezichtiging mogelijk' : '')] : null,
        $lot->transport_from_cents ? ['truck', 'Transport vanaf '.Money::format($lot->transport_from_cents)] : ($lot->pickup_only ? ['truck', 'Zelf ophalen in overleg'] : null),
        $lot->quantity > 1 ? ['shopping-bag', $lot->quantity.' × '.config('listings.unit_options.'.$lot->unit, $lot->unit)] : null,
        $lot->warranty !== null && $lot->warranty !== 'none' ? ['shield-check', config('listings.warranty_options.'.$lot->warranty).($lot->warranty_until ? ' tot '.$lot->warranty_until->isoFormat('D MMM YYYY') : '')] : null,
    ]));
    $trustPoints = array_values(array_filter([
        $lot->company?->isVerified() ? 'Verkoper geverifieerd' : null,
        'Kosten vóór het bieden',
        $auction !== null ? 'Biedhistorie vastgelegd' : null,
        $lot->viewing_available ? 'Bezichtiging mogelijk' : null,
        $lot->pickup_only ? 'Zelf ophalen in overleg' : 'Transport- en ophaalopties',
        'Support bij geschillen',
    ]));
@endphp

@push('head')
    <script type="application/ld+json">{!! json_encode(array_filter($structured, fn ($value) => $value !== null), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

<x-layouts.public :title="$lot->title" :heading="$translation['title'] ?? $lot->title" :description="Str::limit($lot->description, 155)"
    :eyebrow="($lot->category?->name ?? 'Kavel').' · Kavel #'.$lot->public_id" :intro="$lead" :image="$shareImage" :image-width="$shareWidth" :image-height="$shareHeight" og-type="product" :canonical="route('lots.show', $lot)" :ivy-lot="$lot" ivy-page="kavel">
    {{-- Op mobiel staat de Ivy-knop boven de biedbalk, nooit eroverheen. --}}
    <style>@media (max-width: 1023px) { .ivy-root { --ivy-offset: 76px; } }</style>
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
        dan de teller ernaast (hfst. 11). De opbouw is die van het ontwerp van
        november: links foto's en inhoud, rechts de biedkaart.
    --}}
    <div @if ($state) x-data="auction({{ Js::from($state) }})" @endif
         data-lot-nav
         @if ($nextLot) data-next="{{ route('lots.show', $nextLot) }}" @endif
         @if ($previousLot) data-prev="{{ route('lots.show', $previousLot) }}" @endif>

        @if ($nextLot || $previousLot)
            <p class="-mt-2 hidden items-center justify-end gap-1 text-xs text-aj-muted lg:flex" aria-hidden="true">
                Blader
                <kbd class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-1.5 py-0.5 font-sans font-bold"><x-v4.icon name="arrow-left" :size="11" stroke="2.6" /></kbd>
                <kbd class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-1.5 py-0.5 font-sans font-bold"><x-v4.icon name="arrow-right" :size="11" stroke="2.6" /></kbd>
            </p>
        @endif

        {{-- Prijswijzigingen worden voorgelezen, afgeknepen tot één melding per drie seconden (hfst. 11). --}}
        <div aria-live="polite" class="sr-only" x-text="announcement"></div>

        @if (isset($share) && $share !== null)
            @include('lots.partials.shared-banner', ['lot' => $lot, 'share' => $share])
        @endif

        {{-- Op mobiel: foto's eerst, dan pas prijs en bod (hfst. 12). Op desktop staat de biedkaart rechts. --}}
        <div class="mt-4 grid gap-7 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start xl:grid-cols-[minmax(0,1fr)_320px]">

            <div class="d-stack" style="gap:20px">
                @if ($liveSession ?? null)
                    @include('lots.partials.live-viewer', ['lot' => $lot, 'session' => $liveSession])
                @endif

                <x-lot.media-gallery :lot="$lot" :status="$galleryStatus" :watching="$interest['now'] ?? 0" />

                <x-lot.seller-card :lot="$lot" :stats="$sellerStats" />

                {{-- Ivy insight: waarde-indicatie, interesse en documentatie in één zin, en drie vragen die het gesprek openen. --}}
                @php
                    $insightParts = [];
                    if ($lot->hasValueEstimate()) {
                        $insightParts[] = 'Marktwaarde '.Money::format($lot->value_min_cents).' – '.Money::format($lot->value_max_cents).' (indicatie).';
                        $insightParts[] = $lot->mayShowValueComparison()
                            ? $lot->value_confidence->label().'.'
                            : $lot->value_confidence->label().': te weinig vergelijkbare verkopen voor een scherpe schatting - een bezichtiging is hier verstandig.';
                    }
                    if ($interest['hot'] ?? false) {
                        $insightParts[] = 'Interesse hoog.';
                    } elseif (($interest['bidders'] ?? 0) > 0) {
                        $insightParts[] = ($interest['bidders'] === 1 ? '1 bieder' : $interest['bidders'].' bieders').' tot nu toe.';
                    }
                    if ($lot->documents->isNotEmpty()) {
                        $insightParts[] = $lot->documents->every(fn ($document) => $document->wasCheckedByIvy()) ? 'Documentatie gecontroleerd door Lex.' : 'Documentatie aanwezig.';
                    }
                    $insightQuestions = $lot->sale_type === SaleType::BuyNow
                        ? ['Is dit een eerlijke prijs?', 'Wat kost dit totaal?', 'Vergelijk met alternatieven']
                        : ['Wat is een verstandig bod?', 'Wat kost dit totaal?', 'Vergelijk met alternatieven'];
                @endphp
                @if (\App\Ivy\Chat\IvyFlags::on('ivy.enabled') || $insightParts !== [])
                    <div class="d-insight">
                        <div class="d-insight-head">
                            <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
                            <div>
                                <strong>Ivy insight</strong>
                                <span>{{ $insightParts !== [] ? implode(' ', $insightParts) : 'Ivy kent deze kavel: de tekst, de documenten en de interesse. Stel haar een vraag.' }}</span>
                            </div>
                        </div>
                        @if (\App\Ivy\Chat\IvyFlags::on('ivy.enabled'))
                            <div class="d-insight-label">Vraag het Ivy over deze kavel</div>
                            <div class="d-chips">
                                @foreach ($insightQuestions as $question)
                                    <button type="button" class="d-chip" @click="$dispatch('ivy-open', { text: @js($question) })">{{ $question }}</button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if ($lot->sale && $lot->sale->isPublished())
                    {{-- Onderdeel van een veiling met meerdere kavels (Auction Studio). --}}
                    <p class="d-soft" style="margin:0">
                        Onderdeel van de veiling <a class="d-link" href="{{ route('sales.show', $lot->sale) }}">{{ $lot->sale->title }}</a>
                        <span class="d-muted">· {{ $lot->sale->lots()->count() }} kavels, sluiten vanaf {{ $lot->sale->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span>
                    </p>
                @endif

                {{-- Documenten staan op een private disk (hfst. 15); hier staat wát er is en of Lex het bekeken heeft. --}}
                @include('lots.partials.documents', ['lot' => $lot])

                <div>
                    <h2 class="d-h2-page">{{ $translation ? 'Description' : 'Omschrijving' }}</h2>
                    {{-- Vertaling door Ivy (briefing stap 4): op verzoek, met de Nederlandse tekst als bindende versie. --}}
                    @if ($translation)
                        <p class="d-soft" style="margin:8px 0 0" lang="en">Automatically translated by Ivy. The Dutch text is the binding version. <a class="d-link" href="{{ route('lots.show', $lot) }}" hreflang="nl">Toon Nederlands</a></p>
                        <p class="d-prose" lang="en">{{ $translation['description'] }}</p>
                    @else
                        @if ($hasTranslation)
                            <p class="d-hint"><a class="d-link" href="{{ route('lots.show', ['lot' => $lot, 'lang' => 'en']) }}" hreflang="en" lang="en">Read in English</a></p>
                        @endif
                        <p class="d-prose">{{ $lot->description }}</p>
                    @endif
                </div>

                @if ($facts !== [] || $lot->condition)
                    <div>
                        <h2 class="d-h2-page">Conditie en gebreken</h2>
                        @if ($lot->condition)
                            <p class="d-prose">Staat volgens de verkoper: {{ mb_strtolower($lot->condition->label()) }}. Gebreken staan in de omschrijving en op de foto's; wat niet vermeld is, kun je navragen.</p>
                        @endif
                        <ul class="d-facts">
                            @foreach ($facts as [$icon, $fact])
                                <li><x-v4.icon :name="$icon" :size="15" stroke="2" /> {{ $fact }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $specRows = [];
                    $schemaKeys = [];
                    foreach ($lot->category?->effectiveSpecificationsSchema() ?? [] as $field) {
                        $schemaKeys[] = $field['key'];
                        $value = $lot->specifications[$field['key']] ?? null;
                        $value = is_array($value) || $value === null ? $value : ['value' => $value];
                        if ($value !== null && ($value['value'] ?? null) !== null) {
                            $specRows[] = [$field['label'], is_bool($value['value']) ? ($value['value'] ? 'Ja' : 'Nee') : $value['value'].(isset($field['unit']) ? ' '.$field['unit'] : ''), ($value['source'] ?? null) === 'ai'];
                        }
                    }
                    foreach (collect($lot->specifications ?? [])->except($schemaKeys) as $key => $value) {
                        $entry = is_array($value) ? $value : ['value' => $value];
                        if (($entry['value'] ?? null) !== null && ! is_array($entry['value'])) {
                            $specRows[] = [ucfirst(str_replace('_', ' ', (string) $key)), is_bool($entry['value']) ? ($entry['value'] ? 'Ja' : 'Nee') : $entry['value'], ($entry['source'] ?? null) === 'ai'];
                        }
                    }
                    if ($lot->brand) {
                        array_unshift($specRows, ['Merk', $lot->brand, false]);
                    }
                    if ($lot->condition) {
                        $specRows[] = ['Conditie', $lot->condition->label(), false];
                    }
                    $specRows[] = ['Btw-regeling', $lot->vat_regime?->label() ?? 'Volgens factuur', false];
                @endphp
                @if ($specRows !== [])
                    <div>
                        <h2 class="d-h2-page" style="margin-bottom:8px">Specificaties</h2>
                        <dl class="d-specs">
                            @foreach ($specRows as [$label, $value, $byIvy])
                                <div class="d-spec">
                                    <dt>{{ $label }}</dt>
                                    {{-- De herkomst blijft navolgbaar: door Ivy ingevuld of door de verkoper (hfst. 5). --}}
                                    <dd>{{ $value }}@if ($byIvy) <small>· door Ivy</small>@endif</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                @include('lots.partials.ask-answers', ['lot' => $lot])

                {{-- Bij koop direct valt er geen biedhistorie te tonen. --}}
                @if ($state !== null && $lot->sale_type !== SaleType::BuyNow)
                    <x-auction.bid-history />
                @endif
            </div>

            {{-- Zijkolom ---------------------------------------------------- --}}
            <aside class="d-stack lg:sticky lg:top-4">
                @if ($state === null)
                    @if ($lot->status === \App\Enums\LotStatus::Sold)
                        <x-ui.empty-state
                            :title="\App\Support\PlatformFacts::publicSoldPrices() && $lot->order ? 'Verkocht voor '.Money::format($lot->order->hammer_cents) : 'Deze kavel is verkocht'"
                            :body="\App\Support\PlatformFacts::publicSoldPrices() && $lot->order ? 'Aan '.($lot->order->buyer?->publicName() ?? 'een koper').'. Bekijk vergelijkbare kavels hieronder of blader verder.' : 'De verkoop is afgerond. Bekijk vergelijkbare kavels hieronder of blader verder.'">
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
                    {{-- Eén verkoopvorm tegelijk (hfst. 12). De interesse staat in de kaart, onder de stand. --}}
                    @if ($lot->sale_type === SaleType::Dutch)
                        <x-auction.dutch-panel>
                            @include('lots.partials.interest', ['lot' => $lot, 'interest' => $interest])
                        </x-auction.dutch-panel>
                    @elseif ($lot->sale_type === SaleType::BuyNow)
                        <x-auction.buy-now-hero>
                            @include('lots.partials.interest', ['lot' => $lot, 'interest' => $interest])
                        </x-auction.buy-now-hero>
                    @else
                        <x-auction.bid-panel :auction="$auction">
                            @include('lots.partials.interest', ['lot' => $lot, 'interest' => $interest])

                            {{-- De kostenopbouw bij dit bod, met het vanaf-tarief voor transport; na een postcode komt de verse opbouw ervoor in de plaats (hfst. 13.3). --}}
                            <div x-data="{ liveCosts: null }" @shipping-quoted.window="liveCosts = $event.detail">
                                <template x-if="! liveCosts">
                                    <div>
                                        <x-lot.cost-breakdown id="kosten" :costs="$costs" title="Kostenopbouw bij dit bod" :note="__('money.notes.estimate')" transport-note="Vanaf-tarief - bereken hieronder je postcode" />
                                    </div>
                                </template>
                                <template x-if="liveCosts">
                                    <div id="kosten">
                                        <h3 class="d-h3" style="font-size:14px;font-weight:700">Kostenopbouw bij dit bod</h3>
                                        <div class="d-costs" style="margin-top:8px">
                                            <template x-for="line in liveCosts.lines" :key="line.key">
                                                <div class="d-row">
                                                    <span><span x-text="line.label"></span><template x-if="line.note"><small x-text="line.note"></small></template></span>
                                                    <strong x-text="line.formatted"></strong>
                                                </div>
                                            </template>
                                            <div class="d-row is-total"><span>Jij betaalt</span><strong x-text="liveCosts.total_formatted"></strong></div>
                                        </div>
                                        <p class="d-hint" style="margin-top:8px">Met jouw transportindicatie - definitief na bevestiging.</p>
                                    </div>
                                </template>
                            </div>

                            {{-- Een veiling kan daarnaast een koop-direct-prijs hebben; die vervalt zodra er overheen geboden is. --}}
                            @if ($auction->buy_now_price_cents !== null)
                                <x-auction.buy-now-panel />
                            @endif
                        </x-auction.bid-panel>

                        {{-- Ivy naast het biedpaneel (Ivy-briefing hfst. 9): het niveau per kavel (K4). --}}
                        @if (\App\Ivy\Chat\IvyFlags::on('ivy.enabled'))
                            @auth
                                @if (! $lot->isManagedBy(auth()->user()) && ! $auction->isClosed())
                                    @php $ivyMode = \App\Models\IvyLotMode::for(auth()->user(), $lot); $ivyModeRow = \App\Models\IvyLotMode::query()->where('user_id', auth()->id())->where('lot_id', $lot->id)->whereNull('ended_at')->first(); @endphp
                                    <div class="d-side-card" x-data="{ open: false, mode: @js($ivyMode) }">
                                        <button type="button" class="d-link" style="text-align:left" @click="open = !open" :aria-expanded="open">Hoeveel mag Ivy doen? <span class="d-muted">Nu: {{ __('ivy.modes.'.$ivyMode) }}{{ $ivyModeRow?->limit_cents ? ' tot '.Money::format($ivyModeRow->limit_cents) : '' }}</span></button>
                                        <form method="POST" action="{{ route('ivy.lot-mode', $lot) }}" x-show="open" x-cloak class="d-stack-tight text-sm">
                                            @csrf
                                            <label class="flex items-start gap-2"><input type="radio" name="mode" value="notify" x-model="mode" class="mt-1"> <span><span class="font-bold">Zelf bieden</span><span class="block text-xs text-aj-muted">Ivy helpt alleen met informatie en meldt wat er speelt.</span></span></label>
                                            <label class="flex items-start gap-2"><input type="radio" name="mode" value="ask" x-model="mode" class="mt-1"> <span><span class="font-bold">Ivy waarschuwt mij</span><span class="block text-xs text-aj-muted">Bij elk moment zet ze een voorstel klaar en vraagt wat je wilt.</span></span></label>
                                            @if (\App\Ivy\Chat\IvyFlags::on('ivy.proposals'))
                                                <label class="flex items-start gap-2"><input type="radio" name="mode" value="auto" x-model="mode" class="mt-1"> <span><span class="font-bold">Automatisch tot mijn limiet</span><span class="block text-xs text-aj-muted">Via het bestaande autobod; boven je limiet biedt ze nooit. Je bevestigt het mandaat eerst.</span></span></label>
                                                <div x-show="mode === 'auto'" x-cloak class="flex items-center gap-2 pl-6"><span class="text-xs">Limiet €</span><input type="number" name="limit" min="1" step="1" inputmode="numeric" class="d-input" style="width:130px;margin:0"></div>
                                            @endif
                                            <button type="submit" class="d-btn d-btn-sm d-btn-sq" style="align-self:flex-start">Opslaan</button>
                                            @error('limit')<p class="d-hint is-warn">{{ $message }}</p>@enderror
                                        </form>
                                    </div>
                                @endif
                            @endauth
                        @endif
                    @endif

                    {{-- Volgen en delen: licht, naast de zware koopacties. --}}
                    <div class="d-pair"
                         x-data="{
                            watching: {{ $isWatching ? 'true' : 'false' }},
                            count: {{ $watcherCount }},
                            busy: false,
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
                         }">
                        @auth
                            <button type="button" class="d-btn d-btn-secondary" @click="toggle()" :aria-pressed="watching">
                                <svg class="size-4" viewBox="0 0 20 20" :fill="watching ? 'currentColor' : 'none'"
                                     stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10 17s-6.5-4.1-6.5-8.6a3.6 3.6 0 0 1 6.5-2.1 3.6 3.6 0 0 1 6.5 2.1C16.5 12.9 10 17 10 17Z"/>
                                </svg>
                                <span x-text="watching ? 'Gevolgd' : 'Volgen'"></span>
                                <span class="tabular d-muted" x-show="count > 0" x-text="count"></span>
                            </button>
                        @endauth
                        @guest
                            <a href="{{ route('login') }}" class="d-btn d-btn-secondary">
                                <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                                     stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M10 17s-6.5-4.1-6.5-8.6a3.6 3.6 0 0 1 6.5-2.1 3.6 3.6 0 0 1 6.5 2.1C16.5 12.9 10 17 10 17Z"/>
                                </svg>
                                Volgen
                            </a>
                        @endguest

                        @include('lots.partials.share-menu', ['lot' => $lot, 'ownShares' => $ownShares ?? collect()])
                    </div>

                    {{-- Web push (hfst. 14): "overboden" hoort binnen twee seconden op je scherm, ook met de site dicht. --}}
                    @auth
                        <button type="button" class="d-btn d-btn-secondary d-btn-wide" x-data="pushToggle"
                                x-show="supported && ! denied" x-cloak
                                :disabled="busy" @click="toggle()"
                                :aria-pressed="enabled"
                                :title="enabled ? 'Meldingen staan aan op dit apparaat' : 'Krijg direct een melding als je overboden wordt'">
                            <x-v4.icon name="bell" :size="16" stroke="2" />
                            <span x-text="enabled ? 'Meldingen aan' : 'Meldingen bij overboden'"></span>
                        </button>
                    @endauth

                    @auth
                        @if ($lot->seller_id === auth()->id())
                            {{-- Eigenaar: delen met herkomst per kanaal (briefing stap 10) en snel naar bewerken. --}}
                            <div class="d-side-card">
                                <h3>Jouw kavel delen</h3>
                                <p class="d-note">Elke link heeft een eigen herkomst, zodat je ziet wat werkt.</p>
                                <x-lots.share :lot="$lot" compact />
                                @unless ($lot->viewing_available)
                                    <a href="{{ route('dashboard.lots.edit', $lot) }}" class="d-btn d-btn-secondary d-btn-wide d-btn-sq">Kavel bewerken</a>
                                @endunless
                            </div>
                        @endif
                    @endauth

                    @include('lots.partials.ask', ['lot' => $lot])

                    @include('lots.partials.viewing-and-transport', ['lot' => $lot])

                    @if ($lot->sale_type === SaleType::Auction && ! $auction->isClosed() && config('aucvy.ai.enabled'))
                        <x-auction.ivy-advice :lot="$lot" />
                    @endif

                    @if ($lot->sale_type !== SaleType::Auction)
                        <x-lot.cost-breakdown id="kosten" class="d-side-card" :costs="$costs" :note="__('money.notes.estimate')" transport-note="Vanaf-tarief - bereken hierboven je postcode" />
                    @endif
                @endif

                <div class="d-trust" aria-label="Vertrouwen">
                    <h3>Vertrouwen</h3>
                    <p class="d-note">Dit staat vast bij deze kavel.</p>
                    <ul>
                        @foreach ($trustPoints as $point)
                            <li>
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/></svg>
                                <span>{{ $point }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('assurance') }}" class="d-link" style="display:inline-flex;align-items:center;min-height:44px">Hoe werkt zekerheid bij Aucvy?</a>
                </div>
            </aside>
        </div>

        {{-- Vergelijkbare kavels: dezelfde kaart als op de veilingenpagina. --}}
        @if ($similarLots->isNotEmpty())
            <section id="vergelijkbaar" class="mt-12" aria-label="Vergelijkbare kavels">
                <h2 style="margin:0;font-size:24px;line-height:1.2;font-weight:800;letter-spacing:-.01em">Vergelijkbare kavels</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($similarLots as $similarLot)
                        <x-lot.card :lot="$similarLot" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
