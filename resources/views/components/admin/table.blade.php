@props(['items' => null, 'searchable' => false, 'placeholder' => 'Zoeken'])

{{-- De standaard backoffice-lijst: zoekveld, tabelkaart, paginering. --}}
<div class="mt-6">
    @if ($searchable)
        <form method="GET" class="mb-3 flex gap-2">
            <label class="w-full max-w-xs">
                <span class="sr-only">Zoeken</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}"
                       class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                       style="min-height:44px">
            </label>
            <button type="submit" class="btn btn-ghost" style="height:44px;font-size:13px">Zoeken</button>
            @if (request('q'))
                <a href="{{ url()->current() }}" class="btn btn-ghost" style="height:44px;font-size:13px">Wissen</a>
            @endif
        </form>
    @endif

    <div class="overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface">
        <table class="admin-table">
            <thead><tr>{{ $head }}</tr></thead>
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>

    @if ($items !== null && $items->hasPages())
        <div class="mt-4">{{ $items->links() }}</div>
    @endif

    @if ($items !== null && $items->isEmpty())
        <p class="mt-3 text-sm text-aj-muted">Niets gevonden.</p>
    @endif
</div>
