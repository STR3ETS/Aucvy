@use('App\Enums\LotStatus')
@use('App\Support\Money\Money')

@php
    // Vijf groepen voor de filterchips; de status zelf blijft op de kaart staan.
    $groupOf = fn (LotStatus $status): string => match (true) {
        in_array($status, [LotStatus::Live, LotStatus::Closing], true) => 'live',
        $status === LotStatus::Draft => 'concept',
        in_array($status, [LotStatus::PendingReview, LotStatus::Scheduled], true) => 'gepland',
        $status === LotStatus::Paused => 'gepauzeerd',
        $status === LotStatus::Sold => 'verkocht',
        default => 'overig',
    };
    $groups = ['live' => 'Live', 'concept' => 'Concept', 'gepland' => 'Gepland', 'gepauzeerd' => 'Gepauzeerd', 'verkocht' => 'Verkocht', 'overig' => 'Overig'];
    $counts = $lots->countBy(fn ($lot): string => $groupOf($lot->status));
    $bidders = (int) $lots
        ->filter(fn ($lot): bool => $groupOf($lot->status) === 'live')
        ->sum(fn ($lot): int => (int) ($lot->auction?->bidder_count ?? 0));
@endphp

<x-layouts.dashboard title="Mijn kavels" eyebrow="Dashboard" intro="Alles wat je hebt geplaatst, met de stand van elke veiling en wat er nog in de la ligt.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('sell') }}">Kavel plaatsen</a>
        <a class="btn btn-outline-white" href="{{ route('dashboard.import') }}">Importeren</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" icon="gavel" label="Live" :value="$counts->get('live', 0)" sub="nu te zien voor kopers" />
        <x-dashboard.tile tone="green" icon="users" label="Bieders" :value="$bidders" sub="op je lopende veilingen" />
        <x-dashboard.tile tone="orange" icon="clock" label="Concepten" :value="$counts->get('concept', 0)" sub="nog niet gepubliceerd" :href="$counts->get('concept', 0) > 0 ? route('sell') : null" />
        <x-dashboard.tile tone="purple" icon="badge-check" label="Verkocht" :value="$counts->get('verkocht', 0)" sub="met een koper" :href="route('orders.index')" />
    </x-slot:tiles>

    @if ($lots->isEmpty())
        <x-ui.empty-state
            title="Nog geen kavels"
            body="Plaats je eerste kavel; Ivy helpt met herkennen, schrijven en waarderen.">
            <x-slot:action>
                <x-ui.button :href="route('sell')">Kavel plaatsen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        @if (session('status'))
            <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
        @endif

        {{-- Filteren en zoeken gebeurt in de browser: de lijst is al geladen. Bulk gaat via één formulier om de grid heen. --}}
        <form method="POST" action="{{ route('dashboard.lots.bulk') }}" x-data="{ filter: 'alle', q: '', selected: [] }">
            @csrf
            <div class="list-tools">
                <div class="chip-row" role="group" aria-label="Filter op status">
                    <button type="button" class="filter-chip" :class="filter === 'alle' && 'is-on'" @click="filter = 'alle'">Alle <span>{{ $lots->count() }}</span></button>
                    @foreach ($groups as $key => $label)
                        @if ($counts->get($key, 0) > 0)
                            <button type="button" class="filter-chip" :class="filter === '{{ $key }}' && 'is-on'" @click="filter = '{{ $key }}'">{{ $label }} <span>{{ $counts->get($key) }}</span></button>
                        @endif
                    @endforeach
                </div>
                <label class="list-search">
                    <x-v4.icon name="search" :size="15" stroke="2.2" />
                    <span class="sr-only">Zoek in je kavels</span>
                    <input type="search" x-model="q" placeholder="Zoek in je kavels" autocomplete="off">
                </label>
            </div>

            <div class="bulk-bar" x-show="selected.length" x-cloak>
                <span><b class="tabular" x-text="selected.length"></b> geselecteerd</span>
                <button type="submit" name="action" value="duplicate" class="btn btn-ghost" style="height:38px;font-size:13px">Dupliceren</button>
                <button type="submit" name="action" value="delete_drafts" class="btn btn-ghost" style="height:38px;font-size:13px;color:var(--color-aj-error)"
                        @click="if (! confirm('Alleen concepten worden verwijderd. Doorgaan?')) $event.preventDefault()">Concepten verwijderen</button>
                <button type="button" class="link" @click="selected = []">Niets selecteren</button>
            </div>

            <ul class="lot-grid">
                @foreach ($lots as $lot)
                    @php
                        $group = $groupOf($lot->status);
                        $url = match (true) {
                            $lot->status === LotStatus::Draft => route('sell', ['concept' => $lot->id]),
                            $lot->status->isPublic() => route('lots.show', $lot),
                            default => null,
                        };
                    @endphp
                    <li class="lot-tile" data-group="{{ $group }}" data-title="{{ Str::lower($lot->title) }}"
                        x-show="(filter === 'alle' || filter === $el.dataset.group) && (q === '' || $el.dataset.title.includes(q.toLowerCase()))">
                        <label class="pick">
                            <input type="checkbox" name="lots[]" value="{{ $lot->id }}" x-model="selected" aria-label="Selecteer {{ $lot->title }}">
                        </label>
                        <a class="lot-tile-media" href="{{ $url ?? route('dashboard.lots') }}" aria-label="{{ $lot->title }}">
                            @if ($lot->cover_path)
                                <img src="{{ $lot->cover_path }}" alt="" loading="lazy">
                            @else
                                <span class="empty" aria-hidden="true"><x-v4.icon name="image" :size="28" stroke="1.6" /></span>
                            @endif
                            <span class="status {{ $group }}">{{ $lot->status->label() }}</span>
                        </a>
                        <div class="lot-tile-body">
                            <span class="cat">{{ $lot->category?->name ?? 'Zonder categorie' }}</span>
                            <h3>@if ($url)<a href="{{ $url }}">{{ $lot->title }}</a>@else{{ $lot->title }}@endif</h3>
                            <div class="lot-tile-meta">
                                @if ($lot->auction)
                                    <span><b class="tabular">{{ Money::format($lot->currentPriceCents()) }}</b></span>
                                    <span>{{ $lot->auction->bidder_count }} {{ $lot->auction->bidder_count === 1 ? 'bieder' : 'bieders' }}</span>
                                    @unless ($lot->auction->isClosed())
                                        <span>sluit {{ $lot->auction->ends_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</span>
                                    @endunless
                                @elseif ($lot->status === LotStatus::Draft)
                                    <span><b class="tabular">{{ $lot->completeness_score }}%</b> compleet</span>
                                    <span>bewerkt {{ $lot->updated_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</span>
                                @else
                                    <span>Nog geen veiling gepland</span>
                                @endif
                            </div>
                            <div class="lot-tile-actions">
                                @if ($lot->status === LotStatus::Draft)
                                    <a href="{{ $url }}" class="btn btn-primary">{{ __('dashboard.actions.continue_draft') }}</a>
                                @elseif (in_array($lot->status, [LotStatus::Unsold, LotStatus::Cancelled], true))
                                    <button type="submit" class="btn btn-primary" formaction="{{ route('dashboard.lots.duplicate', ['lot' => $lot->id, 'relist' => 1]) }}" formmethod="POST">Opnieuw plaatsen</button>
                                    @if ($url)<a href="{{ $url }}" class="btn btn-ghost">Bekijk</a>@endif
                                @elseif (in_array($lot->status, [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing, LotStatus::Paused], true))
                                    <a href="{{ route('dashboard.lots.edit', $lot) }}" class="btn btn-primary">{{ $lot->status === LotStatus::Paused ? 'Hervatten' : 'Bewerken' }}</a>
                                    @if ($url)<a href="{{ $url }}" class="btn btn-ghost">Bekijk</a>@endif
                                @elseif ($url)
                                    <a href="{{ $url }}" class="btn btn-ghost">Bekijk</a>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </form>
    @endif
</x-layouts.dashboard>
