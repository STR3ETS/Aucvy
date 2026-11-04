@props(['lot', 'stats'])

@php
    $sellerName = $lot->company?->name ?? $lot->seller->publicName();
    $initials = collect(explode(' ', $sellerName))
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $memberSince = $lot->company?->verified_at ?? $lot->seller->created_at;
    $facts = array_values(array_filter([
        $lot->company?->isVerified() ? 'Geverifieerd' : null,
        $stats['average_rating'] !== null ? number_format($stats['average_rating'], 1, ',', '.').' · '.trans_choice(':count review|:count reviews', $stats['review_count'], ['count' => $stats['review_count']]) : null,
        $stats['completed_sales'] > 0 ? $stats['completed_sales'].' verkopen' : null,
        $memberSince ? 'lid sinds '.$memberSince->format('Y') : null,
    ]));
@endphp

{{--
    De verkoperskaart toont alleen gemeten feiten: gepubliceerde reviews en
    afgeronde verkopen. Geen verzonnen betrouwbaarheidsscore (hfst. 13.6).
    Opmaak: de kaart uit het ontwerp van november, als één link.
--}}
<a href="{{ route('sellers.show', $lot->seller) }}" {{ $attributes->merge(['class' => 'd-seller']) }}>
    <span class="d-seller-mark" aria-hidden="true">{{ $initials }}</span>
    <span class="d-seller-text">
        <strong>{{ $sellerName }}</strong>
        <span>{{ implode(' · ', $facts) }}</span>
    </span>
    @if (($stats['trust_score'] ?? null) !== null)
        <span class="d-tag tone-ok" title="Berekend uit afgeronde transacties, documenten, reviews en levering - geen garantie">Trust Index {{ $stats['trust_score'] }}</span>
    @endif
    <span class="d-link">Bekijk profiel</span>
</a>
