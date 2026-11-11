@use('App\Enums\BidStatus')
@use('App\Support\Money\Money')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $open = $bids->filter(fn ($bid): bool => ! $bid->auction->isClosed());
    $closed = $bids->filter(fn ($bid): bool => $bid->auction->isClosed());
    $winning = $open->where('status', BidStatus::Winning)->count();
    $outbidBids = $open->where('status', BidStatus::Outbid);
    $won = $closed->where('status', BidStatus::Winning);
    $lost = $closed->where('status', '!=', BidStatus::Winning);
    $guards = app(\App\Auctions\Support\BidGuards::class);

    // De tegels zijn het filter (?toon=): één lijst tegelijk. Overboden staat bovenaan, dan wat het eerst sluit.
    $byUrgency = fn ($group) => $group->sortBy([
        fn ($a, $b): int => ($a->status === BidStatus::Winning) <=> ($b->status === BidStatus::Winning),
        fn ($a, $b): int => $a->auction->ends_at->getTimestamp() <=> $b->auction->ends_at->getTimestamp(),
    ])->values();
    $views = [
        'lopend' => ['Lopend', $byUrgency($open)],
        'bovenaan' => ['Hoogste bieder', $byUrgency($open->where('status', BidStatus::Winning))],
        'overboden' => ['Overboden', $byUrgency($outbidBids)],
        'gewonnen' => ['Gewonnen', $won->values()],
        'verloren' => ['Niet gewonnen', $lost->values()],
    ];
    $view = array_key_exists((string) request('toon'), $views) ? (string) request('toon') : 'lopend';
    [$heading, $group] = $views[$view];
    $viewUrl = fn (string $key): string => route('dashboard.bids', $key === 'lopend' ? [] : ['toon' => $key]);
    $listLimit = 10;
@endphp

