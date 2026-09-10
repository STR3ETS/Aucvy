@props(['agent'])

@php
    $slug = $agent instanceof \App\Enums\AiAgent ? $agent->value : $agent;
@endphp

{{--
    Portret van een AI-specialist, met een ring in zijn eigen kleur. De alt
    zegt er eerlijk bij dat het een AI is - de foto's zijn gezichten, geen
    mensen (EU AI Act, en gewoon netjes).
--}}
<img src="{{ asset("images/agents/{$slug}.jpg") }}"
     alt="{{ ucfirst($slug) }}, AI-specialist"
     width="512" height="512" loading="lazy"
     {{ $attributes->merge(['class' => 'rounded-full object-cover']) }}
     style="box-shadow: 0 0 0 2px var(--color-aj-{{ $slug }})">
