<x-layouts.admin title="Bezichtigingen">
    <x-admin.table :items="$viewings">
        <x-slot:head>
            <th>Id</th><th>Kavel</th><th>Aanvrager</th><th>Moment</th><th>Status</th><th>Adres gedeeld</th>
        </x-slot:head>
        @foreach ($viewings as $viewing)
            <tr>
                <td class="tabular text-aj-muted">{{ $viewing->id }}</td>
                <td class="max-w-[26ch] truncate font-semibold">{{ $viewing->lot->title }}</td>
                <td>{{ $viewing->user->name }}</td>
                <td class="whitespace-nowrap">{{ $viewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                <td>{{ $viewing->status->value }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $viewing->address_sent_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
