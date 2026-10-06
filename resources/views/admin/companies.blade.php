<x-layouts.admin title="Bedrijven" subtitle="Elke zakelijke verkoper, met verificatiestatus en fee.">
    <x-admin.table :items="$companies" searchable placeholder="Naam of KVK">
        <x-slot:filters>
            <label><span class="sr-only">Verificatie</span>
                <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                    <option value="">Alle bedrijven</option>
                    <option value="geverifieerd" @selected(request('status') === 'geverifieerd')>Geverifieerd</option>
                    <option value="ongeverifieerd" @selected(request('status') === 'ongeverifieerd')>Nog niet geverifieerd</option>
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Id</th><th>Naam</th><th>KVK</th><th>Btw-nummer</th><th>Geverifieerd</th><th class="text-right">Succesfee</th>
        </x-slot:head>
        @foreach ($companies as $company)
            <tr>
                <td class="tabular text-aj-muted">{{ $company->id }}</td>
                <td class="font-semibold">
                    <a href="{{ route('admin.companies.show', $company) }}" class="text-aj-blue-text underline">{{ $company->name }}</a>
                </td>
                <td class="tabular">{{ $company->kvk_number }}</td>
                <td class="tabular text-aj-muted">{{ $company->vat_number }}</td>
                <td>{{ $company->verified_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') ?? 'nee' }}</td>
                <td class="tabular text-right">{{ number_format((float) $company->fee_percentage, 1, ',', '.') }}%</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
