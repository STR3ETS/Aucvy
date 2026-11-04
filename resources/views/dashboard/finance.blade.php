@use('App\Support\Money\Money')

<x-layouts.dashboard title="Financiën" eyebrow="Dashboard" intro="Per verkoop het hamerbedrag, de succesfee met btw en wat er wordt uitbetaald. Te downloaden voor je boekhouding.">
    <x-slot:actions>
        @if ($lines->isNotEmpty())
            <a class="btn btn-white" href="{{ route('dashboard.finance.export') }}">Download csv</a>
        @endif
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="green" icon="banknote" label="Omzet" :value="Money::format($totals['hammer_cents'])" :sub="$totals['sales'].' '.($totals['sales'] === 1 ? 'verkoop' : 'verkopen').' in totaal'" />
        <x-dashboard.tile tone="orange" icon="clock" label="Succesfee" :value="Money::format($totals['fee_cents'])" :sub="'plus '.Money::format($totals['fee_vat_cents']).' btw'" />
        <x-dashboard.tile tone="blue" icon="trending-up" label="Netto" :value="Money::format($totals['payout_cents'])" sub="uitbetaald of onderweg" :href="route('dashboard.payouts')" />
        <x-dashboard.tile tone="purple" icon="badge-check" label="Deze maand" :value="Money::format($thisMonth['hammer_cents'])" :sub="$thisMonth['sales'].' '.($thisMonth['sales'] === 1 ? 'verkoop' : 'verkopen')" />
    </x-slot:tiles>

    @if ($lines->isEmpty())
        <x-ui.empty-state title="Nog geen verkopen"
            body="Zodra een kavel verkocht en betaald is, staat hier de afrekening: hamerbedrag, fee en uitbetaling.">
            <x-slot:action>
                <x-ui.button :href="route('sell')">Kavel plaatsen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="grid gap-5 lg:grid-cols-[1fr_300px] lg:items-start">
            {{-- De staffel (besluit Raph en Don, 2 november 2026): omzet op Aucvy in de laatste twaalf maanden bepaalt de fee. --}}
            @if (isset($tier))
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="staffel">
                    <h2 id="staffel" class="text-sm font-extrabold">Je succesfee: {{ rtrim(rtrim(number_format($feePercentage, 1, ',', '.'), '0'), ',') }}%</h2>
                    <p class="mt-1 text-xs text-aj-muted">
                        Op basis van {{ Money::format($tier['revenue_cents']) }} omzet op Aucvy in de laatste twaalf maanden.
                        @if ($tier['next'])
                            Nog {{ Money::format($tier['next']['remaining_cents']) }} tot {{ rtrim(rtrim(number_format($tier['next']['percent'], 1, ',', '.'), '0'), ',') }}%.
                        @else
                            Dit is de laagste trede.
                        @endif
                        Het tarief ligt vast op het moment van verkoop en wordt nooit achteraf verrekend.
                    </p>
                    <ul class="mt-3 flex flex-wrap gap-1.5 text-xs">
                        @foreach ($tier['tiers'] as $step)
                            <li @class(['rounded-full px-2.5 py-1 font-bold', 'bg-aj-navy text-white' => (float) $step['percent'] === (float) $feePercentage, 'border border-aj-border' => (float) $step['percent'] !== (float) $feePercentage])>
                                {{ $step['up_to_cents'] === null ? 'daarboven' : 'tot '.Money::format($step['up_to_cents']) }}: {{ rtrim(rtrim(number_format($step['percent'], 1, ',', '.'), '0'), ',') }}%
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface" aria-label="Verkopen">
                <table class="admin-table">
                    <thead><tr><th>Bestelling</th><th>Kavel</th><th class="text-right">Hamer</th><th class="text-right">Fee</th><th class="text-right">Btw</th><th class="text-right">Netto</th><th>Uitbetaling</th></tr></thead>
                    <tbody>
                        @foreach ($lines as $line)
                            <tr>
                                <td class="whitespace-nowrap"><a href="{{ route('orders.show', $line['order']) }}" class="font-bold underline">{{ $line['order']->order_number }}</a><br><span class="text-xs text-aj-muted">{{ $line['order']->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') }}</span></td>
                                <td class="max-w-[28ch] truncate">{{ $line['order']->lot?->title }}</td>
                                <td class="tabular text-right">{{ Money::format($line['hammer_cents']) }}</td>
                                <td class="tabular text-right">{{ Money::format($line['fee_cents']) }}</td>
                                <td class="tabular text-right text-aj-muted">{{ Money::format($line['fee_vat_cents']) }}</td>
                                <td class="tabular text-right font-bold">{{ Money::format($line['payout_cents']) }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $line['payout_status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <aside class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Per maand">
                <h2 class="text-sm font-extrabold">Per maand</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($perMonth as $month => $sum)
                        <li class="flex items-baseline justify-between gap-3 border-b border-aj-border pb-2">
                            <span>{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->isoFormat('MMMM YYYY') }}<br><span class="text-xs text-aj-muted">{{ $sum['sales'] }} {{ $sum['sales'] === 1 ? 'verkoop' : 'verkopen' }}</span></span>
                            <span class="text-right"><b class="tabular">{{ Money::format($sum['hammer_cents']) }}</b><br><span class="tabular text-xs text-aj-muted">fee {{ Money::format($sum['fee_cents']) }}</span></span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-xs text-aj-muted">De succesfee is {{ rtrim(rtrim(number_format($feePercentage, 1, ',', '.'), '0'), ',') }}% over het hamerbedrag, plus {{ (int) \App\Support\Money\Money::vatRatePercent() }}% btw. Een factuur voor de fee volgt zodra de facturatie is vastgesteld.</p>
            </aside>
        </div>
    @endif
</x-layouts.dashboard>
