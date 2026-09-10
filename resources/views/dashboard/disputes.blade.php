@use('App\Enums\DisputeStatus')

<x-layouts.public title="Geschillen">
    <h1 class="text-3xl font-extrabold tracking-[-0.03em]">Geschillen</h1>
    <x-dashboard.nav />

    @if ($disputes->isEmpty())
        <div class="mt-6">
            <x-ui.empty-state
                title="Geen geschillen"
                body="Zo hoort het. Gaat er ooit iets mis, dan volg je hier de melding, de reactie en de uitkomst." />
        </div>
    @else
        <ul class="mt-6 space-y-2">
            @foreach ($disputes as $dispute)
                <li class="flex flex-wrap items-center gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold">{{ $dispute->order->lot->title }}</span>
                        <span class="block text-xs text-aj-muted">
                            Bestelling {{ $dispute->order->order_number }}
                            · gemeld {{ $dispute->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM') }}
                            @if ($dispute->status !== DisputeStatus::Resolved && $dispute->sla_due_at)
                                · reactie vóór {{ $dispute->sla_due_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm') }}
                            @endif
                        </span>
                    </span>

                    <span @class([
                        'rounded-full px-2.5 py-1 text-xs font-bold',
                        'bg-aj-success/10 text-aj-success-text' => $dispute->status === DisputeStatus::Resolved,
                        'bg-aj-warn/10 text-aj-warn-text' => $dispute->status !== DisputeStatus::Resolved,
                    ])>{{ $dispute->status->label() }}</span>

                    <a href="{{ route('orders.show', $dispute->order) }}" class="btn btn-ghost" style="height:36px;font-size:13px">Bekijk</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.public>
