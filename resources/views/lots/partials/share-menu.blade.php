{{--
    Delen door een koper (briefing nieuwe functies): altijd een menu met
    WhatsApp, e-mail, link kopiëren en het deelmenu van het apparaat, met een
    optionele notitie. Elke deling krijgt een eigen link met token en UTM.
--}}
<div class="relative flex-1" x-data="shareMenu({{ Js::from(route('lots.share', $lot)) }}, {{ Js::from($lot->title) }})" @keydown.escape.window="open = false">
    <button type="button" class="btn btn-ghost w-full" @click="open = ! open" :aria-expanded="open" aria-haspopup="true">
        <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="5" cy="10" r="2.2"/><circle cx="15" cy="4.5" r="2.2"/><circle cx="15" cy="15.5" r="2.2"/><path d="m7 9 6-3.6M7 11l6 3.6"/>
        </svg>
        <span x-text="done === 'link' ? 'Gekopieerd!' : 'Delen'"></span>
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-20 mt-2 w-72 rounded-aj-s border border-aj-border bg-aj-surface p-3 shadow-lg" role="menu" aria-label="Delen">
        <label class="block text-xs font-bold" for="share-note-{{ $lot->id }}">Notitie voor de ontvanger (mag leeg)</label>
        <input id="share-note-{{ $lot->id }}" type="text" x-model="note" maxlength="200" placeholder="Wat vind jij van de staat?" class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-2 text-sm" style="min-height:36px">
        <p class="mt-1 text-[11px] text-aj-muted">De ontvanger ziet de kavel en je notitie, nooit je bod of biednaam.</p>
        <div class="mt-2 grid gap-1.5">
            <button type="button" class="btn btn-ghost justify-start" style="height:36px;font-size:13px" role="menuitem" @click="share('whatsapp')" :disabled="busy">WhatsApp</button>
            <button type="button" class="btn btn-ghost justify-start" style="height:36px;font-size:13px" role="menuitem" @click="share('mail')" :disabled="busy">E-mail</button>
            <button type="button" class="btn btn-ghost justify-start" style="height:36px;font-size:13px" role="menuitem" @click="share('link')" :disabled="busy"><span x-text="done === 'link' ? 'Link gekopieerd' : 'Link kopiëren'"></span></button>
            <button type="button" class="btn btn-ghost justify-start" style="height:36px;font-size:13px" role="menuitem" x-show="canSystemShare" @click="share('system')" :disabled="busy">Meer opties van je apparaat</button>
        </div>
        <p x-show="error" x-text="error" class="mt-2 text-xs text-aj-error"></p>
    </div>

    @if (isset($ownShares) && $ownShares->isNotEmpty())
        <details class="mt-2 text-xs">
            <summary class="cursor-pointer font-semibold text-aj-muted">Je deelde deze kavel {{ $ownShares->count() }}× · {{ $ownShares->whereNotNull('opened_at')->count() }}× geopend</summary>
            <ul class="mt-1 space-y-1">
                @foreach ($ownShares as $share)
                    <li class="rounded-aj-sm bg-aj-bg px-2 py-1">{{ $share->channel()->label() }} · {{ $share->created_at->diffForHumans() }} · {{ $share->opened_at ? 'geopend' : 'nog niet geopend' }}@if ($share->reaction) · <span class="font-bold">{{ $share->reaction === \App\Models\LotShare::TEXT ? $share->reaction_text : $share->reactionLabel() }}</span>@endif</li>
                @endforeach
            </ul>
        </details>
    @endif
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('shareMenu', (endpoint, title) => ({
                open: false, note: '', busy: false, done: null, error: null,
                canSystemShare: typeof navigator.share === 'function',
                async share(channel) {
                    if (this.busy) return;
                    this.busy = true; this.error = null;
                    try {
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
                            body: JSON.stringify({ channel, note: this.note }),
                        });
                        if (!response.ok) throw new Error('Delen lukt nu niet. Probeer het zo nog eens.');
                        const { url, text } = await response.json();
                        if (channel === 'whatsapp') {
                            window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener');
                        } else if (channel === 'mail') {
                            window.location.href = 'mailto:?subject=' + encodeURIComponent(title + ' op Aucvy') + '&body=' + encodeURIComponent(text);
                        } else if (channel === 'system' && navigator.share) {
                            try { await navigator.share({ title, text: this.note || title, url }); } catch (e) {}
                        } else {
                            try { await navigator.clipboard.writeText(url); } catch (e) { window.prompt('Kopieer de link:', url); }
                        }
                        this.done = channel; this.note = '';
                        setTimeout(() => { this.done = null; this.open = false; }, 1800);
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.busy = false;
                    }
                },
            }));
        });
    </script>
@endonce
