@use('App\Enums\CompanyPlan')

{{--
    De verkooproute als systeemdemo (hfst. 2b): geen scrollpagina maar één
    scherm waarop de echte interface in het klein het hele proces naspeelt -
    funnel, Ivy-analyse, advertentie, waardebalk, live bieden en uitbetaling.
    De regie zit in selling-demo.js, de micro-animaties in app.css (.demo-*).
--}}
{{-- Geen over-hero: de pagina heeft geen donkere hero, dus de header hoort
     hier de vaste witte variant te zijn. De layout levert de inhoudskolom. --}}
<x-layouts.public title="Verkooproute" heading="Van foto tot uitbetaling" eyebrow="Verkopen op Aucvy"
    intro="Dit is het echte systeem in het klein. Jij levert de foto's en het akkoord; Ivy en haar team doen het werk ertussen.">
    <div>
        <div x-data="sellingDemo"
             class="grid gap-8 lg:min-h-[600px] lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)] lg:items-stretch">

            {{-- Links: uitleg, stappen en de actie. ----------------------- --}}
            <div class="flex flex-col">
                <ol class="space-y-1" aria-label="De zes stappen">
                    @foreach ([
                        [null, 'Upload je foto\'s'],
                        ['ivy', 'Ivy herkent je object'],
                        ['nova', 'Nova schrijft de advertentie'],
                        ['atlas', 'Atlas geeft de waarde-indicatie'],
                        ['finn', 'Live verkopen - jij kiest de vorm'],
                        ['flow', 'Flow handelt betaling en uitbetaling af'],
                    ] as $index => [$agent, $title])
                        <li>
                            <button type="button" @click="go({{ $index }})"
                                    class="relative flex w-full items-center gap-2.5 overflow-hidden rounded-aj-sm px-3 py-2 text-left text-sm"
                                    :class="scene === {{ $index }} ? 'bg-aj-surface font-extrabold shadow-sm' : 'text-aj-soft'"
                                    :aria-current="scene === {{ $index }} && 'step'">
                                <span class="tabular flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-extrabold"
                                      :class="scene === {{ $index }} ? 'bg-aj-blue text-white' : 'bg-aj-bg text-aj-muted'">{{ $index + 1 }}</span>
                                @if ($agent)
                                    <x-ui.agent-avatar :agent="$agent" class="size-6 shrink-0" />
                                @endif
                                <span class="min-w-0 truncate">{{ $title }}</span>
                                {{-- Voortgang van de spelende stap. --}}
                                <span class="absolute inset-x-0 bottom-0 h-0.5 bg-aj-blue"
                                      x-show="scene === {{ $index }} && playing"
                                      :style="`width:${progress}%`" aria-hidden="true"></span>
                            </button>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-4 flex items-center gap-3">
                    <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px"
                            @click="toggle()" :aria-pressed="! playing" x-show="! reduce">
                        <span x-show="playing">Pauze</span>
                        <span x-show="! playing" x-cloak>Afspelen</span>
                    </button>
                    <p class="text-xs text-aj-muted" x-show="! reduce">Speelt vanzelf af - klik een stap om te springen.</p>
                    <p class="text-xs text-aj-muted" x-show="reduce" x-cloak>Klik een stap om die te bekijken.</p>
                </div>

                <div class="mt-auto pt-6">
                    <p class="text-xs text-aj-muted">
                        Gratis plaatsen · succesfee alleen bij verkoop:
                        {{ number_format(CompanyPlan::Start->defaultFeePercentage(), 1, ',', '.') }}%,
                        {{ number_format(CompanyPlan::Pro->defaultFeePercentage(), 1, ',', '.') }}% of
                        {{ number_format(CompanyPlan::Enterprise->defaultFeePercentage(), 1, ',', '.') }}%
                        afhankelijk van je plan.
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('sell') }}" class="btn btn-primary">Kavel plaatsen</a>
                        <a href="{{ route('faq') }}" class="btn btn-ghost">Veelgestelde vragen</a>
                    </div>
                </div>
            </div>

            {{-- Rechts: het toneel waarop het systeem zichzelf laat zien. --}}
            <div class="relative flex items-center overflow-hidden rounded-aj-s border border-aj-border-strong bg-aj-bg p-5 sm:p-8"
                 aria-hidden="true">

                {{-- Scene 1 · Funnel: uploaden ----------------------------- --}}
                <template x-if="scene === 0">
                    <div class="mx-auto w-full max-w-md">
                        <p class="text-xs font-bold text-aj-muted">Kavel plaatsen · stap 1</p>
                        <div class="demo-in mt-2 rounded-aj-s border-2 border-dashed border-aj-border-strong bg-aj-surface p-6 text-center">
                            <p class="text-sm font-extrabold">Sleep je foto's hierheen</p>
                            <p class="mt-0.5 text-xs text-aj-muted">of maak ze direct met je telefoon</p>
                        </div>
                        <div class="mt-3 grid grid-cols-3 gap-2">
                            @foreach (['IMG_4821', 'IMG_4822', 'IMG_4823'] as $index => $file)
                                <div class="demo-in demo-d{{ $index + 1 }} overflow-hidden rounded-aj-sm border border-aj-border bg-aj-surface">
                                    <div class="flex aspect-[4/3] items-center justify-center"
                                         style="background:linear-gradient(135deg,#dfe4ee 0%,#c6cede 55%,#aab4c9 100%)">
                                        <svg class="size-6 text-white/80" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.2"/>
                                        </svg>
                                    </div>
                                    <p class="tabular px-2 py-1 text-[10px] text-aj-muted">{{ $file }}.jpg</p>
                                </div>
                            @endforeach
                        </div>
                        <p class="demo-in demo-d4 mt-3 flex items-center gap-1.5 text-xs font-semibold text-aj-success-text">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/></svg>
                            Drie foto's toegevoegd - genoeg om te beginnen
                        </p>
                        <div class="demo-in demo-d5 mt-3">
                            <span class="btn btn-primary w-full justify-center">Laat Ivy meekijken</span>
                        </div>
                    </div>
                </template>

                {{-- Scene 2 · Ivy-analyse ---------------------------------- --}}
                <template x-if="scene === 1">
                    <div class="mx-auto w-full max-w-md d-card">
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/agents/ivy.jpg') }}" alt="" class="size-10 rounded-full object-cover">
                            <p class="text-sm font-extrabold">Ivy kijkt mee
                                <span class="block text-xs font-normal text-aj-muted">AI-specialist</span></p>
                        </div>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li class="demo-in demo-d1 flex items-start gap-2">
                                <img src="{{ asset('images/agents/ivy.jpg') }}" alt="" class="mt-0.5 size-5 rounded-full object-cover">
                                <span><span class="font-bold">Herkend:</span> CNC-freesbank, Haas VF-2
                                    <span class="ml-1 rounded-full bg-aj-violet/10 px-2 py-0.5 text-xs font-bold text-aj-violet">82% zeker</span></span>
                            </li>
                            <li class="demo-in demo-d2 flex items-start gap-2">
                                <img src="{{ asset('images/agents/ivy.jpg') }}" alt="" class="mt-0.5 size-5 rounded-full object-cover">
                                <span><span class="font-bold">Nakijken:</span> bouwjaar - op het typeplaatje staat 2019</span>
                            </li>
                            <li class="demo-in demo-d3 flex items-start gap-2">
                                <img src="{{ asset('images/agents/nova.jpg') }}" alt="" class="mt-0.5 size-5 rounded-full object-cover">
                                <span><span class="font-bold">Nova:</span> eerste advertentietekst staat klaar</span>
                            </li>
                            <li class="demo-in demo-d4 flex items-start gap-2">
                                <img src="{{ asset('images/agents/atlas.jpg') }}" alt="" class="mt-0.5 size-5 rounded-full object-cover">
                                <span><span class="font-bold">Atlas:</span> waarde geschat op basis van 14 vergelijkbare verkopen</span>
                            </li>
                        </ul>
                        <p class="demo-in demo-d5 mt-4 rounded-aj-sm bg-aj-bg px-3 py-2 text-xs text-aj-muted">
                            Alles wat het team invult is een voorstel - jij past aan of keurt goed.
                        </p>
                    </div>
                </template>

                {{-- Scene 3 · De advertentie ontstaat ---------------------- --}}
                <template x-if="scene === 2">
                    <div class="mx-auto w-full max-w-sm overflow-hidden rounded-aj-s border border-aj-border bg-aj-surface">
                        <div class="relative flex aspect-[4/3] items-center justify-center"
                             style="background:linear-gradient(135deg,#dfe4ee 0%,#c6cede 55%,#aab4c9 100%)">
                            <svg class="size-10 text-white/80" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.2"/>
                            </svg>
                            <span class="absolute bottom-2 left-2 rounded-full bg-aj-navy/70 px-2.5 py-0.5 text-[11px] text-white">1/3</span>
                        </div>
                        <div class="p-4">
                            <p class="text-xs font-semibold text-aj-muted">Machines &amp; techniek · Utrecht</p>
                            <p class="mt-1 min-h-11 text-base font-extrabold leading-snug">
                                <span x-text="typedTitle"></span><span class="animate-pulse text-aj-blue">|</span>
                            </p>
                            <p class="demo-in demo-d3 text-xs text-aj-muted">
                                Direct uit een draaiende werkplaats. Eerlijk vermeld: twee krassen op de tafel,
                                zichtbaar op foto 2 en 3.
                            </p>
                            <div class="demo-in demo-d4 mt-3 flex flex-wrap gap-1.5 text-[11px]">
                                <span class="rounded-full bg-aj-bg px-2.5 py-1">Bouwjaar 2019</span>
                                <span class="rounded-full bg-aj-bg px-2.5 py-1">Conditie: gebruikt - goed</span>
                                <span class="rounded-full bg-aj-bg px-2.5 py-1">4.200 draaiuren</span>
                            </div>
                            <div class="demo-in demo-d5 mt-4 flex gap-2">
                                <span class="btn btn-ghost flex-1 justify-center" style="height:40px;font-size:13px">Aanpassen</span>
                                <span class="btn btn-primary flex-1 justify-center" style="height:40px;font-size:13px">Goedkeuren</span>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Scene 4 · De waardebalk -------------------------------- --}}
                <template x-if="scene === 3">
                    <div class="mx-auto w-full max-w-md rounded-aj-s border border-aj-violet/20 bg-aj-violet/5 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('images/agents/atlas.jpg') }}" alt="" class="size-10 rounded-full object-cover">
                                <p class="text-sm font-extrabold text-aj-violet">Marktadvies
                                    <span class="block text-xs font-normal text-aj-muted">Atlas, AI-specialist</span></p>
                            </div>
                            <p class="demo-in demo-d1 text-right text-sm font-bold">Marktwaarde
                                <span class="tabular block">€ 15.000 – € 19.500</span></p>
                        </div>
                        <div class="mt-5">
                            <div class="relative h-2.5">
                                <div class="absolute inset-0 rounded-full opacity-25"
                                     style="background:linear-gradient(90deg,#1c8a5b 0%,#b4690e 55%,#c0392b 100%)"></div>
                                <div class="demo-grow absolute inset-y-0 rounded-full"
                                     style="left:24%;width:44%;background-image:linear-gradient(90deg,#1c8a5b 0%,#b4690e 55%,#c0392b 100%);background-size:227% 100%;background-position-x:75%"></div>
                            </div>
                            <div class="mt-1.5 flex justify-between px-[20%] text-[11px] font-semibold text-aj-muted">
                                <span class="tabular">€ 15.000</span>
                                <span class="tabular">€ 19.500</span>
                            </div>
                        </div>
                        <p class="demo-in demo-d3 mt-3 text-xs text-aj-muted">
                            Zekerheid: midden · bron: 14 vergelijkbare verkopen in de laatste 12 maanden.
                        </p>
                        <p class="demo-in demo-d4 mt-1 text-xs font-semibold">Een range, geen belofte - jij kiest de vorm.</p>
                    </div>
                </template>

                {{-- Scene 5 · Live bieden (het echte paneel in het klein) -- --}}
                <template x-if="scene === 4">
                    <div class="panel-hero mx-auto w-full max-w-sm rounded-aj-s p-5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-xs font-extrabold text-aj-navy">
                                <span class="size-1.5 animate-pulse rounded-full bg-aj-error"></span> LIVE
                            </span>
                            <span class="tabular text-sm text-white/80">nog 2:14</span>
                        </div>
                        <p class="mt-3 text-xs text-white/70">Huidig bod</p>
                        <p class="tabular text-3xl font-extrabold" x-text="euro(price)"></p>
                        <div class="mt-2 space-y-1 text-xs text-white/85">
                            <p class="demo-in demo-d1 flex justify-between"><span class="tabular font-semibold">€ 16.000</span><span>A. Bakker</span></p>
                            <p class="demo-in demo-d2 flex justify-between"><span class="tabular font-semibold">€ 16.500</span><span>B. Klaassen</span></p>
                            <p class="demo-in demo-d3 flex justify-between"><span class="tabular font-semibold">€ 16.750</span><span>A. Bakker</span></p>
                        </div>
                        <div class="mt-4">
                            <div class="slide-confirm">
                                <div class="fill demo-fill"></div>
                                <span class="label label-past">Sleep om te bieden</span>
                                <div class="handle demo-handle">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M6 17l5-5-5-5"/><path d="M13 17l5-5-5-5"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <p class="demo-in demo-d6 mt-3 flex items-center gap-2 rounded-aj-sm bg-white/12 px-3 py-2 text-sm font-semibold text-emerald-200">
                            <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/></svg>
                            Elk laat bod verlengt de veiling - snipen lukt niet.
                        </p>
                    </div>
                </template>

                {{-- Scene 6 · Verkocht en uitbetaald ----------------------- --}}
                <template x-if="scene === 5">
                    <div class="mx-auto w-full max-w-md d-card">
                        <div class="flex items-center gap-3">
                            <img src="{{ asset('images/agents/flow.jpg') }}" alt="" class="size-10 rounded-full object-cover">
                            <p class="text-sm font-extrabold">Flow handelt af
                                <span class="block text-xs font-normal text-aj-muted">AI-specialist</span></p>
                        </div>
                        <ul class="mt-4 space-y-2 text-sm">
                            @foreach (['Verkocht voor € 16.750', 'Koper heeft betaald', 'Geleverd of opgehaald', 'Houdtermijn voorbij - geen geschil'] as $index => $stap)
                                <li class="demo-in demo-d{{ $index + 1 }} flex items-center gap-2.5">
                                    <svg class="size-4 shrink-0 text-aj-success" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.7-9.8a.8.8 0 0 0-1.2-1l-3.3 4-1.6-1.6a.8.8 0 1 0-1.2 1.1l2.3 2.3a.8.8 0 0 0 1.2-.1l3.8-4.7Z" clip-rule="evenodd"/></svg>
                                    {{ $stap }}
                                </li>
                            @endforeach
                        </ul>
                        <dl class="demo-in demo-d5 mt-4 space-y-1 border-t border-aj-border pt-3 text-sm">
                            <div class="flex justify-between"><dt class="text-aj-muted">Hamerprijs</dt><dd class="tabular font-semibold">€ 16.750</dd></div>
                            <div class="flex justify-between"><dt class="text-aj-muted">Succesfee ({{ number_format(CompanyPlan::Start->defaultFeePercentage(), 1, ',', '.') }}%)</dt><dd class="tabular font-semibold">- € 1.256</dd></div>
                            <div class="flex justify-between border-t border-aj-border pt-1.5">
                                <dt class="font-extrabold">Uitbetaald</dt>
                                <dd class="tabular text-lg font-extrabold text-aj-success-text" x-text="euro(payout)"></dd>
                            </div>
                        </dl>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-layouts.public>
