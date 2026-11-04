@props(['lot', 'status' => null, 'watching' => 0])

@php
    $media = $lot->media;

    $items = $media->map(fn ($item) => [
        'path' => $item->path,
        'alt' => $item->alt ?? '',
    ])->values();

    $visibleThumbs = 4;
    $hiddenCount = max(0, $media->count() - $visibleThumbs);
@endphp

{{--
    Foto's staan op mobiel bewust bovenaan, vóór prijs en bod: je moet eerst
    zien wát je koopt (hfst. 12). Opmaak: de galerij uit het ontwerp van
    november, met de staat, de kijkers en het bijschrift als pillen op de
    foto en vier tegels eronder.

    De vaste beeldverhouding staat op de container en niet op het beeld zelf,
    zodat er geen layout shift optreedt terwijl de foto laadt.
--}}
<div {{ $attributes->merge(['class' => 'd-stack-tight']) }}>
    @if ($media->isEmpty())
        {{--
            Geen stockbeeld en geen grijs vlak dat zich voordoet als een foto.
            Wie levert de fotografie staat nog open (hfst. 18), en tot die tijd
            is eerlijk zijn beter dan doen alsof.
        --}}
        <div class="flex aspect-[16/9] w-full flex-col items-center justify-center gap-2 text-center"
             style="border:1px dashed rgba(14,21,38,.2);border-radius:16px;background:#f3f5f9">
            <span class="text-sm font-semibold text-aj-muted">Nog geen foto's</span>
            <span class="max-w-xs px-6 text-xs text-aj-muted">
                Vraag de verkoper om beeld voordat je biedt - of plan een bezichtiging.
            </span>
        </div>
    @else
        <div x-data="{ items: {{ Js::from($items) }}, active: 0, lightbox: false }" class="d-stack-tight">
            <figure class="relative m-0" style="position:relative">
                {{-- Klik op de hoofdfoto opent de volledige weergave. --}}
                <button type="button" class="block w-full cursor-zoom-in" style="border-radius:16px;overflow:hidden;padding:0;border:0;background:#e9edf5" @click="lightbox = true"
                        aria-label="Foto op volledige grootte bekijken">
                    <img src="{{ $media->first()->path }}"
                         :src="items[active].path"
                         alt="{{ $media->first()->alt ?? $lot->title }}"
                         :alt="items[active].alt || {{ Js::from($lot->title) }}"
                         width="{{ $media->first()->width }}"
                         height="{{ $media->first()->height }}"
                         class="w-full object-cover" style="display:block;aspect-ratio:16/9"
                         loading="eager">
                </button>
                @if ($status)
                    <span class="d-gallery-status d-tag {{ $status[0] }}">{{ $status[1] }}</span>
                @endif
                @if ($watching > 0)
                    <span class="d-gallery-pill" style="top:12px;right:12px"><x-v4.icon name="eye" :size="14" stroke="2.2" /> {{ $watching }} kijken nu</span>
                @endif
                {{-- Teller plus bijschrift, zodat je weet wáár je kijkt. --}}
                <figcaption class="d-gallery-pill" style="left:12px;bottom:12px"
                            x-text="(active + 1) + '/' + items.length + (items[active].alt ? ' · ' + items[active].alt : '')">
                </figcaption>
            </figure>

            @if ($media->count() > 1)
                {{-- Vier tegels; de laatste telt wat er nog meer is en opent de volledige weergave. --}}
                <div class="d-thumbs" role="list">
                    @foreach ($media->take($visibleThumbs) as $index => $item)
                        @php $isOverflowTile = $loop->iteration === $visibleThumbs && $hiddenCount > 0; @endphp
                        <button type="button" role="listitem"
                                :class="active === {{ $index }} && ! {{ $isOverflowTile ? 'true' : 'false' }} && 'is-on'"
                                @click="{{ $isOverflowTile ? "active = {$index}; lightbox = true" : "active = {$index}" }}"
                                @unless ($isOverflowTile) :aria-pressed="active === {{ $index }}" @endunless
                                aria-label="{{ $isOverflowTile ? "Alle {$media->count()} foto's bekijken" : 'Foto '.($index + 1).($item->alt ? ', '.$item->alt : '') }}">
                            <img src="{{ $item->path }}" alt="" loading="lazy">
                            @if ($isOverflowTile)
                                <span class="absolute inset-0 flex items-center justify-center text-sm font-extrabold text-white" style="background:rgba(12,19,38,.6)">+{{ $hiddenCount }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
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
    <div class="d-chips">
        <span class="d-chip is-blue">Foto's <span class="tabular" style="color:#4d5566">{{ $media->count() }}</span></span>
        @if ($lot->documents->isNotEmpty())
            <a href="#documenten" class="d-chip">Documenten <span class="tabular" style="color:#4d5566">{{ $lot->documents->count() }}</span></a>
        @endif
        @if ($lot->viewing_available)
            <a href="#bezichtiging" class="d-chip"><x-v4.icon name="clock" :size="15" stroke="2.2" /> Bezichtiging plannen</a>
        @endif
    </div>
</div>
