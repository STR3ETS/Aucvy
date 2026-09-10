@props(['done' => false, 'open' => false])

{{--
    Stap-icoon voor checklists: vinkje (klaar), pijl (nu aan de beurt) of
    open rondje (nog niet). Echte SVG's, geen tekstglyphs.
--}}
@if ($done)
    <svg {{ $attributes->merge(['class' => 'inline-block size-4 align-[-2px] text-aj-success']) }}
         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10" fill="none" stroke-width="2"/>
        <path d="M8 12.5l2.5 2.5L16 9.5"/>
    </svg>
@elseif ($open)
    <svg {{ $attributes->merge(['class' => 'inline-block size-4 align-[-2px] text-aj-border-strong']) }}
         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="12" cy="12" r="10"/>
    </svg>
@else
    <svg {{ $attributes->merge(['class' => 'inline-block size-4 align-[-2px] text-aj-blue-text']) }}
         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10" fill="none" stroke-width="2"/>
        <path d="M9 12h6m-2.5-3l3 3-3 3"/>
    </svg>
@endif
