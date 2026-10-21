@php
    $iconFor = fn (string $kind): string => match ($kind) {
        'outbid', 'auction_lost' => 'gavel',
        'order' => 'shopping-bag',
        'payout' => 'banknote',
        'dispute' => 'shield-check',
        'alert' => 'search',
        'watchlist' => 'heart',
        'viewing' => 'clock',
        'moderation', 'verification' => 'badge-check',
        default => 'bell',
    };
@endphp

<x-layouts.dashboard title="Meldingen" eyebrow="Dashboard" intro="Alles wat je per mail of push kreeg, hier terug te vinden.">
    <x-slot:actions>
        @if ($unread > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-white">Alles gelezen</button>
            </form>
        @endif
        <a class="btn btn-outline-white" href="{{ route('account.settings') }}#meldingen">Voorkeuren</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" icon="bell" label="Ongelezen" :value="$unread" sub="sinds je laatste bezoek" />
        <x-dashboard.tile tone="purple" icon="clock" label="Totaal" :value="$notifications->total()" sub="meldingen bewaard" />
    </x-slot:tiles>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">{{ session('status') }}</p>
    @endif

    @if ($notifications->isEmpty())
        <x-ui.empty-state title="Nog geen meldingen"
            body="Zodra je overboden wordt, een alert raak is of een bestelling verandert, staat het hier.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <ul class="notif-list">
            @foreach ($notifications as $notification)
                @php $data = $notification->data; @endphp
                <li class="notif-item{{ $notification->read_at ? '' : ' is-unread' }}">
                    <span class="notif-icon" aria-hidden="true"><x-v4.icon :name="$iconFor((string) ($data['kind'] ?? ''))" :size="18" stroke="2" /></span>
                    <a href="{{ route('notifications.open', $notification->id) }}" class="notif-body">
                        <b>{{ $data['title'] ?? 'Melding' }}</b>
                        @if (! empty($data['body']))
                            <span>{{ $data['body'] }}</span>
                        @endif
                    </a>
                    <time class="notif-time" datetime="{{ $notification->created_at->toIso8601String() }}">
                        {{ $notification->created_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}
                    </time>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
</x-layouts.dashboard>
