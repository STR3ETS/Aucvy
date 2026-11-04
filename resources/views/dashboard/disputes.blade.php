@use('App\Enums\DisputeStatus')

@php
    $openDisputes = $disputes->filter(fn ($dispute): bool => $dispute->status !== DisputeStatus::Resolved);
    $resolved = $disputes->count() - $openDisputes->count();
@endphp

<x-layouts.dashboard title="Geschillen" eyebrow="Dashboard" intro="Meldingen, reacties en uitkomsten, met de termijnen die erbij horen.">
    <x-slot:tiles>
        <x-dashboard.tile tone="orange" icon="clock" label="Open" :value="$openDisputes->count()" sub="wacht op een reactie of beoordeling" />
        <x-dashboard.tile tone="green" icon="badge-check" label="Afgehandeld" :value="$resolved" sub="met een uitkomst" />
        <x-dashboard.tile tone="blue" icon="shield-check" label="Totaal" :value="$disputes->count()" sub="geschillen bij je bestellingen" />
        <x-dashboard.tile tone="purple" icon="message-circle" label="Hulp" value="FAQ" sub="hoe de geschilroute werkt" :href="route('assurance')" />
    </x-slot:tiles>

    @if ($disputes->isEmpty())
        <div>
            <x-ui.empty-state
                title="Geen geschillen"
                body="Zo hoort het. Gaat er ooit iets mis, dan volg je hier de melding, de reactie en de uitkomst." />
        </div>
    @else
        <ul class="space-y-2">
            @foreach ($disputes as $dispute)
                <li class="flex flex-wrap items-center gap-3 d-card">
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
</x-layouts.dashboard>
