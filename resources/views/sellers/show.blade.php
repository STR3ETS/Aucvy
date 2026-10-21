<x-layouts.public :title="$seller->publicName()" eyebrow="Verkoper">
    {{-- De feiten onder de naam staan in de paginakop, als lichte regel. --}}
    <x-slot:lead>
        <div class="flex flex-wrap items-center gap-2 text-sm">
            @if ($company?->verified_at !== null || $seller->kyc_status->allowsBidding())
                <span class="rounded-full bg-white/15 px-2.5 py-0.5 font-bold text-white">Geverifieerd</span>
            @endif
            @if ($averageRating !== null)
                <span class="tabular font-bold text-white">{{ number_format($averageRating, 1, ',', '') }}★</span>
                <span>· {{ $completedSales }} {{ $completedSales === 1 ? 'verkoop' : 'verkopen' }}</span>
            @endif
            <span>· lid sinds {{ $seller->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('MMMM YYYY') }}</span>
            <span>· {{ $disputeCount }} {{ $disputeCount === 1 ? 'geschil' : 'geschillen' }}</span>
        </div>
    </x-slot:lead>
    <div class="mx-auto max-w-4xl">

        {{--
            De Trust Index (preview v4): één cijfer, met de opbouw er
            transparant naast. Onder de tien transacties staat hier níéts -
            weinig data is geen onbetrouwbaarheid.
        --}}
        @if ($trust !== null)
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-label="Trust Index">
                <div class="flex flex-wrap items-center gap-6">
                    @php
                        $size = 132;
                        $radius = $size / 2 - 11;
                        $circumference = 2 * pi() * $radius;
                    @endphp
                    <div class="relative shrink-0" style="width:{{ $size }}px;height:{{ $size }}px">
                        <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" role="img"
                             aria-label="Trust Index {{ $trust['score'] }} van 100">
                            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" fill="none"
                                    stroke="var(--color-aj-border)" stroke-width="9"/>
                            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" fill="none"
                                    stroke="var(--color-aj-success)" stroke-width="9" stroke-linecap="round"
                                    stroke-dasharray="{{ $circumference }}"
                                    stroke-dashoffset="{{ $circumference * (1 - $trust['score'] / 100) }}"
                                    transform="rotate(-90 {{ $size / 2 }} {{ $size / 2 }})"/>
                        </svg>
                        <span class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="tabular text-3xl font-extrabold">{{ $trust['score'] }}</span>
                            <span class="text-xs font-bold text-aj-muted">van 100</span>
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-extrabold">Trust Index</h2>
                        <p class="mt-1 text-sm text-aj-muted">
                            Berekend uit {{ $trust['transactions'] }} afgeronde transacties. Een hoge score is
                            geen garantie; de staat van een individueel object beoordeelt Ivy per kavel.
                        </p>

                    </div>
                </div>

                <dl class="mt-5 grid gap-x-8 gap-y-2 border-t border-aj-border pt-4 sm:grid-cols-2">
                    @foreach ($trust['factors'] as $factor)
                        <div class="text-sm">
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="font-bold">{{ $factor['label'] }}
                                    <span class="text-xs font-normal text-aj-muted">weegt {{ $factor['weight'] }}%</span>
                                </dt>
                                <dd class="tabular font-extrabold">{{ $factor['score'] }}</dd>
                            </div>
                            <div class="mt-1 h-1.5 rounded-full bg-aj-bg">
                                <div class="h-full rounded-full bg-aj-success" style="width:{{ $factor['score'] }}%"></div>
                            </div>
                            <p class="mt-0.5 text-xs text-aj-muted">{{ $factor['measures'] }}</p>
                        </div>
                    @endforeach
                </dl>
                <p class="mt-4 text-xs text-aj-muted">
                    Reactietijd op vragen meten we nog niet; die factor telt bewust niet mee.
                </p>
            </section>
        @endif

        {{-- De verdeling over álle sterren - ook waar nul staat (13.6). --}}
        <section class="mt-8" aria-label="Beoordelingen">
            <h2 class="text-lg font-extrabold">Beoordelingen - alle sterren</h2>

            @if ($reviews->isEmpty())
                <p class="mt-2 text-sm text-aj-muted">Nog geen beoordelingen.</p>
            @else
                @php $peak = max(1, $distribution->max()); @endphp
                <dl class="mt-3 max-w-sm space-y-1">
                    @foreach ($distribution as $stars => $count)
                        <div class="flex items-center gap-2 text-sm">
                            <dt class="tabular w-7">{{ $stars }}★</dt>
                            <div class="h-3 flex-1 rounded-aj-sm bg-aj-bg">
                                <div class="h-full rounded-aj-sm bg-aj-blue"
                                     style="width:{{ intdiv($count * 100, $peak) }}%"></div>
                            </div>
                            <dd class="tabular w-8 text-right text-aj-muted">{{ $count }}</dd>
                        </div>
                    @endforeach
                </dl>

                <ul class="mt-5 space-y-3">
                    @foreach ($reviews as $review)
                        <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                            <p class="text-sm">
                                <span class="tabular font-extrabold">{{ $review->rating }}★</span>
                                <span class="text-aj-muted">· {{ $review->reviewer->publicName() }}
                                    · {{ $review->published_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM YYYY') }}</span>
                            </p>
                            @if ($review->body)
                                <p class="mt-1 text-sm">"{{ $review->body }}"</p>
                            @endif
                            @if ($review->seller_reply)
                                <p class="mt-2 border-l-2 border-aj-border-strong pl-3 text-sm text-aj-muted">
                                    Reactie verkoper: "{{ $review->seller_reply }}"
                                </p>
                            @elseif (auth()->id() === $seller->id)
                                <form method="POST" action="{{ route('reviews.reply', $review) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input type="text" name="reply" required maxlength="2000"
                                           placeholder="Reageer op deze beoordeling"
                                           aria-label="Reactie op review"
                                           class="w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm"
                                           style="min-height:40px">
                                    <button type="submit" class="btn btn-ghost" style="height:40px;font-size:13px;flex:none">
                                        Reageren
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-3 text-xs text-aj-muted">
                Alleen kopers die de kavel afnamen kunnen beoordelen. Reviews worden niet verwijderd op verzoek.
            </p>
        </section>

        @if ($liveLots->isNotEmpty())
            <section class="mt-8" aria-label="Lopende kavels">
                <h2 class="text-lg font-extrabold">Nu bij deze verkoper</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($liveLots as $lot)
                        <x-lot.card :lot="$lot" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.public>
