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
@endphp

<x-layouts.dashboard title="Mijn biedingen" eyebrow="Dashboard" intro="Per veiling hoe je ervoor staat: hoogste bieder, overboden of gewonnen.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Naar de veilingen</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="green" label="Hoogste bieder" :value="$winning" sub="in lopende veilingen" />
        <x-dashboard.tile tone="orange" label="Overboden" :value="$outbidBids->count()" sub="nog te verhogen" />
        <x-dashboard.tile tone="blue" label="Lopend" :value="$open->count()" sub="veilingen waarin je meedoet" />
        <x-dashboard.tile tone="purple" label="Gewonnen" :value="$won->count()" sub="afgelopen veilingen" :href="route('orders.index')" />
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
            @foreach ($outbidBids->sortBy(fn ($bid) => $bid->auction->ends_at->getTimestamp()) as $bid)
                {{-- Overboden is hét signaal dat aandacht vraagt: bovenaan, met het bedrag dat je weer op kop brengt. --}}
                <div class="d-callout-warn">
                    <span class="mark" aria-hidden="true">!</span>
                    <div class="text">
                        <strong>Je bent overboden op {{ $bid->auction->lot->title }}</strong>
                        <span>Een bod van {{ Money::format($guards->minimumBid($bid->auction)) }} of meer brengt je weer op kop.</span>
                    </div>
                    <span class="d-muted">Sluit {{ $bid->auction->ends_at->timezone($displayTimezone)->diffForHumans() }}</span>
                </div>
            @endforeach

            @foreach ([['Lopend', $open->sortBy(fn ($bid) => $bid->auction->ends_at->getTimestamp())], ['Gewonnen', $won], ['Niet gewonnen', $lost]] as [$heading, $group])
                @if ($group->isNotEmpty())
                    <section aria-label="{{ $heading }}">
                        <h2 class="d-section-title">{{ $heading }}</h2>
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
                                <article class="d-listrow">
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
                                            <a href="{{ route('lots.show', $lot) }}" class="d-btn d-btn-sm d-btn-sq">Bied opnieuw</a>
                                        @elseif (! $isOpen && $bid->status === BidStatus::Winning && $lot->order !== null)
                                            <a href="{{ route('orders.show', $lot->order) }}" class="d-btn d-btn-secondary d-btn-sm d-btn-sq">{{ $lot->order->status === \App\Enums\OrderStatus::AwaitingPayment ? 'Betalen' : 'Bestelling' }}</a>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach

            @if ($won->isNotEmpty())
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
