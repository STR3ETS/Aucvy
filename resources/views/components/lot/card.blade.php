@use('App\Enums\SaleType')

@props(['lot'])

{{--
    De kavelkaart uit aucvy-home.html (.lot), gevuld met echte data.

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

    [$statusClass, $statusLabel] = match (true) {
        $isDutch => ['dutch', 'Afslag'],
        $isBuyNow => ['buy', 'Koop direct'],
        $lastChance => ['last', 'Laatste kans'],
        default => ['live', 'Live'],
    };

    // Het ontwerp toont hele euro's ("€ 9.400"); centen alleen als ze er zijn.
    $euro = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.')
        .($cents % 100 !== 0 ? ','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT) : '');

    // De paarse Ivy-regel: alleen echte inzichten van het AI-team, waar een
    // koper iets aan heeft. Niets te melden? Dan geen regel - een teller of
    // een transportfeit is geen inzicht.
    $belowValue = $lot->mayShowValueComparison() && $price < $lot->value_min_cents;
    $withinRange = $lot->mayShowValueComparison()
        && $price >= $lot->value_min_cents
        && $price <= $lot->value_max_cents;

    $ivyLine = match (true) {
        $belowValue => (int) round(100 - ($price / $lot->value_min_cents * 100)).'% onder waarde-indicatie',
        $isDutch && $lot->mayShowValueComparison() && $price > $lot->value_max_cents => 'Prijs daalt richting de waarde-range',
        $withinRange => 'Nu binnen de waarde-range',
        $lot->hasValueEstimate() && ! $lot->mayShowValueComparison() => 'Bezichtiging aangeraden',
        $lot->documents->isNotEmpty() && $lot->documents->every(fn ($document) => $document->wasCheckedByIvy()) => 'Documenten gecontroleerd door Lex',
        default => null,
    };
@endphp

<article {{ $attributes->merge(['class' => 'lot']) }}>
  <a href="{{ route('lots.show', $lot) }}">
    <div class="media">
      @if ($lot->media->isNotEmpty())
        <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
      @else
        {{-- De abstracte plaatshouder uit het ontwerp; echte fotografie is een
             open punt (briefing hfst. 18). --}}
        <div class="ph"><svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><rect width="400" height="300" fill="#e3e7f0"/><rect x="40" y="150" width="320" height="90" fill="#cfd5e2"/><rect x="60" y="80" width="120" height="70" fill="#b9c2d4"/><rect x="220" y="60" width="140" height="90" fill="#c5cddc"/><rect x="40" y="240" width="320" height="8" fill="#aab3c7"/></svg></div>
      @endif
      <span class="status {{ $statusClass }}">@if ($statusClass === 'live')<i></i>@endif{{ $statusLabel }}</span>
    </div>
    <div class="body">
      <span class="cat">{{ $lot->category?->name }}@if ($lot->location) · {{ $lot->location->city }}@endif</span>
      <h3>{{ $lot->title }}</h3>
      {{-- De foto van Ivy in plaats van het ruitje uit het ontwerp
           (app.css zet het ::before-ruitje uit). --}}
      @if ($ivyLine)
        {{-- De regel blijft altijd één rij: langere teksten worden afgekapt. --}}
        <span class="ivy"><img src="{{ asset('images/agents/ivy.jpg') }}" alt="" loading="lazy"
               class="size-4 shrink-0 rounded-full object-cover"><span class="min-w-0 truncate">{{ $ivyLine }}</span></span>
      @endif
      <div class="meta">
        @if ($isDutch && $auction?->hasDutchSettings())
          <div>
            <span class="label">Prijs nu</span>
            <span class="price tnum"
                  data-dutch="{{ intdiv($price, 100) }}"
                  data-step="{{ intdiv($auction->dutch_step_cents, 100) }}"
                  data-floor="{{ intdiv($auction->dutch_floor_cents, 100) }}">{{ $euro($price) }}</span>
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

      {{--
          De waardebalk, in dezelfde taal als de kavelpagina: vage prijsas,
          fel afgerond segment als marktwaarde, bolletje voor de huidige
          prijs. Het bolletje alleen bij voldoende zekerheid (hfst. 8).
      --}}
      @if ($lot->hasValueEstimate())
        @php
            $axisMin = (int) floor($lot->value_min_cents * 0.7);
            $axisMax = max((int) ceil($lot->value_max_cents * 1.3), $axisMin + 100);
            $axisPosition = fn (int $cents): float => round(max(2, min(98, ($cents - $axisMin) / ($axisMax - $axisMin) * 100)), 1);
            $bandLeft = $axisPosition($lot->value_min_cents);
            $bandWidth = max(2, $axisPosition($lot->value_max_cents) - $bandLeft);
            $axisGradient = 'linear-gradient(90deg,#1c8a5b 0%,#b4690e 55%,#c0392b 100%)';
        @endphp
        <div class="mt-2">
          {{-- Altijd één regel: het bedrag blijft heel, het label kapt af. --}}
          <div class="flex items-baseline justify-between gap-2 text-[11px]">
            <span class="min-w-0 truncate font-semibold text-aj-muted">Ivy-waarderange (indicatie)</span>
            <span class="tnum shrink-0 whitespace-nowrap font-bold">{{ $euro($lot->value_min_cents) }} – {{ $euro($lot->value_max_cents) }}</span>
          </div>
          <div class="relative mt-1.5 h-1.5"
               title="Marktwaarde {{ $euro($lot->value_min_cents) }} – {{ $euro($lot->value_max_cents) }}">
            <div class="absolute inset-0 rounded-full opacity-25" style="background:{{ $axisGradient }}"></div>
            <div class="absolute inset-y-0 rounded-full"
                 style="left:{{ $bandLeft }}%;width:{{ $bandWidth }}%;
                        background-image:{{ $axisGradient }};
                        background-size:{{ round(10000 / $bandWidth, 2) }}% 100%;
                        background-position-x:{{ round(100 * $bandLeft / (100 - $bandWidth), 2) }}%"></div>
            @if ($lot->mayShowValueComparison() && $price > 0)
              <div class="absolute top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-aj-navy shadow-sm"
                   style="left:{{ $axisPosition($price) }}%" title="Huidige prijs"></div>
            @endif
          </div>
        </div>
      @endif
    </div>
  </a>
</article>
