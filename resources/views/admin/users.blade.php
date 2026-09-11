@use('App\Enums\KycStatus')

<x-layouts.admin title="Gebruikers" subtitle="Iedereen op het platform, inclusief geanonimiseerde accounts.">
    <x-admin.table :items="$users" searchable placeholder="Naam of e-mail">
        <x-slot:filters>
            <label><span class="sr-only">KYC-status</span>
                <select name="kyc" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                    <option value="">Alle KYC-statussen</option>
                    @foreach (KycStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('kyc') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label><span class="sr-only">Rol</span>
                <select name="rol" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                    <option value="">Iedereen</option>
                    <option value="beheer" @selected(request('rol') === 'beheer')>Alleen beheer</option>
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Id</th><th>Naam</th><th>E-mail</th><th>Type</th><th>KYC</th><th class="text-right">Kavels</th><th class="text-right">Biedingen</th><th>Vlaggen</th>
        </x-slot:head>
        @foreach ($users as $user)
            <tr @class(['opacity-50' => $user->trashed()])>
                <td class="tabular text-aj-muted">{{ $user->id }}</td>
                <td class="font-semibold">
                    <a href="{{ route('admin.users.show', $user) }}" class="text-aj-blue-text underline">{{ $user->name }}</a>@if ($user->trashed()) <span class="text-xs text-aj-muted">(geanonimiseerd)</span>@endif
                </td>
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