<x-layouts.dashboard title="Mijn biedingen" eyebrow="Dashboard" intro="Per veiling hoe je ervoor staat: hoogste bieder, overboden of gewonnen.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Naar de veilingen</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" label="Lopend" :value="$open->count()" sub="veilingen waarin je meedoet" :href="$viewUrl('lopend')" :active="$view === 'lopend'" />
        <x-dashboard.tile tone="orange" label="Overboden" :value="$outbidBids->count()" sub="nog te verhogen" :href="$viewUrl('overboden')" :active="$view === 'overboden'" />
        <x-dashboard.tile tone="green" label="Hoogste bieder" :value="$winning" sub="in lopende veilingen" :href="$viewUrl('bovenaan')" :active="$view === 'bovenaan'" />
        <x-dashboard.tile tone="purple" label="Gewonnen" :value="$won->count()" sub="afgelopen veilingen" :href="$viewUrl('gewonnen')" :active="$view === 'gewonnen'" />
    </x-slot:tiles>

    @if ($bids->isEmpty())
        <x-ui.empty-state
            title="Nog geen biedingen"
            body="Zodra je ergens op biedt, volg je hier per veiling hoe je ervoor staat.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="d-stack" style="gap:18px">
                    {{-- De eerste tien staan open, de rest achter "Meer tonen". --}}
                    <section aria-label="{{ $heading }}" x-data="{ all: false }">
                        <div class="d-listbar">
                            <h2 class="d-section-title" style="margin:0;flex:1 1 auto">{{ $heading }}</h2>
                            @if ($view === 'verloren')
                                <a class="d-link" href="{{ $viewUrl('lopend') }}">Terug naar lopend</a>
                            @elseif ($lost->isNotEmpty())
                                <a class="d-link" href="{{ $viewUrl('verloren') }}">Niet gewonnen ({{ $lost->count() }})</a>
                            @endif
                        </div>
                        @if ($group->isEmpty())
                            <p class="feed-empty" style="margin:0">{{ match ($view) {
                                'overboden' => 'Je bent nergens overboden.',
                                'bovenaan' => 'Je staat nu nergens bovenaan.',
                                'gewonnen' => 'Nog niets gewonnen. Wat je wint, staat hier met de bestelling.',
                                'verloren' => 'Geen veilingen die je niet won.',
                                default => 'Je doet nu nergens aan mee.',
                            } }}</p>
                            <div style="margin-top:12px"><a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('lots.index') }}">Naar de veilingen</a></div>
                        @endif
                        <div class="d-stack-tight">
                            @foreach ($group as $bid)
                                @php
                                    $auction = $bid->auction;
                                    $lot = $auction->lot;
                                    $isOpen = ! $auction->isClosed();
                                    $interest = \App\Lots\Interest\Interest::forBuyer($lot);
                                    [$tagTone, $tagLabel] = match (true) {
                                        ! $isOpen && $bid->status === BidStatus::Winning => ['tone-atlas', $lot->order?->status === \App\Enums\OrderStatus::AwaitingPayment ? 'Wacht op betaling' : 'Gewonnen'],
                                        ! $isOpen => ['tone-grey', 'Niet gewonnen'],
                                        $bid->status === BidStatus::Winning => ['tone-ok', 'Hoogste bieder'],
                                        default => ['tone-warn', 'Overboden'],
                                    };
                                    $meta = 'Kavel #'.$lot->public_id.' · '.($isOpen
                                        ? ($bid->status === BidStatus::Winning ? 'sluit '.$auction->ends_at->timezone($displayTimezone)->diffForHumans() : 'jouw bod '.Money::format($bid->amount_cents))
                                        : 'gesloten op '.$auction->ends_at->timezone($displayTimezone)->isoFormat('D MMM'));
                                @endphp
                                <article class="d-listrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                    @if ($lot->media->first())
                                        <img class="d-listrow-thumb" src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                                    @else
                                        <span class="d-listrow-thumb" aria-hidden="true"></span>
                                    @endif
                                    <div class="d-listrow-body">
                                        <strong><a href="{{ route('lots.show', $lot) }}">{{ $lot->title }}</a></strong>
                                        <span class="d-listrow-meta">{{ $meta }}</span>
                                        @if ($isOpen)
                                            <div class="d-counts">
                                                <span title="kijkers"><x-v4.icon name="eye" :size="14" stroke="2" />{{ number_format($interest['viewers'], 0, ',', '.') }}</span>
                                                <span title="keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" />{{ $interest['saved'] }}</span>
                                                <span title="keer gedeeld"><x-v4.icon name="share" :size="14" stroke="2" />{{ $interest['shared'] }}</span>
                                            </div>
                                        @endif
                                        <div class="d-listrow-tags">
                                            <span class="d-tag {{ $tagTone }}">{{ $tagLabel }}</span>
                                            @if ($lot->is_hot && $isOpen)
                                                <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="d-listrow-right">
                                        <b>{{ Money::format($isOpen && $bid->status === BidStatus::Outbid ? $lot->currentPriceCents() : $bid->amount_cents) }}</b>
                                        <span>{{ $isOpen ? ($bid->status === BidStatus::Outbid ? 'Huidig bod' : 'Jouw bod') : ($bid->status === BidStatus::Winning ? 'Winnend bod' : 'Jouw bod') }}</span>
                                        @if ($isOpen && $bid->status === BidStatus::Outbid)
                                            <a href="{{ route('lots.show', $lot) }}" class="d-btn d-btn-dark d-btn-sm d-btn-sq">Bied opnieuw</a>
                                        @elseif (! $isOpen && $bid->status === BidStatus::Winning && $lot->order !== null)
                                            <a href="{{ route('orders.show', $lot->order) }}" class="d-btn d-btn-secondary d-btn-sm d-btn-sq">{{ $lot->order->status === \App\Enums\OrderStatus::AwaitingPayment ? 'Betalen' : 'Bestelling' }}</a>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <x-dashboard.more :total="$group->count()" :limit="$listLimit" />
                    </section>

            @if ($view === 'gewonnen' && $won->isNotEmpty())
                <div class="d-callout-info">
                    <span class="mark" aria-hidden="true">i</span>
                    <div>
                        <strong>Wat is de volgende stap?</strong>
                        Elke gewonnen kavel heeft een eigen betaalverzoek bij je bestellingen. Na betaling plan je het ophalen of de levering.
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-layouts.dashboard>
