@use('App\Enums\AiRunStatus')
@use('App\Support\Money\Money')

<x-layouts.admin title="AI-runs"
                 :subtitle="'Totale kosten tot nu toe: '.Money::format($totalCostCents).'.'">
    <x-admin.table :items="$runs" searchable placeholder="Taaknaam"
                   :bulk-action="route('admin.ai-runs.destroy')"
                   bulk-label="Verwijderen"
                   bulk-confirm="Geselecteerde AI-runs verwijderen?"
                   :bulk-ids="$runs->pluck('id')">
        <x-slot:filters>
            <label>
                <span class="sr-only">Status</span>
                <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                        style="min-height:44px">
                    <option value="">Alle statussen</option>
                    @foreach (AiRunStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>
                    @endforeach
                </select>
            </label>
        </x-slot:filters>
        <x-slot:head>
            <th>Id</th><th>Taak</th><th>Agent</th><th>Model</th><th>Status</th><th class="text-right">Tokens in/uit</th><th class="text-right">Kosten</th><th>Wanneer</th>
        </x-slot:head>
        @foreach ($runs as $run)
            <tr>
                <td><input type="checkbox" class="rounded" name="ids[]" value="{{ $run->id }}" x-model="selected"
                           aria-label="Selecteer run {{ $run->id }}"></td>
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
