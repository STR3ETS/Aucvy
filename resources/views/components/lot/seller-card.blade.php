@props(['lot', 'stats'])

@php
    $sellerName = $lot->company?->name ?? $lot->seller->publicName();
    $initials = collect(explode(' ', $sellerName))
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
    $memberSince = $lot->company?->verified_at ?? $lot->seller->created_at;
@endphp

{{--
    De verkoperskaart toont alleen gemeten feiten: gepubliceerde reviews en
    afgeronde verkopen. Geen verzonnen betrouwbaarheidsscore (hfst. 13.6).
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-3 rounded-aj-s border border-aj-border bg-aj-surface p-4']) }}>
    <span class="flex size-11 shrink-0 items-center justify-center rounded-aj-sm bg-aj-blue/10
                 text-sm font-extrabold text-aj-blue-text" aria-hidden="true">{{ $initials }}</span>

    <div class="min-w-0 flex-1">
        <p class="flex items-center gap-1.5 text-sm font-extrabold">
            <span class="truncate">{{ $sellerName }}</span>
            @if ($lot->company?->isVerified())
                <svg class="size-4 shrink-0 text-aj-success" viewBox="0 0 20 20" fill="currentColor"
                     role="img" aria-label="Geverifieerd">
                    <path fill-rule="evenodd" d="M16.4 3.9a1 1 0 0 1 .2 1.4l-7.5 10a1 1 0 0 1-1.5.1l-4-4a1 1 0 1 1 1.4-1.4l3.2 3.2 6.8-9a1 1 0 0 1 1.4-.3Z" clip-rule="evenodd"/>
                </svg>
            @endif
        </p>

        <p class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-aj-muted">
            @if ($stats['average_rating'] !== null)
                <span class="flex items-center gap-0.5" role="img"
                      aria-label="{{ number_format($stats['average_rating'], 1, ',', '.') }} van 5 sterren">
                    @for ($star = 1; $star <= 5; $star++)
                        <svg class="size-3.5 {{ $star <= round($stats['average_rating']) ? 'text-aj-warn' : 'text-aj-border-strong' }}"
                             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10 1.8l2.4 4.9 5.4.8-3.9 3.8.9 5.4L10 14.1l-4.8 2.6.9-5.4L2.2 7.5l5.4-.8L10 1.8Z"/>
                        </svg>
                    @endfor
                </span>
                <span class="tabular font-bold text-aj-text">{{ number_format($stats['average_rating'], 1, ',', '.') }}</span>
                <span>· {{ trans_choice(':count review|:count reviews', $stats['review_count'], ['count' => $stats['review_count']]) }}</span>
            @endif
            @if ($stats['completed_sales'] > 0)
                <span>· {{ $stats['completed_sales'] }} verkopen</span>
            @endif
            @if ($memberSince)
                <span>· lid sinds {{ $memberSince->format('Y') }}</span>
            @endif
        </p>
    </div>

    @if (($stats['trust_score'] ?? null) !== null)
        <span class="flex shrink-0 items-center gap-1 rounded-full bg-aj-success/10 px-2.5 py-1 text-xs font-extrabold text-aj-success-text"
              title="Berekend uit afgeronde transacties, documenten, reviews en levering - geen garantie">
            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/>
            </svg>
            Trust Index {{ $stats['trust_score'] }}
        </span>
    @endif

    <a href="{{ route('sellers.show', $lot->seller) }}"
       class="btn btn-ghost shrink-0" style="height:40px;font-size:13px">
        Bekijk profiel
        <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 10h12m-5-5 5 5-5 5"/>
        </svg>
    </a>
</div>
