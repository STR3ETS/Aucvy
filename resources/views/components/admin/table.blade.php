@props([
    'items' => null,
    'searchable' => false,
    'placeholder' => 'Zoeken',
    // Bulkacties: geef een POST-route plus de ids op deze pagina mee; de
    // rijen leveren zelf hun selectievakje (name="ids[]", x-model="selected").
    'bulkAction' => null,
    'bulkLabel' => 'Toepassen',
    'bulkConfirm' => 'Weet je het zeker?',
    'bulkIds' => [],
])

{{--
    De standaard backoffice-lijst in de stijl van het dashboard: één kaart
    met bovenin zoeken en filters, daaronder de tabel, onderaan de paginering.
--}}
<div class="d-admintable" @if ($bulkAction) x-data="{ selected: [] }" @endif>

    @if ($searchable || isset($filters))
        {{-- Een filterkeuze verstuurt meteen; het zoekveld pas op de knop. --}}
        <form method="GET" x-data
              @change="if ($event.target.tagName === 'SELECT') $el.requestSubmit()"
              class="d-admintable-bar">
            @if ($searchable)
                <label class="d-admintable-search">
                    <span class="sr-only">Zoeken</span>
                    <x-v4.icon name="search" :size="16" stroke="2.2" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="d-input">
                </label>
            @endif

            {{ $filters ?? '' }}

            @if ($searchable)
                <button type="submit" class="d-btn d-btn-secondary d-btn-sq">Zoeken</button>
            @endif
            @if (request()->query())
                <a href="{{ url()->current() }}" class="d-link">Wissen</a>
            @endif
        </form>
    @endif

    @if ($bulkAction)
        <form method="POST" action="{{ $bulkAction }}"
              onsubmit="return confirm(@js($bulkConfirm))">
            @csrf
    @endif

    <div class="d-admintable-scroll">
        <table class="admin-table">
            <thead>
                <tr>
                    @if ($bulkAction)
                        <th class="w-10">
                            <input type="checkbox" class="rounded" aria-label="Alles op deze pagina selecteren"
                                   @change="selected = $event.target.checked ? {{ Js::from(collect($bulkIds)->map(fn ($id) => (string) $id)->values()) }} : []">
                        </th>
                    @endif
                    {{ $head }}
                </tr>
            </thead>
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>

    @if ($items !== null && $items->isEmpty())
        <p class="d-admintable-empty">Niets gevonden.</p>
    @endif

    @if ($bulkAction)
            <div class="d-admintable-bulk" x-show="selected.length" x-cloak>
                <p><span x-text="selected.length"></span> geselecteerd</p>
                <button type="submit" class="d-btn d-btn-sq">{{ $bulkLabel }}</button>
            </div>
        </form>
    @endif

    @if ($items !== null && $items->hasPages())
        <div class="d-admintable-pages">{{ $items->links() }}</div>
    @endif
</div>
