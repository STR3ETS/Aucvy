@use('App\Enums\LotStatus')
@use('App\Support\Money\Money')

<x-layouts.admin title="Kavels" subtitle="Alles wat er staat of stond, van concept tot verkocht.">
    <x-admin.table :items="$lots" searchable placeholder="Titel of kavelnummer"
                   :bulk-action="route('admin.lots.destroy-drafts')"
                   bulk-label="Concepten verwijderen"
                   bulk-confirm="Geselecteerde concepten verwijderen? Alleen concepten worden geraakt."
                   :bulk-ids="$lots->pluck('id')">
        <x-slot:filters>
            <label>
                <span class="sr-only">Status</span>
                <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                        style="min-height:44px">
                    <option value="">Alle statussen</option>
                    @foreach (LotStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Nr</th><th>Titel</th><th>Verkoper</th><th>Categorie</th><th>Status</th><th class="text-right">Stand</th><th></th>
        </x-slot:head>
        @foreach ($lots as $lot)
            <tr>
                <td><input type="checkbox" class="rounded" name="ids[]" value="{{ $lot->id }}" x-model="selected"
                           aria-label="Selecteer {{ $lot->title }}"></td>
                <td class="tabular text-aj-muted">{{ $lot->public_id }}</td>
                <td class="max-w-[30ch] truncate font-semibold">
                    <a href="{{ route('admin.lots.show', $lot) }}" class="text-aj-blue-text underline">{{ $lot->title }}</a>
                </td>
                <td><a href="{{ route('admin.users.show', $lot->seller) }}" class="underline">{{ $lot->seller->name }}</a></td>
                <td>{{ $lot->category?->name }}</td>
                <td>{{ $lot->status->label() }}</td>
                <td class="tabular text-right font-semibold">{{ $lot->auction ? Money::format($lot->currentPriceCents()) : '-' }}</td>
                <td class="text-right">
                    <a href="{{ route('admin.lots.show', $lot) }}" class="font-bold text-aj-blue-text underline">Bekijk</a>
                </td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
