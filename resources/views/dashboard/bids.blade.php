@use('App\Enums\BidStatus')
@use('App\Support\Money\Money')

@php
    $open = $bids->filter(fn ($bid): bool => ! $bid->auction->isClosed());
    $winning = $open->where('status', BidStatus::Winning)->count();
    $outbid = $open->where('status', BidStatus::Outbid)->count();
    $won = $bids->filter(fn ($bid): bool => $bid->auction->isClosed() && $bid->status === BidStatus::Winning)->count();
@endphp

<x-layouts.dashboard title="Mijn biedingen" eyebrow="Dashboard" intro="Per veiling hoe je ervoor staat: hoogste bieder, overboden of gewonnen.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Naar de veilingen</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="green" icon="trending-up" label="Hoogste bieder" :value="$winning" sub="in lopende veilingen" />
        <x-dashboard.tile tone="orange" icon="clock" label="Overboden" :value="$outbid" sub="nog te verhogen" />
        <x-dashboard.tile tone="blue" icon="gavel" label="Lopend" :value="$open->count()" sub="veilingen waarin je meedoet" />
        <x-dashboard.tile tone="purple" icon="badge-check" label="Gewonnen" :value="$won" sub="afgelopen veilingen" :href="route('orders.index')" />
    </x-slot:tiles>

    @if ($bids->isEmpty())
        <div>
            <x-ui.empty-state
                title="Nog geen biedingen"
                body="Zodra je ergens op biedt, volg je hier per veiling hoe je ervoor staat.">
                <x-slot:action>
                    <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </div>
    @else
        <ul class="space-y-2">
            @foreach ($bids as $bid)
                @php
                    $auction = $bid->auction;
                    [$stateClass, $stateLabel] = match (true) {
                        $auction->isClosed() && $bid->status === BidStatus::Winning => ['bg-aj-success/10 text-aj-success-text', 'Gewonnen'],
                        $auction->isClosed() => ['bg-aj-bg text-aj-muted', 'Niet gewonnen'],
                        $bid->status === BidStatus::Winning => ['bg-aj-success/10 text-aj-success-text', 'Hoogste bieder'],
                        default => ['bg-aj-warn/10 text-aj-warn-text', 'Overboden'],
                    };
                @endphp
                <li class="flex flex-wrap items-center gap-3 rounded-aj-s border border-aj-border bg-aj-surface p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold">{{ $auction->lot->title }}</span>
                        <span class="block text-xs text-aj-muted">
                            Jouw bod: <span class="tabular font-semibold">{{ Money::format($bid->amount_cents) }}</span>
                            · stand: <span class="tabular font-semibold">{{ Money::format($auction->current_price_cents) }}</span>
                            @unless ($auction->isClosed())
                                · sluit {{ $auction->ends_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}
                            @endunless
                        </span>
                    </span>

                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $stateClass }}">{{ $stateLabel }}</span>

                    <a href="{{ route('lots.show', $auction->lot) }}" class="btn btn-ghost" style="height:36px;font-size:13px">
                        {{ $auction->isClosed() ? 'Bekijk' : 'Naar de veiling' }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.dashboard>
