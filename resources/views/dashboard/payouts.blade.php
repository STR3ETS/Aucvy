@use('App\Enums\PayoutStatus')
@use('App\Support\Money\Money')

@php
    // Vrijgegeven of uitbetaald: het platform geeft vrij, de overboeking zelf
    // loopt via de betaalprovider en wordt hier (nog) niet teruggemeld.
    $paid = (int) $payouts->whereIn('status', [PayoutStatus::Released, PayoutStatus::Paid])->sum('amount_cents');
    $pending = $payouts->whereIn('status', [PayoutStatus::Scheduled, PayoutStatus::Held]);
    $held = $payouts->where('status', PayoutStatus::Held)->count();
    $fees = (int) $payouts->sum('fee_cents');
@endphp

<x-layouts.dashboard title="Uitbetalingen" eyebrow="Dashboard" intro="Wat er na een verkoop wanneer wordt uitbetaald, met de houdtermijn erbij.">
    <x-slot:tiles>
        <x-dashboard.tile tone="green" icon="banknote" label="Vrijgegeven" :value="Money::format($paid)" sub="vrijgegeven voor uitbetaling" />
        <x-dashboard.tile tone="orange" icon="clock" label="Onderweg" :value="Money::format((int) $pending->sum('amount_cents'))" :sub="$held.' '.($held === 1 ? 'uitbetaling' : 'uitbetalingen').' in de houdtermijn'" />
        <x-dashboard.tile tone="blue" icon="gavel" label="Uitbetalingen" :value="$payouts->count()" sub="in totaal" />
        <x-dashboard.tile tone="purple" icon="badge-check" label="Succesfee" :value="Money::format($fees)" sub="ingehouden over alle verkopen" />
    </x-slot:tiles>

    @if ($payouts->isEmpty())
        <div>
            <x-ui.empty-state
                title="Nog geen uitbetalingen"
                body="Na een verkoop en de houdtermijn verschijnt hier wat er wanneer wordt uitbetaald.">
                <x-slot:action>
                    <x-ui.button :href="route('sell')">Kavel plaatsen</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </div>
    @else
        <ul class="space-y-2">
            @foreach ($payouts as $payout)
                <li class="flex flex-wrap items-center gap-3 d-card">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-bold">{{ $payout->order->lot->title }}</span>
                        <span class="block text-xs text-aj-muted">
                            Bestelling {{ $payout->order->order_number }}
                            · fee {{ Money::format($payout->fee_cents) }}
                            @if ($payout->status === PayoutStatus::Held && $payout->hold_until)
                                · vrijgave {{ $payout->hold_until->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm') }}
                            @elseif ($payout->paid_at)
                                · betaald {{ $payout->paid_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM') }}
                            @endif
                        </span>
                    </span>

                    <span class="tabular text-sm font-extrabold">{{ Money::format($payout->amount_cents) }}</span>

                    <span @class([
                        'rounded-full px-2.5 py-1 text-xs font-bold',
                        'bg-aj-success/10 text-aj-success-text' => in_array($payout->status, [PayoutStatus::Released, PayoutStatus::Paid], true),
                        'bg-aj-warn/10 text-aj-warn-text' => $payout->status === PayoutStatus::Held,
                        'bg-aj-bg text-aj-muted' => $payout->status === PayoutStatus::Scheduled,
                        'bg-aj-error/10 text-aj-error' => $payout->status === PayoutStatus::Failed,
                    ])>{{ $payout->status->label() }}</span>

                    <a href="{{ route('orders.show', $payout->order) }}" class="btn btn-ghost" style="height:36px;font-size:13px">Bekijk</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.dashboard>
