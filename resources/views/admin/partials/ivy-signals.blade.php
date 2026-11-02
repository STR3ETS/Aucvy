{{-- Ivy-statusmeldingen (besluit Raph, 2 november 2026: zoals Izzy bij Eazy). --}}
<section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="ivy-status" x-data="{ all: false }">
    <div class="flex items-center gap-2">
        <span class="grid size-8 place-items-center rounded-full bg-aj-navy text-xs font-extrabold text-white" aria-hidden="true">Ivy</span>
        <h2 id="ivy-status" class="text-lg font-extrabold">
            {{ $signals === [] ? 'Alles loopt' : 'Dit vraagt aandacht' }}
        </h2>
    </div>

    @if ($signals === [])
        <p class="mt-2 text-sm text-aj-muted">Geen open fouten, niets dat op een medewerker wacht en alle systeemchecks staan op groen.</p>
    @else
        <ul class="mt-3 space-y-2">
            @foreach ($signals as $index => $signal)
                <li @if ($index >= 3) x-show="all" x-cloak @endif>
                    <a href="{{ $signal['url'] }}" class="flex gap-3 rounded-aj-sm border border-aj-border p-3 text-sm hover:bg-aj-blue/5">
                        <span @class([
                            'mt-1.5 size-2 flex-none rounded-full',
                            'bg-aj-error' => $signal['level'] === 'fail',
                            'bg-aj-warn' => $signal['level'] === 'warn',
                            'bg-aj-blue' => $signal['level'] === 'info',
                        ]) aria-hidden="true"></span>
                        <span>
                            <span class="font-bold">{{ $signal['title'] }}</span>
                            <span class="block text-aj-muted">{{ $signal['text'] }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        @if (count($signals) > 3)
            <button type="button" class="mt-2 text-xs font-semibold underline" @click="all = ! all"
                    x-text="all ? 'Minder tonen' : 'Nog {{ count($signals) - 3 }} meer'"></button>
        @endif
    @endif
</section>
