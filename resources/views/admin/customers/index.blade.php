<x-layouts.admin title="Klanten" :subtitle="$customers->total().' klanten met een bevestigd e-mailadres'">
    <div class="mt-6 flex flex-wrap items-center gap-2">
        <nav class="flex flex-wrap gap-1.5 text-sm" aria-label="Filter">
            @foreach (['' => 'Alle', 'zakelijk' => 'Zakelijk', 'particulier' => 'Particulier', 'nieuw' => 'Nieuw (30 dagen)', 'beheer' => 'Beheer'] as $key => $label)
                <a href="{{ route('admin.customers', array_filter(['soort' => $key, 'q' => request('q')])) }}" @class(['rounded-full px-3 py-1 font-bold', 'bg-aj-navy text-white' => $filter === $key, 'border border-aj-border bg-aj-surface' => $filter !== $key])>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" class="ml-auto flex gap-2">
            <input type="hidden" name="soort" value="{{ $filter }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek op naam, mail of bedrijf" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:40px">
            <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Zoeken</button>
        </form>
    </div>

    <div class="mt-4 overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface p-5">
        <table class="admin-table">
            <thead><tr><th>Klant</th><th>Soort</th><th>Verificatie</th><th>Kavels</th><th>Biedingen</th><th>Sinds</th><th></th></tr></thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td><span class="font-semibold">{{ $customer->name }}</span><span class="block text-xs text-aj-muted">{{ $customer->email }}</span></td>
                        <td class="text-xs">{{ $customer->company ? 'Zakelijk · '.$customer->company->name : 'Particulier' }}{{ $customer->is_staff ? ' · beheer' : '' }}</td>
                        <td class="text-xs">{{ $customer->kyc_status->label() }}</td>
                        <td class="tabular">{{ $customer->lots_count }}</td>
                        <td class="tabular">{{ $customer->bids_count }}</td>
                        <td class="text-xs text-aj-muted">{{ $customer->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') }}</td>
                        <td><a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-ghost" style="height:30px;font-size:12px">Klantkaart</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-aj-muted">Geen klanten gevonden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
</x-layouts.admin>
