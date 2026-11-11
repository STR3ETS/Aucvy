@props([
    // Hoeveel meldingen nog niet gelezen zijn; bepaalt of "Alles gelezen" er staat.
    'unread' => 0,
])

{{--
    De meldingen als paneel dat rechts uitschuift vanaf de bel in de
    bovenbalk. De lijst komt als fragment van de server
    (dashboard/partials/notifications-panel). Een eigen pagina Meldingen is
    er niet meer: oudere meldingen laden in de lade zelf, en een oude link
    naar die pagina opent de lade. Het gedrag staat in
    resources/js/dash-overlays.js.
--}}
<div x-data="notifDrawer({{ Js::from(['url' => route('notifications.panel'), 'page' => route('notifications.index', [], false)]) }})"
     @notifs-open.window="show()" @keydown.escape.window="if (open) close()">
    <div class="d-drawer-scrim" x-show="open" x-cloak x-transition.opacity.duration.200ms @click="close()"></div>

    <aside class="d-drawer" x-show="open" x-cloak x-trap.noscroll="open" role="dialog" aria-modal="true" aria-labelledby="drawer-title"
           x-transition:enter="is-entering" x-transition:enter-start="is-from" x-transition:enter-end="is-to"
           x-transition:leave="is-leaving" x-transition:leave-start="is-to" x-transition:leave-end="is-from">
        <header class="d-drawer-head">
            <h2 id="drawer-title">Meldingen</h2>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="d-link">Alles gelezen</button>
                </form>
            @endif
            <button type="button" class="d-drawer-close" aria-label="Meldingen sluiten" @click="close()">
                <x-v4.icon name="x" :size="18" stroke="2.2" />
            </button>
        </header>

        <div class="d-drawer-body">
            {{-- Alleen de lijst laadt; kop en voet staan er meteen. --}}
            <div class="d-drawer-skeleton" x-show="loading" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <p class="d-drawer-empty" x-show="failed" x-cloak>De meldingen laden nu niet. Sluit het paneel en probeer het opnieuw.</p>
            <div x-show="! loading && ! failed" x-html="html"></div>
        </div>

        <footer class="d-drawer-foot" x-show="more" x-cloak>
            <button type="button" class="d-btn d-btn-secondary d-btn-wide" :disabled="loadingMore" @click="showAll()">Oudere meldingen tonen</button>
        </footer>
    </aside>
</div>
