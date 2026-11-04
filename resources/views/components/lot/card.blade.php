@use('App\Enums\SaleType')

@props(['lot'])

{{--
    De kavelkaart uit het ontwerp van november (KavelKaart), gevuld met
    echte data: staat als pil op de foto, categorie en plaats, titel, de
    gemeten interesse (kijkers, opgeslagen, gedeeld), de regel van Ivy, de
    prijs met de tijd en de waarde-indicatie van Atlas in een grijs vak.

    De tellers worden door het ontwerpscript (home.js) bijgehouden via
    data-countdown / data-dutch; de server rendert een correcte begintekst
    voor wie zonder JavaScript kijkt.
--}}
@php
    $auction = $lot->auction;
    $price = $lot->currentPriceCents();
    $isDutch = $lot->sale_type === SaleType::Dutch;
    $isBuyNow = $lot->sale_type === SaleType::BuyNow;
    $secondsLeft = $auction ? max(0, $auction->ends_at->getTimestamp() - now()->getTimestamp()) : 0;
    $lastChance = $auction !== null && ! $auction->isClosed() && $auction->ends_at->isBefore(now()->addDay());

    [$statusTone, $statusLabel] = match (true) {
        $isDutch => ['tone-atlas', 'Afslag'],
        $isBuyNow => ['tone-ok', 'Koop direct'],
        $lastChance => ['tone-warn', 'Laatste kans'],
        default => ['tone-live', 'Live'],
    };

    // Het ontwerp toont hele euro's ("€ 9.400"); centen alleen als ze er zijn.
    $euro = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.')
        .($cents % 100 !== 0 ? ','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT) : '');

    // De regel van Ivy: alleen echte inzichten van het AI-team, waar een
    // koper iets aan heeft. Niets te melden? Dan geen regel.
    $belowValue = $lot->mayShowValueComparison() && $price < $lot->value_min_cents;
    $withinRange = $lot->mayShowValueComparison()
        && $price >= $lot->value_min_cents
        && $price <= $lot->value_max_cents;

    [$ivyLine, $ivyTone] = match (true) {
        $belowValue => [(int) round(100 - ($price / $lot->value_min_cents * 100)).'% onder waarde-indicatie', ''],
        $isDutch && $lot->mayShowValueComparison() && $price > $lot->value_max_cents => ['Prijs daalt richting de waarde-range', 'is-neutral'],
        $withinRange => ['Nu binnen de waarde-range', 'is-neutral'],
        $lot->hasValueEstimate() && ! $lot->mayShowValueComparison() => ['Bezichtiging aangeraden', 'is-neutral'],
        $lot->documents->isNotEmpty() && $lot->documents->every(fn ($document) => $document->wasCheckedByIvy()) => ['Documenten gecontroleerd door Lex', 'is-neutral'],
        default => [null, ''],
    };

    $interest = \App\Lots\Interest\Interest::forBuyer($lot);
@endphp

<article {{ $attributes->merge(['class' => 'd-lotcard']) }}>
  <a href="{{ route('lots.show', $lot) }}">
    <div class="d-lotcard-media">
      @if ($lot->media->isNotEmpty())
        <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
      @else
        {{-- De abstracte plaatshouder uit het ontwerp; echte fotografie is een
             open punt (briefing hfst. 18). --}}
        <div class="ph"><svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect width="400" height="300" fill="#e3e7f0"/><rect x="40" y="150" width="320" height="90" fill="#cfd5e2"/><rect x="60" y="80" width="120" height="70" fill="#b9c2d4"/><rect x="220" y="60" width="140" height="90" fill="#c5cddc"/><rect x="40" y="240" width="320" height="8" fill="#aab3c7"/></svg></div>
      @endif
      <span class="d-tag {{ $statusTone }} status">{{ $statusLabel }}</span>
      @if ($lot->is_hot)<span class="d-tag tone-hot is-right"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>@endif
    </div>
    <div class="d-lotcard-body">
      <div class="d-lotcard-cat">{{ $lot->category?->name }}@if ($lot->location) · {{ $lot->location->city }}@endif</div>
      <h3>{{ $lot->title }}</h3>
      <div class="d-counts">
        <span title="{{ $interest['viewers'] }} kijkers"><x-v4.icon name="eye" :size="14" stroke="2" /><span aria-hidden="true">{{ number_format($interest['viewers'], 0, ',', '.') }}</span><span class="sr-only">{{ $interest['viewers'] }} kijkers</span></span>
        <span title="{{ $interest['saved'] }} keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" /><span aria-hidden="true">{{ $interest['saved'] }}</span><span class="sr-only">{{ $interest['saved'] }} keer opgeslagen</span></span>
        <span title="{{ $interest['shared'] }} keer gedeeld"><x-v4.icon name="share" :size="14" stroke="2" /><span aria-hidden="true">{{ $interest['shared'] }}</span><span class="sr-only">{{ $interest['shared'] }} keer gedeeld</span></span>
      </div>
      @if ($ivyLine)
        <div class="d-lotcard-ivy {{ $ivyTone }}"><img src="{{ asset('images/agents/ivy.jpg') }}" alt="" loading="lazy"><span>{{ $ivyLine }}</span></div>
      @endif
      <div class="d-lotcard-meta">
        @if ($isDutch && $auction?->hasDutchSettings())
          <div>
            <span class="label">Prijs nu</span>
            <span class="price tnum"
                  data-dutch="{{ $price }}"
                  data-dutch-start="{{ $auction->dutch_start_cents }}"
                  data-dutch-floor="{{ $auction->dutch_floor_cents }}"
                  data-dutch-step="{{ $auction->dutch_step_cents }}"
                  data-dutch-interval="{{ $auction->dutch_interval_s }}"
                  data-dutch-starts-at="{{ $auction->starts_at->toIso8601String() }}"
                  data-dutch-now="{{ now()->toIso8601String() }}">{{ $euro($price) }}</span>
          </div>
          <div class="time tnum" data-dutch-timer>daalt over {{ $auction->dutch_interval_s }}s</div>
        @elseif ($isBuyNow)
          <div>
            <span class="label">Vaste prijs</span>
            <span class="price tnum">{{ $euro($auction?->buy_now_price_cents ?? $price) }}</span>
          </div>
          <div class="time">Direct kopen</div>
        @else
          <div>
            <span class="label">Huidig bod</span>
            <span class="price tnum">{{ $euro($price) }}</span>
          </div>
          <div class="time tnum {{ $lastChance ? 'hot' : '' }}"
               data-countdown="{{ $secondsLeft }}">{{ $auction?->ends_at->diffForHumans(['short' => true, 'parts' => 1]) }}</div>
        @endif
      </div>
      @if ($lot->hasValueEstimate())
        <div class="d-lotcard-range">
          <span>Waarde-indicatie van Atlas{{ $lot->mayShowValueComparison() ? '' : ' · '.mb_strtolower($lot->value_confidence->label()) }}</span>
          <strong>{{ $euro($lot->value_min_cents) }} – {{ $euro($lot->value_max_cents) }}</strong>
        </div>
      @endif
    </div>
  </a>
</article>
