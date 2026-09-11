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

{{-- De standaard backoffice-lijst: zoeken, filters, bulk, paginering. --}}
<div class="mt-6" @if ($bulkAction) x-data="{ selected: [] }" @endif>

    @if ($searchable || isset($filters))
        {{-- Een filterkeuze verstuurt meteen; het zoekveld pas op de knop. --}}
        <form method="GET" x-data
              @change="if ($event.target.tagName === 'SELECT') $el.requestSubmit()"
              class="mb-3 flex flex-wrap items-center gap-2">
            @if ($searchable)
                <label class="w-full max-w-60">
                    <span class="sr-only">Zoeken</span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}"
                           class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                           style="min-height:44px">
                </label>
            @endif

            {{ $filters ?? '' }}

            @if ($searchable)
                <button type="submit" class="btn btn-ghost" style="height:44px;font-size:13px">Zoeken</button>
            @endif
            @if (request()->query())
                <a href="{{ url()->current() }}" class="btn btn-ghost" style="height:44px;font-size:13px">Wissen</a>
            @endif
        </form>
    @endif

    @if ($bulkAction)
        <form method="POST" action="{{ $bulkAction }}"
              onsubmit="return confirm(@js($bulkConfirm))">
            @csrf
    @endif

    <div class="overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface">
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

    @if ($bulkAction)
            <div class="mt-3 flex items-center gap-3" x-show="selected.length" x-cloak>
                <p class="text-sm font-semibold"><span x-text="selected.length"></span> geselecteerd</p>
                <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">{{ $bulkLabel }}</button>
            </div>
        </form>
    @endif

    @if ($items !== null && $items->hasPages())
        <div class="mt-4">{{ $items->links() }}</div>
    @endif

    @if ($items !== null && $items->isEmpty())
        <p class="mt-3 text-sm text-aj-muted">Niets gevonden.</p>
    @endif
</div>
