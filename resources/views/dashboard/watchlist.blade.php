@use('App\Support\Money\Money')

<x-layouts.dashboard title="Volglijst" eyebrow="Dashboard" intro="De kavels die je volgt, met de stand en wanneer ze sluiten. Je hoort het als een gevolgde veiling bijna sluit.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Naar de veilingen</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" icon="heart" label="Gevolgd" :value="$items->count()" sub="kavels op je lijst" />
        <x-dashboard.tile tone="orange" icon="clock" label="Sluit binnen een dag" :value="$endingSoon" sub="nu beslissen" />
        <x-dashboard.tile tone="purple" icon="gavel" label="Gesloten" :value="$closed" sub="veilingen voorbij" />
    </x-slot:tiles>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">{{ session('status') }}</p>
    @endif

    @if ($items->isEmpty())
        <x-ui.empty-state title="Je volgt nog geen kavels"
            body="Op elke kavelpagina staat een knop Volgen. Je krijgt dan een seintje als de veiling bijna sluit.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <ul class="space-y-2">
            @foreach ($items as $item)
                @php $lot = $item->lot; $auction = $lot->auction; @endphp
                <li class="flex flex-wrap items-center gap-3 rounded-aj-s border border-aj-border bg-aj-surface p-3 pr-4">
                    <a href="{{ route('lots.show', $lot) }}" class="block h-16 w-24 flex-none overflow-hidden rounded-aj-sm bg-aj-navy" aria-hidden="true" tabindex="-1">
                        @if ($lot->media->isNotEmpty())
                            <img src="{{ $lot->media->first()->path }}" alt="" class="h-full w-full object-cover" loading="lazy">
                        @endif
                    </a>
                    <span class="min-w-0 flex-1">
                        <span class="block text-xs font-bold uppercase tracking-wide text-aj-muted">{{ $lot->category?->name ?? 'Kavel' }}</span>
                        <a href="{{ route('lots.show', $lot) }}" class="block truncate text-sm font-bold hover:underline">{{ $lot->title }}</a>
                        <span class="block text-xs text-aj-muted">
                            @if ($auction)
                                Stand <span class="tabular font-semibold">{{ Money::format($lot->currentPriceCents()) }}</span>
                                · {{ $auction->bidder_count }} {{ $auction->bidder_count === 1 ? 'bieder' : 'bieders' }}
                                @if ($auction->isClosed())
                                    · gesloten
                                @elseif ($auction->isPaused())
                                    · gepauzeerd
                                @else
                                    · sluit {{ $auction->ends_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}
                                @endif
                            @else
                                Nog geen veiling gepland
                            @endif
                        </span>
                    </span>

                    @if ($auction && ! $auction->isClosed() && ! $auction->isPaused())
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $auction->ends_at->lessThanOrEqualTo(now()->addDay()) ? 'bg-aj-warn/10 text-aj-warn-text' : 'bg-aj-success/10 text-aj-success-text' }}">
                            {{ $auction->ends_at->lessThanOrEqualTo(now()->addDay()) ? 'Sluit bijna' : 'Loopt' }}
                        </span>
                    @endif

                    <a href="{{ route('lots.show', $lot) }}" class="btn btn-ghost" style="height:36px;font-size:13px">Naar de kavel</a>
                    <form method="POST" action="{{ route('dashboard.watchlist.remove', $lot) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px" aria-label="Stop met volgen van {{ $lot->title }}">Ontvolgen</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.dashboard>
