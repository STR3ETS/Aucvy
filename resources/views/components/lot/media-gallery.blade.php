@props(['lot'])

@php
    $media = $lot->media;

    $items = $media->map(fn ($item) => [
        'path' => $item->path,
        'alt' => $item->alt ?? '',
    ])->values();

    $visibleThumbs = 6;
    $hiddenCount = max(0, $media->count() - $visibleThumbs);
@endphp

{{--
    Foto's staan op mobiel bewust bovenaan, vóór prijs en bod: je moet eerst
    zien wát je koopt (hfst. 12).

    De vaste beeldverhouding staat op de container en niet op het beeld zelf,
    zodat er geen layout shift optreedt terwijl de foto laadt.
--}}
<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @if ($media->isEmpty())
        {{--
            Geen stockbeeld en geen grijs vlak dat zich voordoet als een foto.
            Wie levert de fotografie staat nog open (hfst. 18), en tot die tijd
            is eerlijk zijn beter dan doen alsof.
        --}}
        <div class="flex aspect-[4/3] w-full flex-col items-center justify-center gap-2
                    rounded-aj-s border border-dashed border-aj-border-strong bg-aj-bg text-center">
            <span class="text-sm font-semibold text-aj-muted">Nog geen foto's</span>
            <span class="max-w-xs px-6 text-xs text-aj-muted">
                Vraag de verkoper om beeld voordat je biedt - of plan een bezichtiging.
            </span>
        </div>
    @else
        <div x-data="{ items: {{ Js::from($items) }}, active: 0, lightbox: false }">
            <figure class="relative overflow-hidden rounded-aj-s border border-aj-border">
                {{-- Klik op de hoofdfoto opent de volledige weergave. --}}
                <button type="button" class="block w-full cursor-zoom-in" @click="lightbox = true"
                        aria-label="Foto op volledige grootte bekijken">
                    <img src="{{ $media->first()->path }}"
                         :src="items[active].path"
                         alt="{{ $media->first()->alt ?? $lot->title }}"
                         :alt="items[active].alt || {{ Js::from($lot->title) }}"
                         width="{{ $media->first()->width }}"
                         height="{{ $media->first()->height }}"
                         class="aspect-[4/3] w-full object-cover"
                         loading="eager">
                </button>

                {{-- Teller plus bijschrift, zodat je weet wáár je kijkt. --}}
                <figcaption class="pointer-events-none absolute bottom-3 left-3 rounded-full bg-aj-navy/70 px-3 py-1 text-xs text-white"
                            x-text="(active + 1) + '/' + items.length + (items[active].alt ? ' · ' + items[active].alt : '')">
                </figcaption>
            </figure>

            @if ($media->count() > 1)
                {{-- Maximaal zes tegels; de laatste telt wat er nog meer is
                     en opent de volledige weergave. --}}
                <ul class="mt-2 grid grid-cols-6 gap-2" role="list">
                    @foreach ($media->take($visibleThumbs) as $index => $item)
                        @php $isOverflowTile = $loop->iteration === $visibleThumbs && $hiddenCount > 0; @endphp
                        <li>
                            <button type="button"
                                    class="relative block w-full overflow-hidden rounded-aj-sm border-2"
                                    :class="active === {{ $index }} && ! {{ $isOverflowTile ? 'true' : 'false' }} ? 'border-aj-blue' : 'border-transparent'"
                                    @click="{{ $isOverflowTile ? "active = {$index}; lightbox = true" : "active = {$index}" }}"
                                    @unless ($isOverflowTile) :aria-pressed="active === {{ $index }}" @endunless
                                    aria-label="{{ $isOverflowTile ? "Alle {$media->count()} foto's bekijken" : 'Foto '.($index + 1).($item->alt ? ', '.$item->alt : '') }}">
                                <img src="{{ $item->path }}"
                                     alt=""
                                     class="aspect-square w-full object-cover"
                                     loading="lazy">
                                @if ($isOverflowTile)
                                    <span class="absolute inset-0 flex items-center justify-center bg-aj-navy/60
                                                 text-sm font-extrabold text-white">+{{ $hiddenCount }}</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Volledige weergave: onbijgesneden beeld, bladeren met de
                 pijltjestoetsen, sluiten met Escape of een klik ernaast. --}}
            <div x-show="lightbox" x-cloak
                 class="fixed inset-0 z-50 flex flex-col gap-3 bg-aj-navy/95 p-4"
                 role="dialog" aria-modal="true" aria-label="Foto's op volledige grootte"
                 x-trap.noscroll="lightbox"
                 @keydown.escape.window="lightbox = false"
                 @keydown.arrow-right.window="if (lightbox) active = (active + 1) % items.length"
                 @keydown.arrow-left.window="if (lightbox) active = (active - 1 + items.length) % items.length">

                <div class="flex items-center justify-between text-white">
                    <span class="tabular text-sm" x-text="(active + 1) + '/' + items.length"></span>
                    <button type="button" @click="lightbox = false" aria-label="Sluiten"
                            class="flex size-10 items-center justify-center rounded-full bg-white/10">
                        <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" aria-hidden="true">
                            <path d="m5 5 10 10M15 5 5 15"/>
                        </svg>
                    </button>
                </div>

                <div class="relative flex min-h-0 flex-1 items-center justify-center" @click.self="lightbox = false">
                    <img :src="items[active].path"
                         :alt="items[active].alt || {{ Js::from($lot->title) }}"
                         class="max-h-full max-w-full rounded-aj-sm object-contain">

                    <template x-if="items.length > 1">
                        <div>
                            <button type="button" aria-label="Vorige foto"
                                    class="absolute left-0 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white"
                                    @click="active = (active - 1 + items.length) % items.length">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m12 4-6 6 6 6"/>
                                </svg>
                            </button>
                            <button type="button" aria-label="Volgende foto"
                                    class="absolute right-0 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white"
                                    @click="active = (active + 1) % items.length">
                                <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m8 4 6 6-6 6"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                <p class="min-h-5 text-center text-sm text-white/70"
                   x-text="items[active].alt"></p>
            </div>
        </div>
    @endif

    {{-- Wat er naast foto's nog is: documenten en bezichtiging. --}}
    <ul class="flex flex-wrap gap-2 text-sm">
        <li class="rounded-full border border-aj-border-strong bg-aj-surface px-3 py-1 font-semibold">
            Foto's <span class="tabular text-aj-muted">{{ $media->count() }}</span>
        </li>
        @if ($lot->documents->isNotEmpty())
            <li>
                <a href="#documenten"
                   class="block rounded-full border border-aj-border-strong bg-aj-surface px-3 py-1 font-semibold">
                    Documenten <span class="tabular text-aj-muted">{{ $lot->documents->count() }}</span>
                </a>
            </li>
        @endif
        @if ($lot->viewing_available)
            <li class="rounded-full border border-aj-border-strong bg-aj-surface px-3 py-1 font-semibold">
                Bezichtiging mogelijk
            </li>
        @endif
    </ul>
</div>
