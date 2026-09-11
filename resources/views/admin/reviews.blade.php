<x-layouts.admin title="Reviews">
    <x-admin.table :items="$reviews">
        <x-slot:head>
            <th>Id</th><th class="text-right">Sterren</th><th>Kavel</th><th>Reviewer</th><th>Verkoper</th><th>Gepubliceerd</th>
        </x-slot:head>
        @foreach ($reviews as $review)
            <tr>
                <td class="tabular text-aj-muted">{{ $review->id }}</td>
                <td class="tabular text-right font-extrabold">{{ $review->rating }}</td>
                <td class="max-w-[24ch] truncate">{{ $review->order->lot->title }}</td>
                <td>{{ $review->reviewer->name }}</td>
                <td>{{ $review->seller->name }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $review->published_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') ?? 'nog niet' }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
