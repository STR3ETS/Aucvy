@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

{{--
    De lijst onder de werkruimte van de verkoper: de actieve kavels met hun
    interesse en huidige bod, en de concepten die af moeten. De andere tabs
    zijn de pagina's zelf.
--}}
@php $displayTimezone = config('aucvy.display_timezone'); @endphp

<section class="dash-section d-stack-tight" aria-labelledby="actief-title">
    <div class="d-chips">
        <span class="d-chip is-on" aria-current="page" id="actief-title">Actieve kavels</span>
        <a class="d-chip" href="{{ route('dashboard.bids') }}">Biedingen</a>
        <a class="d-chip" href="{{ route('orders.index') }}">Verkocht</a>
        @if ($user->canSeeCompanyFinance())
            <a class="d-chip" href="{{ route('dashboard.payouts') }}">Uitbetalingen</a>
        @endif
        <a class="d-chip" href="{{ route('dashboard.lots') }}">Alle kavels</a>
    </div>

    @if ($liveLots->isEmpty() && $drafts->isEmpty())
        <p class="feed-empty" style="margin:0">Nog geen kavels. <a href="{{ route('sell') }}" class="d-link">Plaats je eerste kavel</a>: een paar foto's zijn genoeg, Ivy doet de rest.</p>
    @endif

    @foreach ($liveLots as $lot)
        @php
            $auction = $lot->auction;
            $stats = \App\Lots\Interest\Interest::forBuyer($lot);
            $closesSoon = $auction !== null && ! $auction->isClosed() && $auction->ends_at->isBefore(now()->addDay());
            $isDutch = $lot->sale_type === SaleType::Dutch;
        @endphp
        <article class="d-lot">
            <div class="d-lot-row">
                @if ($lot->media->first())
                    <img class="d-lot-thumb" src="{{ $lot->media->first()->path }}" alt="">
                @else
                    <span class="d-lot-thumb" aria-hidden="true"></span>
                @endif
                <div class="d-lot-body">
                    <div class="d-lot-title">
                        <strong><a href="{{ route('dashboard.lots.show', $lot) }}">{{ $lot->title }}</a></strong>
                        @if ($lot->is_hot)
                            <span class="d-tag tone-warn">Hot{{ $closesSoon && $auction ? ' · sluit '.$auction->ends_at->timezone($displayTimezone)->diffForHumans(short: true) : '' }}</span>
                        @elseif ($closesSoon && $auction)
                            <span class="d-tag tone-warn">Laatste kans · {{ $auction->ends_at->timezone($displayTimezone)->diffForHumans() }}</span>
                        @else
                            <span class="d-tag tone-live">Live</span>
                        @endif
                    </div>
                    <div class="d-lot-meta">Kavel #{{ $lot->public_id ?? $lot->id }} · {{ $lot->sale_type->label() }} · {{ $stats['viewers'] }} kijkers · {{ $stats['saved'] }} opgeslagen · {{ $stats['shared'] }} gedeeld · {{ $stats['bidders'] }} bieders</div>
                </div>
                <div class="d-lot-price">
                    <div>{{ $isDutch ? 'Prijs nu' : ($lot->sale_type === SaleType::BuyNow ? 'Prijs' : 'Huidig bod') }}</div>
                    <div>{{ Money::format($lot->currentPriceCents()) }}</div>
                </div>
                <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('dashboard.lots.show', $lot) }}">Dossier</a>
            </div>
        </article>
    @endforeach

    @foreach ($drafts as $lot)
        <article class="d-lot">
            <div class="d-lot-row">
                @if ($lot->media->first())
                    <img class="d-lot-thumb" src="{{ $lot->media->first()->path }}" alt="">
                @else
                    <span class="d-lot-thumb" aria-hidden="true"></span>
                @endif
                <div class="d-lot-body">
                    <div class="d-lot-title">
                        <strong>{{ $lot->title ?: 'Concept zonder titel' }}</strong>
                        <span class="d-tag tone-atlas">Concept</span>
                    </div>
                    <div class="d-lot-meta">Concept · laatst bewerkt {{ $lot->updated_at->timezone($displayTimezone)->diffForHumans() }}</div>
                </div>
                <div class="d-lot-price">
                    <div>Nog te doen</div>
                    <div>Verder</div>
                </div>
                <a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('sell', ['concept' => $lot->id]) }}">Maak af</a>
            </div>
        </article>
    @endforeach
</section>
