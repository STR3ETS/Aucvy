@use('App\Enums\LotStatus')
@use('App\Support\Money\Money')

<x-layouts.public title="Mijn kavels">
    <h1 class="text-3xl font-extrabold tracking-[-0.03em]">Mijn kavels</h1>
    <x-dashboard.nav />

    @if ($lots->isEmpty())
        <div class="mt-6">
            <x-ui.empty-state
                title="Nog geen kavels"
                body="Plaats je eerste kavel; Ivy helpt met herkennen, schrijven en waarderen.">
                <x-slot:action>
                    <x-ui.button :href="route('sell')">Kavel plaatsen</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </div>
    @else
        <ul class="mt-6 space-y-2">
            @foreach ($lots as $lot)
                <li class="flex flex-wrap items-center gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold">{{ $lot->title }}</span>
                        <span class="block text-xs text-aj-muted">
                            {{ $lot->category?->name }}
                            @if ($lot->auction)
                                · {{ $lot->auction->bidder_count }} {{ $lot->auction->bidder_count === 1 ? 'bieder' : 'bieders' }}
                                @unless ($lot->auction->isClosed())
                                    · sluit {{ $lot->auction->ends_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}
                                @endunless
                            @endif
                        </span>
                    </span>

                    @if ($lot->auction)
                        <span class="tabular text-sm font-extrabold">{{ Money::format($lot->currentPriceCents()) }}</span>
                    @endif

                    <span @class([
                        'rounded-full px-2.5 py-1 text-xs font-bold',
                        'bg-aj-success/10 text-aj-success-text' => in_array($lot->status, [LotStatus::Live, LotStatus::Closing], true),
                        'bg-aj-bg text-aj-muted' => ! in_array($lot->status, [LotStatus::Live, LotStatus::Closing], true),
                    ])>{{ $lot->status->label() }}</span>

                    @if ($lot->status->isPublic())
                        <a href="{{ route('lots.show', $lot) }}" class="btn btn-ghost" style="height:36px;font-size:13px">Bekijk</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.public>
