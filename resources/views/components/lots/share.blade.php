@props(['lot', 'compact' => false])

@php
    // Deellink met UTM per kanaal (briefing sectie 5, stap 10): zo zie je straks
    // in de statistieken waar kopers vandaan kwamen.
    $base = route('lots.show', $lot);
    $link = fn (string $medium): string => $base.'?utm_source=aucvy&utm_medium='.$medium.'&utm_campaign=kavel-'.$lot->public_id;
    $price = $lot->auction ? \App\Support\Money\Money::format($lot->currentPriceCents()) : null;
    $caption = $lot->title.($price ? ' · nu '.$price : '').' · bekijk op Aucvy: '.$link('social');
@endphp

<div {{ $attributes->merge(['class' => 'share'.($compact ? ' share-compact' : '')]) }}
     x-data="{ copied: null, async copy(text, what) { try { await navigator.clipboard.writeText(text); this.copied = what; setTimeout(() => this.copied = null, 2000); } catch (error) { window.prompt('Kopieer de tekst:', text); } } }">
    @unless ($compact)
        <h2 class="text-sm font-extrabold">Delen</h2>
        <p class="mt-1 text-xs text-aj-muted">Elke link heeft een eigen herkomst, zodat je ziet wat werkt.</p>
    @endunless
    <div class="share-row">
        <button type="button" class="btn btn-primary" @click="copy({{ Js::from($link('link')) }}, 'link')">
            <span x-text="copied === 'link' ? 'Gekopieerd' : 'Kopieer link'"></span>
        </button>
        <button type="button" class="btn btn-ghost" @click="copy({{ Js::from($caption) }}, 'tekst')">
            <span x-text="copied === 'tekst' ? 'Gekopieerd' : 'Kopieer tekst'"></span>
        </button>
        <a class="btn btn-ghost" target="_blank" rel="noopener" href="https://wa.me/?text={{ rawurlencode($lot->title.' · '.$link('whatsapp')) }}">WhatsApp</a>
        <a class="btn btn-ghost" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url={{ rawurlencode($link('linkedin')) }}">LinkedIn</a>
        <a class="btn btn-ghost" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text={{ rawurlencode($lot->title) }}&url={{ rawurlencode($link('x')) }}">X</a>
        <a class="btn btn-ghost" href="mailto:?subject={{ rawurlencode($lot->title.' op Aucvy') }}&body={{ rawurlencode($caption) }}">E-mail</a>
    </div>
    @unless ($compact)
        <p class="share-caption">{{ $caption }}</p>
    @endunless
</div>
