@use('App\Enums\ViewingStatus')

<x-layouts.admin title="Bezichtigingen" subtitle="Aanvragen, bevestigingen en wanneer het adres is gedeeld.">
    <x-admin.table :items="$viewings">
        <x-slot:filters>
            <label><span class="sr-only">Status</span>
                <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                    <option value="">Alle statussen</option>
                    @foreach (ViewingStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Id</th><th>Kavel</th><th>Aanvrager</th><th>Moment</th><th>Status</th><th>Adres gedeeld</th>
        </x-slot:head>
        @foreach ($viewings as $viewing)
            <tr>
                <td class="tabular text-aj-muted">{{ $viewing->id }}</td>
                <td class="max-w-[26ch] truncate font-semibold">
                    <a href="{{ route('admin.lots.show', $viewing->lot) }}" class="text-aj-blue-text underline">{{ $viewing->lot->title }}</a>
                </td>
                <td><a href="{{ route('admin.users.show', $viewing->user) }}" class="underline">{{ $viewing->user->name }}</a></td>
                <td class="whitespace-nowrap">{{ $viewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                <td>{{ $viewing->status->label() }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $viewing->address_sent_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
