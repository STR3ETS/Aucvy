@use('App\Support\Money\Money')

@php
    $percent = fn (float|int|string $value): string => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',').'%';
    $whole = fn (int $cents): string => '€ '.number_format($cents / 100, 0, ',', '.');
    $displayTimezone = config('aucvy.display_timezone');

    // Hoe ver de omzet binnen de huidige trede zit, voor de balk naar het volgende tarief.
    $tierProgress = null;
    if (isset($tier) && $tier['next']) {
        $lower = 0;
        foreach ($tier['tiers'] as $step) {
            if ((float) $step['percent'] === (float) $feePercentage) {
                $span = max(1, (int) $step['up_to_cents'] - $lower);
                $tierProgress = (int) max(2, min(100, round(($tier['revenue_cents'] - $lower) / $span * 100)));
                break;
            }
            $lower = (int) $step['up_to_cents'];
        }
    }
@endphp

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
        {{-- Links de lijsten waar je in zoekt (verkopen en facturen), rechts wat je in één blik leest (tarief en maanden). --}}
        <div class="d-fin" x-data="{ tab: 'verkopen' }">
            <div class="d-fin-main">
                <div class="d-card-head" style="align-items:center">
                    <div class="d-chips" role="tablist" aria-label="Wat wil je zien">
                        <button type="button" role="tab" class="d-chip" :class="tab === 'verkopen' && 'is-on'" :aria-selected="tab === 'verkopen'" @click="tab = 'verkopen'">Verkopen <span style="font-weight:700;opacity:.7">{{ $lines->count() }}</span></button>
                        @if ($invoices->isNotEmpty())
                            <button type="button" role="tab" class="d-chip" :class="tab === 'facturen' && 'is-on'" :aria-selected="tab === 'facturen'" @click="tab = 'facturen'">Facturen <span style="font-weight:700;opacity:.7">{{ $invoices->count() }}</span></button>
                        @endif
                    </div>
                </div>

                <section class="d-datatable" aria-label="Verkopen" x-show="tab === 'verkopen'">
                    <table>
                        <thead><tr><th>Bestelling</th><th>Kavel</th><th class="num">Hamer</th><th class="num">Fee</th><th class="num">Netto</th><th>Uitbetaling</th></tr></thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr>
                                    <td class="nowrap"><a href="{{ route('orders.show', $line['order']) }}" class="d-link">{{ $line['order']->order_number }}</a><small>{{ $line['order']->created_at->timezone($displayTimezone)->isoFormat('D MMM YYYY') }}</small></td>
                                    <td class="clip">{{ $line['order']->lot?->title }}</td>
                                    <td class="num">{{ Money::format($line['hammer_cents']) }}</td>
                                    <td class="num">{{ Money::format($line['fee_cents']) }}<small>btw {{ Money::format($line['fee_vat_cents']) }}</small></td>
                                    <td class="num"><strong>{{ Money::format($line['payout_cents']) }}</strong></td>
                                    <td class="nowrap">{{ $line['payout_status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>

                @if ($invoices->isNotEmpty())
                    <div x-show="tab === 'facturen'" x-cloak>
                        <p class="d-sub" style="margin:0 0 10px">Kavelfacturen namens jou aan de koper, en de facturen van Aucvy voor de succesfee. Opslaan als pdf via Afdrukken.</p>
                        <section class="d-datatable" aria-labelledby="facturen">
                            <h2 id="facturen" class="sr-only">Facturen</h2>
                            <table>
                                <thead><tr><th>Factuur</th><th>Soort</th><th>Bestelling</th><th class="num">Bedrag</th><th><span class="sr-only">Openen</span></th></tr></thead>
                                <tbody>
                                    @foreach ($invoices as $invoice)
                                        <tr>
                                            <td class="nowrap"><strong>{{ $invoice->number }}</strong><small>{{ $invoice->issued_at->isoFormat('D MMM YYYY') }}</small></td>
                                            <td>{{ __('invoices.for.'.$invoice->kind) }}</td>
                                            <td class="nowrap">{{ $invoice->order->order_number }}</td>
                                            <td class="num"><strong>{{ Money::format($invoice->total_cents) }}</strong></td>
                                            <td class="num"><a href="{{ route('invoices.show', $invoice) }}" class="d-link">Bekijken</a></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    </div>
                @endif
            </div>

            <aside class="d-fin-aside">
                {{-- De staffel (besluit Raph en Don, 2 november 2026): omzet op Aucvy in de laatste twaalf maanden bepaalt de fee. --}}
                @if (isset($tier))
                    <section class="d-card" aria-labelledby="staffel">
                        <h2 id="staffel" class="d-h3">Je succesfee</h2>
                        <div class="d-big" style="margin-top:6px">{{ $percent($feePercentage) }}</div>
                        <p class="d-sub">Op basis van {{ $whole($tier['revenue_cents']) }} omzet op Aucvy in de laatste twaalf maanden.</p>
                        @if ($tier['next'])
                            <div class="d-depth-bar" style="height:8px;border-radius:4px" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $tierProgress ?? 0 }}" aria-label="Voortgang naar het volgende tarief">
                                <i style="width:{{ $tierProgress ?? 0 }}%;border-radius:4px"></i>
                            </div>
                            <p class="d-sub">Nog {{ $whole($tier['next']['remaining_cents']) }} tot {{ $percent($tier['next']['percent']) }}.</p>
                        @else
                            <p class="d-sub">Dit is de laagste trede.</p>
                        @endif
                        <ul class="d-tiers" aria-label="De staffel">
                            @foreach ($tier['tiers'] as $step)
                                <li @class(['is-on' => (float) $step['percent'] === (float) $feePercentage])>
                                    <span>{{ $step['up_to_cents'] === null ? 'Daarboven' : 'Tot '.$whole($step['up_to_cents']) }}</span>
                                    <strong>{{ $percent($step['percent']) }}</strong>
                                </li>
                            @endforeach
                        </ul>
                        <p class="d-hint">Het tarief ligt vast op het moment van verkoop en wordt nooit achteraf verrekend.</p>
                    </section>
                @endif

                <section class="d-card" aria-label="Per maand">
                    <h2 class="d-h3">Per maand</h2>
                    <div class="d-rows" style="margin-top:8px">
                        @foreach ($perMonth as $month => $sum)
                            <div class="d-row">
                                <span>{{ ucfirst(\Carbon\Carbon::createFromFormat('Y-m', $month)->isoFormat('MMMM YYYY')) }}<small class="d-hint" style="margin-top:2px">{{ $sum['sales'] }} {{ $sum['sales'] === 1 ? 'verkoop' : 'verkopen' }}</small></span>
                                <strong>{{ Money::format($sum['hammer_cents']) }}<small class="d-hint" style="margin-top:2px;font-weight:500">fee {{ Money::format($sum['fee_cents']) }}</small></strong>
                            </div>
                        @endforeach
                    </div>
                    <p class="d-hint" style="margin-top:12px">De succesfee is {{ $percent($feePercentage) }} over het hamerbedrag, plus {{ (int) Money::vatRatePercent() }}% btw. Een factuur voor de fee volgt zodra de facturatie is vastgesteld.</p>
                </section>
            </aside>
        </div>
    @endif
</x-layouts.dashboard>
