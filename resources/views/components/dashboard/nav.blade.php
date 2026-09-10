{{-- De vaste navigatie onder het dashboard (hfst. 7). --}}
<nav class="chip-row -mx-4 mt-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Dashboardonderdelen">
    @foreach ([
        ['dashboard', 'Dashboard'],
        ['dashboard.lots', 'Kavels'],
        ['dashboard.bids', 'Biedingen'],
        ['orders.index', 'Bestellingen'],
        ['dashboard.payouts', 'Uitbetalingen'],
        ['dashboard.disputes', 'Geschillen'],
    ] as [$route, $label])
        <a href="{{ route($route) }}"
           class="btn {{ request()->routeIs($route) ? 'btn-primary' : 'btn-ghost' }}"
           style="height:40px;font-size:13px;flex:none"
           @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
