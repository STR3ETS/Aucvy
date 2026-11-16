@use('App\Enums\SaleState')

<x-layouts.admin title="Gebundelde veilingen" :subtitle="$sales->total().' veilingen met meerdere kavels (Auction Studio); de biedrondes per kavel staan onder Veilingen'">
    <form method="GET" class="mt-6 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek op naam of verkoper" class="d-input" style="min-height:40px;min-width:260px">
        <button type="submit" class="d-btn d-btn-dark d-btn-sq" style="height:40px;font-size:13px">Zoeken</button>
    </form>

    @if ($sales->isEmpty())
        <p class="mt-4 d-card d-admin-empty">Nog geen gebundelde veilingen.</p>
    @else
        <div class="mt-4 overflow-x-auto d-card">
            <table class="admin-table">
                <thead><tr><th>Veiling</th><th>Verkoper</th><th>Kavels</th><th>Status</th><th>Eerste sluiting</th><th></th></tr></thead>
                <tbody>
                    @foreach ($sales as $sale)
                        @php $state = $sale->state(); @endphp
                        <tr>
                            <td class="font-bold">{{ $sale->title }}<span class="block text-xs font-normal text-aj-muted">#{{ $sale->public_id }}</span></td>
                            <td><a href="{{ route('admin.users.show', $sale->seller) }}" class="hover:underline">{{ $sale->seller->name }}</a>{{ $sale->company ? ' · '.$sale->company->name : '' }}</td>
                            <td class="tabular">{{ $sale->lots_count }}</td>
                            <td><span class="rounded-full border px-2.5 py-1 text-xs font-bold {{ $state === SaleState::Live ? 'border-aj-success/40 text-aj-success-text' : ($state === SaleState::Cancelled ? 'border-aj-error/40 text-aj-error' : 'border-aj-border text-aj-muted') }}">{{ $state->label() }}</span></td>
                            <td class="whitespace-nowrap">{{ $sale->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                            <td class="whitespace-nowrap text-right">
                                @if ($sale->isPublished())
                                    <a href="{{ route('sales.show', $sale) }}" class="d-iconbtn" target="_blank" rel="noopener" data-tip="Bekijk op de site" aria-label="Bekijk op de site"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $sales->links() }}</div>
    @endif
</x-layouts.admin>
