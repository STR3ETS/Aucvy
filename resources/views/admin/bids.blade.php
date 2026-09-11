@use('App\Enums\BidStatus')
@use('App\Support\Money\Money')

<x-layouts.admin title="Biedingen" subtitle="Elk bod, met wie, hoeveel en de uitkomst.">
    <x-admin.table :items="$bids" searchable placeholder="Bieder of kavel">
        <x-slot:filters>
            <label><span class="sr-only">Status</span>
                <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                    <option value="">Alle statussen</option>
                    @foreach (BidStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Id</th><th>Kavel</th><th>Bieder</th><th class="text-right">Bedrag</th><th>Soort</th><th>Status</th><th>Geplaatst</th>
        </x-slot:head>
        @foreach ($bids as $bid)
            <tr>
                <td class="tabular text-aj-muted">{{ $bid->id }}</td>
                <td class="max-w-[26ch] truncate font-semibold">
                    <a href="{{ route('admin.lots.show', $bid->auction->lot) }}" class="text-aj-blue-text underline">{{ $bid->auction->lot->title }}</a>
                </td>
                <td><a href="{{ route('admin.users.show', $bid->user) }}" class="underline">{{ $bid->user->name }}</a></td>
                <td class="tabular text-right font-semibold">{{ Money::format($bid->amount_cents) }}</td>
                <td>{{ $bid->type->label() }}</td>
                <td>{{ $bid->status->label() }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $bid->placed_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm:ss') }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
