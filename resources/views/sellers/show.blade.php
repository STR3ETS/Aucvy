@use('App\Support\Money\Money')
@use('App\Support\PlatformFacts')

@php
    $verified = $company?->verified_at !== null || $seller->kyc_status->allowsBidding();
    $timezone = config('aucvy.display_timezone');
    $facts = array_filter([
        $verified ? ($company?->verified_at !== null ? 'Geverifieerd bedrijf' : 'Geverifieerd') : null,
        $averageRating !== null ? number_format($averageRating, 1, ',', '').'★' : null,
        'actief sinds '.$seller->created_at->timezone($timezone)->isoFormat('MMMM YYYY'),
        $region,
    ]);
@endphp

{{--
    Het verkopersprofiel (ontwerp van november): de cijfers als tegels, dan
    het aanbod en de reviews, met rechts wat er gecontroleerd is en hoe de
    Trust Index is opgebouwd. Geen contactgegevens, geen adres.
--}}
<x-layouts.public :title="$seller->publicName()" eyebrow="Verkoper">
    <x-slot:crumbs>
        <a href="{{ route('lots.index') }}">Veilingen</a> <span aria-hidden="true">/</span> <span>Verkopers</span>
    </x-slot:crumbs>
    <x-slot:lead>
        <p style="margin:0">{{ implode(' · ', $facts) }}</p>
    </x-slot:lead>

    {{-- De cijfers, elk met zijn definitie erbij. --}}
    <div class="d-tiles d-profile-tiles">
        @if ($trust !== null)
            <a class="d-tile" href="#trust">
                <div class="d-tile-label">Trust Index</div>
                <div class="d-tile-value">{{ $trust['score'] }}</div>
                <div class="d-tile-sub">op basis van transacties, documenten en levering</div>
            </a>
        @endif
        <a class="d-tile" href="#aanbod">
            <div class="d-tile-label">Actieve kavels</div>
            <div class="d-tile-value">{{ $activeCount }}</div>
            <div class="d-tile-sub">nu te zien en te bieden</div>
        </a>
        <div class="d-tile">
            <div class="d-tile-label">Afgeronde verkopen</div>
            <div class="d-tile-value">{{ $completedSales }}</div>
            <div class="d-tile-sub">betaald, geleverd en zonder open geschil afgesloten</div>
        </div>
        <a class="d-tile" href="#verkocht">
            <div class="d-tile-label">Verkochte kavels</div>
            <div class="d-tile-value">{{ $soldCount }}</div>
            <div class="d-tile-sub">kavels met een koper, ook als de levering nog loopt</div>
        </a>
        <div class="d-tile">
            <div class="d-tile-label">Geschillen</div>
            <div class="d-tile-value">{{ $disputeCount }}</div>
            <div class="d-tile-sub">meldingen door kopers, gegrond of niet</div>
        </div>
    </div>

    <div class="d-profile">
        <div class="d-profile-main">

            {{-- Over de verkoper (masterbriefing 4.1): wie, in eigen woorden. --}}
            <section id="over" class="d-card d-profile-about" aria-label="Over de verkoper">
                @if ($company?->cover_path)
                    <img class="cover" src="{{ $company->cover_path }}" alt="">
                @endif
                <div class="row">
                    @if ($company?->logo_path)
                        <img class="logo" src="{{ $company->logo_path }}" alt="">
                    @endif
                    <div>
                        <h2>{{ $company?->name ?? $seller->publicName() }}</h2>
                        @if ($company?->description)
                            <p>{{ $company->description }}</p>
                        @else
                            <p class="is-muted">Deze verkoper heeft nog geen omschrijving toegevoegd.</p>
                        @endif
                    </div>
                </div>
            </section>

            <section id="aanbod" aria-labelledby="aanbod-title">
                <h2 id="aanbod-title" class="d-profile-h2">Kavels van deze verkoper</h2>
                @if ($liveLots->isNotEmpty())
                    <div class="d-profile-lots">
                        @foreach ($liveLots as $lot)
                            <x-lot.card :lot="$lot" />
                        @endforeach
                    </div>
                @else
                    <p class="d-profile-note">Deze verkoper heeft op dit moment geen lopende kavels.</p>
                @endif
            </section>

            {{-- De verdeling over álle sterren - ook waar nul staat (13.6). --}}
            <section id="reviews" aria-labelledby="reviews-title">
                <h2 id="reviews-title" class="d-profile-h2">Reviews</h2>
                <p class="d-profile-note">Koper en verkoper beoordelen elkaar na de transactie. Ook kritische reviews blijven staan.</p>

                @if (session('status'))
                    <p class="d-soft is-ok" style="margin:14px 0 0">{{ session('status') }}</p>
                @endif

                @if ($reviews->isEmpty())
                    <p class="d-profile-note">Nog geen beoordelingen.</p>
                @else
                    @php $peak = max(1, $distribution->max()); @endphp
                    <div class="d-callout-info" style="margin-top:16px">
                        <span class="mark" aria-hidden="true">i</span>
                        <div>
                            <strong>Gemiddeld {{ number_format((float) $averageRating, 1, ',', '') }} op basis van afgeronde verkopen</strong>
                            <dl class="d-starbars" aria-label="Beoordelingen, alle sterren">
                                @foreach ($distribution as $stars => $count)
                                    <div>
                                        <dt>{{ $stars }}★</dt>
                                        <span class="bar" aria-hidden="true"><i style="width:{{ intdiv($count * 100, $peak) }}%"></i></span>
                                        <dd>{{ $count }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>

                    <ul class="d-reviews">
                        @foreach ($reviews as $review)
                            <li>
                                <p class="head">
                                    <strong>{{ $review->rating }}★</strong>
                                    <span>· {{ $review->reviewer->publicName() }} · {{ $review->published_at->timezone($timezone)->isoFormat('D MMMM YYYY') }}</span>
                                </p>
                                @if ($review->body)
                                    <p>"{{ $review->body }}"</p>
                                @endif
                                @auth
                                    @if (auth()->id() !== $review->reviewer_id)
                                        <details>
                                            <summary>Review melden</summary>
                                            <form method="POST" action="{{ route('reviews.report', $review) }}">
                                                @csrf
                                                <label class="sr-only" for="reden-{{ $review->id }}">Reden</label>
                                                <select id="reden-{{ $review->id }}" name="reason" required class="d-input">
                                                    @foreach (\App\Reviews\ReviewReason::reportable() as $reason)
                                                        <option value="{{ $reason->value }}">{{ ucfirst($reason->label()) }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="text" name="note" maxlength="1000" placeholder="Toelichting (optioneel)" aria-label="Toelichting" class="d-input">
                                                <button type="submit" class="d-btn d-btn-secondary d-btn-sq">Melden</button>
                                            </form>
                                        </details>
                                    @endif
                                @endauth
                                @if ($review->seller_reply)
                                    <p class="reply">Reactie verkoper: "{{ $review->seller_reply }}"</p>
                                @elseif (auth()->id() === $seller->id)
                                    <form method="POST" action="{{ route('reviews.reply', $review) }}">
                                        @csrf
                                        <input type="text" name="reply" required maxlength="2000" placeholder="Reageer op deze beoordeling" aria-label="Reactie op review" class="d-input">
                                        <button type="submit" class="d-btn d-btn-secondary d-btn-sq">Reageren</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="d-hint" style="margin-top:14px">
                    Alleen kopers die de kavel afnamen kunnen beoordelen. De verkoper kan reageren, maar niets verwijderen.
                    Een medewerker haalt een review alleen offline bij scheldwoorden, discriminatie, politiek, persoonsgegevens of als hij niet over de aankoop gaat.
                </p>
            </section>

            {{-- Verkochte kavels (masterbriefing 4.1): filterbaar op categorie,
                 nieuwste eerst. Of de gerealiseerde prijs erbij staat volgt het
                 beleid uit PlatformFacts. --}}
            @if ($soldCount > 0)
                <section id="verkocht" aria-labelledby="verkocht-title">
                    <h2 id="verkocht-title" class="d-profile-h2">Verkochte kavels</h2>
                    <div class="d-chips" style="margin-top:16px" role="group" aria-label="Filter op categorie">
                        <a href="{{ route('sellers.show', $seller) }}#verkocht" @class(['d-chip', 'd-chip-sm', 'is-on' => $soldCategory === null])>Alle</a>
                        @foreach ($soldCategories as $category)
                            <a href="{{ route('sellers.show', [$seller, 'categorie' => $category->slug]) }}#verkocht" @class(['d-chip', 'd-chip-sm', 'is-on' => $soldCategory === $category->slug])>{{ $category->name }}</a>
                        @endforeach
                    </div>
                    <ul class="d-soldgrid">
                        @foreach ($soldLots as $lot)
                            <li>
                                <a href="{{ route('lots.show', $lot) }}">
                                    <span class="cover">
                                        @if ($lot->media->isNotEmpty())
                                            <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                                        @endif
                                    </span>
                                    <span class="text">
                                        <span class="cat">{{ $lot->category?->name ?? 'Kavel' }}</span>
                                        <strong>{{ $lot->title }}</strong>
                                        <span>Verkocht{{ PlatformFacts::publicSoldPrices() && $lot->relationLoaded('order') && $lot->order ? ' voor '.Money::format($lot->order->hammer_cents) : '' }}{{ $lot->auction?->closed_at ? ' · '.$lot->auction->closed_at->timezone($timezone)->isoFormat('D MMMM YYYY') : '' }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($soldLots->isEmpty())
                        <p class="d-profile-note">Geen verkochte kavels in deze categorie.</p>
                    @endif
                    <div style="margin-top:16px">{{ $soldLots->links() }}</div>
                    <p class="d-hint" style="margin-top:12px">{{ PlatformFacts::publicSoldPrices() ? 'Gerealiseerde prijzen zijn openbaar, zoals de biedgeschiedenis op elke kavel.' : 'Gerealiseerde prijzen zijn niet openbaar.' }}</p>
                </section>
            @endif
        </div>

        <aside class="d-profile-side">
            @if ($company?->verified_at !== null)
                <section class="d-sidecard" aria-labelledby="checked-title">
                    <h2 id="checked-title">Wat we hebben gecontroleerd</h2>
                    <p>Vóórdat een kavel live kan.</p>
                    <ul class="d-checklist">
                        @foreach (['KVK-inschrijving', 'Identiteit van de bevoegde persoon', 'Zakelijke rekening'] as $check)
                            <li><x-v4.icon name="check" :size="14" stroke="3" /> {{ $check }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{--
                De Trust Index: één cijfer, met de opbouw er transparant naast.
                Onder de tien transacties staat hier níéts - weinig data is
                geen onbetrouwbaarheid.
            --}}
            @if ($trust !== null)
                <section class="d-sidecard" id="trust" aria-labelledby="trust-title">
                    <h2 id="trust-title">Trust Index <span class="score">{{ $trust['score'] }}</span></h2>
                    <p>Berekend uit {{ $trust['transactions'] }} afgeronde transacties. Een hoge score is geen garantie; de staat van een individueel object beoordeelt Ivy per kavel.</p>
                    <dl class="d-factors">
                        @foreach ($trust['factors'] as $factor)
                            <div>
                                <dt>{{ $factor['label'] }} <span>weegt {{ $factor['weight'] }}%</span></dt>
                                <dd>{{ $factor['score'] }}</dd>
                                <span class="bar" aria-hidden="true"><i style="width:{{ $factor['score'] }}%"></i></span>
                                <p>{{ $factor['measures'] }}</p>
                            </div>
                        @endforeach
                    </dl>
                    <p class="d-hint">Reactietijd op vragen meten we nog niet; die factor telt bewust niet mee.</p>
                </section>
            @endif

            <section class="d-sidecard" aria-labelledby="how-title">
                <h2 id="how-title">Hoe de score werkt</h2>
                <p>Afgeronde transacties, documentkwaliteit, levering en geschilhistorie bouwen de score op. Pas vanaf {{ (int) config('aucvy.trust.minimum_transactions') }} afgeronde transacties tonen we een cijfer.</p>
            </section>
        </aside>
    </div>

    <x-marketing.cta text="Waarde-indicaties, de kosten vooraf en zien wat er hot is. Rondkijken en volgen kan zonder verificatie."
        primary="Ontdek de veilingen" :primary-url="route('lots.index')"
        secondary="Hoe bieden werkt" :secondary-url="route('how-it-works').'#kopen'">
        Slim bieden. <span class="g">Weten waar je aan toe bent.</span>
    </x-marketing.cta>
</x-layouts.public>
