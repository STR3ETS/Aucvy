<x-layouts.admin title="Gebruikers">
    <x-admin.table :items="$users" searchable placeholder="Naam of e-mail">
        <x-slot:head>
            <th>Id</th><th>Naam</th><th>E-mail</th><th>Type</th><th>KYC</th><th class="text-right">Kavels</th><th class="text-right">Biedingen</th><th>Vlaggen</th>
        </x-slot:head>
        @foreach ($users as $user)
            <tr @class(['opacity-50' => $user->trashed()])>
                <td class="tabular text-aj-muted">{{ $user->id }}</td>
                <td class="font-semibold">{{ $user->name }}@if ($user->trashed()) <span class="text-xs text-aj-muted">(geanonimiseerd)</span>@endif</td>
                <td class="text-aj-muted">{{ $user->email }}</td>
                <td>{{ $user->type?->value }}</td>
                <td>{{ $user->kyc_status->value }}</td>
                <td class="tabular text-right">{{ $user->lots_count }}</td>
                <td class="tabular text-right">{{ $user->bids_count }}</td>
                <td class="space-x-1 whitespace-nowrap">
                    @if ($user->is_staff)<span class="rounded-full bg-aj-blue/10 px-2 py-0.5 text-xs font-bold text-aj-blue-text">beheer</span>@endif
                    @if ($user->can_bid)<span class="rounded-full bg-aj-success/10 px-2 py-0.5 text-xs font-bold text-aj-success-text">kan bieden</span>@endif
                </td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
