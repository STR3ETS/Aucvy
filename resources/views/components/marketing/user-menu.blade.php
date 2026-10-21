{{--
    De profielknop in de header van thema C: initialen, voornaam en een
    dropdown met de snelle acties. Vervangt de losse links Dashboard,
    Bestellingen en Uitloggen. Alleen voor ingelogde gebruikers.
--}}
@php
    $user = auth()->user();
    $initials = collect(explode(' ', trim((string) $user->name)))
        ->filter()
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');
    $firstName = Str::of($user->name)->trim()->before(' ')->toString();
    $unreadCount = $user->unreadNotifications()->count();
@endphp

<div class="user-menu" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button" :aria-expanded="open" aria-haspopup="true" aria-controls="user-panel" @click="open = ! open">
        <span class="ini" aria-hidden="true">{{ $initials !== '' ? $initials : 'A' }}</span>
        <span class="name">{{ $firstName !== '' ? $firstName : 'Account' }}</span>
        <x-v4.icon name="chevron-down" :size="14" stroke="2.2" class="chev" />
    </button>

    <div class="user-panel" id="user-panel" x-show="open" x-cloak>
        <div class="who">
            <b>{{ $user->name }}</b>
            <span>{{ $user->email }}</span>
        </div>

        @if ($user->is_staff)
            <a href="{{ url('/admin') }}"><x-v4.icon name="building" :size="16" stroke="2" /> Backoffice</a>
        @endif
        <a href="{{ route('dashboard') }}"><x-v4.icon name="target" :size="16" stroke="2" /> Dashboard</a>
        <a href="{{ route('dashboard.lots') }}"><x-v4.icon name="gavel" :size="16" stroke="2" /> Mijn kavels</a>
        <a href="{{ route('dashboard.bids') }}"><x-v4.icon name="trending-up" :size="16" stroke="2" /> Mijn biedingen</a>
        <a href="{{ route('orders.index') }}"><x-v4.icon name="shopping-bag" :size="16" stroke="2" /> Bestellingen</a>
        <a href="{{ route('dashboard.payouts') }}"><x-v4.icon name="banknote" :size="16" stroke="2" /> Uitbetalingen</a>

        <div class="sep" role="separator"></div>
        <a href="{{ route('notifications.index') }}"><x-v4.icon name="bell" :size="16" stroke="2" /> Meldingen
            @if ($unreadCount > 0)<span class="notif-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
        </a>
        <a href="{{ route('dashboard.watchlist') }}"><x-v4.icon name="heart" :size="16" stroke="2" /> Volglijst</a>
        <a href="{{ route('dashboard.alerts') }}"><x-v4.icon name="search" :size="16" stroke="2" /> Alerts</a>
        <a href="{{ route('account.settings') }}"><x-v4.icon name="settings" :size="16" stroke="2" /> Instellingen</a>

        <div class="sep" role="separator"></div>
        <a href="{{ route('sell') }}"><x-v4.icon name="plus" :size="16" stroke="2" /> Kavel plaatsen</a>
        @unless ($user->kyc_status->allowsBidding())
            <a href="{{ route('verification.overview') }}"><x-v4.icon name="badge-check" :size="16" stroke="2" /> Verificatie afronden</a>
        @endunless

        <div class="sep" role="separator"></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="danger"><x-v4.icon name="x" :size="16" stroke="2" /> Uitloggen</button>
        </form>
    </div>
</div>
