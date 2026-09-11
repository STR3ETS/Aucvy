@use('App\Support\Money\Money')

<x-layouts.admin title="AI-runs">
    <p class="mt-2 text-sm text-aj-muted">
        Totale kosten tot nu toe:
        <span class="tabular font-extrabold text-aj-text">{{ Money::format($totalCostCents) }}</span>
    </p>

    <x-admin.table :items="$runs" searchable placeholder="Taaknaam">
        <x-slot:head>
            <th>Id</th><th>Taak</th><th>Agent</th><th>Model</th><th>Status</th><th class="text-right">Tokens in/uit</th><th class="text-right">Kosten</th><th>Wanneer</th>
        </x-slot:head>
        @foreach ($runs as $run)
            <tr>
                <td class="tabular text-aj-muted">{{ $run->id }}</td>
                <td class="font-semibold">{{ $run->task }}</td>
                <td>{{ $run->agent }}</td>
                <td class="text-aj-muted">{{ $run->model }}</td>
                <td>{{ $run->status->value }}</td>
                <td class="tabular text-right text-aj-muted">{{ $run->tokens_in ?? 0 }} / {{ $run->tokens_out ?? 0 }}</td>
                <td class="tabular text-right font-semibold">{{ Money::format((int) $run->cost_cents) }}</td>
                <td class="whitespace-nowrap text-aj-muted">{{ $run->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
            </tr>
        @endforeach
    </x-admin.table>
</x-layouts.admin>
