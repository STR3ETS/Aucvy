<x-layouts.admin title="Bedrijven">
    <x-admin.table :items="$companies" searchable placeholder="Naam of KVK">
        <x-slot:head>
            <th>Id</th><th>Naam</th><th>KVK</th><th>Btw-nummer</th><th>Geverifieerd</th><th class="text-right">Succesfee</th>
        </x-slot:head>
        @foreach ($companies as $company)
            <tr>
                <td class="tabular text-aj-muted">{{ $company->id }}</td>
                <td class="font-semibold">{{ $company->name }}</td>
                <td class="tabular">{{ $company->kvk_number }}</td>
                <td class="tabular text-aj-muted">{{ $company->vat_number }}</td>
                <td>{{ $company->verified_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') ?? 'nee' }}</td>
                <td class="tabular text-right">{{ number_format((float) $company->fee_percentage, 1, ',', '.') }}%</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
