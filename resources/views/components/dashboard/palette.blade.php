{{--
    Het zoekvenster van het dashboard: Ctrl K of een klik op het zoekveld in
    de bovenbalk opent het. Eén veld dat door pagina's, eigen kavels,
    bestellingen, veilingen en het aanbod zoekt. Pijltjes kiezen, Enter
    opent, Escape sluit. Het gedrag staat in resources/js/dash-overlays.js.
--}}
@php
    // De pictogrammen van de treffers, één keer op de server getekend.
    $paletteIcons = collect(['target', 'gavel', 'clock', 'trending-up', 'shopping-bag', 'message-circle', 'heart', 'search', 'bell', 'banknote', 'file-text', 'shield-check', 'settings', 'badge-check', 'plus', 'layout', 'user', 'monitor', 'building', 'users', 'share', 'eye', 'sparkles'])
        ->mapWithKeys(fn (string $name): array => [$name => \Illuminate\Support\Facades\Blade::render('<x-v4.icon name="'.$name.'" :size="17" stroke="2" />')]);
@endphp

<div x-data="commandPalette({{ Js::from(['url' => route('dashboard.search'), 'icons' => $paletteIcons, 'notifications' => route('notifications.index', [], false)]) }})"
     @palette-open.window="show()" @keydown.escape.window="if (open) close()">
    <div class="d-palette-scrim" x-show="open" x-cloak x-transition.opacity.duration.150ms @click="close()"></div>

    <div class="d-palette" x-show="open" x-cloak x-trap.noscroll="open" role="dialog" aria-modal="true" aria-label="Zoeken in Aucvy"
         x-transition:enter="is-entering" x-transition:enter-start="is-from" x-transition:enter-end="is-to">
        <div class="d-palette-field">
            <x-v4.icon name="search" :size="19" stroke="2.2" />
            <label class="sr-only" for="palette-q">Zoek in Aucvy</label>
            <input id="palette-q" x-ref="input" type="search" x-model="q" autocomplete="off" spellcheck="false"
                   placeholder="Zoek een kavel, bestelling, pagina of instelling"
                   role="combobox" aria-expanded="true" aria-controls="palette-list" :aria-activedescendant="hits.length ? 'palette-hit-' + active : null"
                   @input="onInput()" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="choose()">
            <kbd>Esc</kbd>
        </div>

        <div class="d-palette-list" id="palette-list" x-ref="list" role="listbox" aria-label="Treffers">
            <template x-for="(group, groupIndex) in groups" :key="group.label">
                <div class="d-palette-group">
                    <p x-text="group.label"></p>
                    <template x-for="(hit, hitIndex) in group.hits" :key="group.label + hitIndex">
                        <a class="d-palette-hit" role="option" :id="'palette-hit-' + position(groupIndex, hitIndex)"
                           :href="hit.url ?? '#'" :class="position(groupIndex, hitIndex) === active && 'is-active'" :aria-selected="position(groupIndex, hitIndex) === active"
                           @mousemove="active = position(groupIndex, hitIndex)" @click.prevent="follow(hit)">
                            <span class="ic" x-html="icon(hit.icon)"></span>
                            <span class="text">
                                <strong x-text="hit.title"></strong>
                                <small x-show="hit.subtitle" x-text="hit.subtitle"></small>
                            </span>
                            <span class="go" aria-hidden="true"><x-v4.icon name="arrow-right" :size="15" stroke="2.2" /></span>
                        </a>
                    </template>
                </div>
            </template>

            <p class="d-palette-empty" x-show="! loading && ! failed && ! groups.length" x-cloak>
                <strong x-text="'Niets gevonden voor &quot;' + q + '&quot;'"></strong>
                Probeer een ander woord, een kavelnummer of een bestelnummer.
            </p>
            <p class="d-palette-empty" x-show="failed" x-cloak><strong>Zoeken lukt nu niet.</strong> Controleer je verbinding en probeer het opnieuw.</p>
        </div>

        <div class="d-palette-foot" aria-hidden="true">
            <span><kbd><x-v4.icon name="arrow-up" :size="11" stroke="2.6" /></kbd><kbd><x-v4.icon name="arrow-down" :size="11" stroke="2.6" /></kbd> kiezen</span>
            <span><kbd>Enter</kbd> openen</span>
            <span><kbd>Esc</kbd> sluiten</span>
        </div>
    </div>
</div>
