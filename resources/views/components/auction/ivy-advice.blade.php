@props(['lot'])

{{--
    Biedadvies (hfst. 8: Atlas + Finn). De bedragen komen uit PHP - de
    waardeschatting staat al op de kavel, het minimumbod in de veilingstand.
    Ivy levert de duiding; bij lage zekerheid verzwijgt het paneel de exacte
    bedragen (ConfidencePolicy). En altijd: jij beslist.
--}}
@auth
    {{-- De insight-chip "Wat is een verstandig bod?" springt hierheen en
         start het advies meteen. --}}
    <div x-data="ivyAdvice({{ $lot->id }})"
         id="biedadvies"
         @ivy-ask.window="if (state === 'idle') ask()"
         class="rounded-aj-s border border-aj-border bg-aj-surface p-4">

        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <x-ui.agent-avatar agent="atlas" class="size-9" />
                <p class="text-sm font-extrabold">
                    Biedadvies van Atlas
                    <span class="block text-xs font-normal text-aj-muted">AI-specialist</span>
                </p>
            </div>
            <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px"
                    x-show="state === 'idle'" @click="ask()">
                Vraag advies
            </button>
        </div>

        <p x-show="state === 'busy'" x-cloak class="mt-2 text-sm text-aj-muted" aria-live="polite">
            Atlas kijkt naar het biedverloop…
        </p>

        <div x-show="state === 'done'" x-cloak class="mt-2 space-y-2 text-sm" aria-live="polite">
            <p x-text="advice?.output?.assessment"></p>

            <p x-show="advice?.output?.risks" class="text-aj-muted" x-text="advice?.output?.risks"></p>

            @if ($lot->value_min_cents !== null && $lot->value_max_cents !== null)
                {{-- Bedragen uit PHP, alleen bij voldoende zekerheid. --}}
                <p x-show="showAmounts" class="rounded-aj-sm bg-aj-bg p-2">
                    Marktwaarde volgens de schatting:
                    <span class="tabular font-bold">{{ \App\Support\Money\Money::format($lot->value_min_cents) }}
                        – {{ \App\Support\Money\Money::format($lot->value_max_cents) }}</span>.
                    Daarboven bieden is zelden verstandig.
                </p>
            @endif

            <p class="text-xs text-aj-muted">
                <span x-text="advice?.confidence_hint"></span>
                <strong>Jij beslist.</strong>
            </p>
        </div>

        <p x-show="state === 'unavailable'" x-cloak class="mt-2 text-sm text-aj-muted">
            Advies is nu even niet beschikbaar - de cijfers hierboven vertellen het meeste.
        </p>
    </div>
@endauth
