<x-layouts.public :title="$seller->publicName()">
    <div class="mx-auto max-w-4xl">

        <header>
            <h1 class="text-3xl font-extrabold tracking-[-0.03em]">{{ $seller->publicName() }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                @if ($company?->verified_at !== null || $seller->kyc_status->allowsBidding())
                    <span class="rounded-aj-sm bg-aj-success/10 px-2 py-0.5 font-bold text-aj-success-text">Geverifieerd</span>
                @endif
                @if ($averageRating !== null)
                    <span class="tabular font-bold">{{ number_format($averageRating, 1, ',', '') }}★</span>
                    <span class="text-aj-muted">· {{ $completedSales }} {{ $completedSales === 1 ? 'verkoop' : 'verkopen' }}</span>
                @endif
                <span class="text-aj-muted">· lid sinds {{ $seller->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('MMMM YYYY') }}</span>
                <span class="text-aj-muted">· {{ $disputeCount }} {{ $disputeCount === 1 ? 'geschil' : 'geschillen' }}</span>
            </div>
        </header>

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
