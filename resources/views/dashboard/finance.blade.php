@use('App\Models\Invoice')
@use('App\Support\Money\Money')

@php
    $percent = fn (float|int|string $value): string => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',').'%';
    $whole = fn (int $cents): string => '€ '.number_format($cents / 100, 0, ',', '.');
    $displayTimezone = config('aucvy.display_timezone');
    $vatPercent = (int) Money::vatRatePercent();

    // Per verkoop de factuur die erbij hoort: die van Aucvy voor de fee, anders de kavelfactuur.
    $invoiceFor = $invoices->groupBy('order_id')->map(fn ($group) => $group->firstWhere('kind', Invoice::FEE) ?? $group->firstWhere('kind', Invoice::LOT));

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

<x-layouts.dashboard title="Financiën" eyebrow="Dashboard" intro="De afrekening per verkoop: hamerbedrag, fee en uitbetaling, met de factuur erbij.">
    <x-slot:actions>
        @if ($lines->isNotEmpty())
            <a class="btn btn-white" href="{{ route('dashboard.finance.export') }}">Exporteren</a>
        @endif
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" icon="trending-up" label="Hamerbedrag" :value="Money::format($totals['hammer_cents'])" :sub="$totals['sales'].' '.($totals['sales'] === 1 ? 'verkoop' : 'verkopen')" />
        <x-dashboard.tile tone="purple" icon="shopping-bag" label="Succesfee" :value="Money::format($totals['fee_cents'])" sub="exclusief btw" />
        <x-dashboard.tile tone="orange" icon="clock" label="Btw over fee" :value="Money::format($totals['fee_vat_cents'])" :sub="$vatPercent.'%'" />
        <x-dashboard.tile tone="green" icon="check" label="Netto" :value="Money::format($totals['payout_cents'])" sub="uitbetaald of onderweg" :href="route('dashboard.payouts')" />
    </x-slot:tiles>

    @if ($lines->isEmpty())
        <x-ui.empty-state title="Nog geen verkopen"
            body="Zodra een kavel verkocht en betaald is, staat hier de afrekening: hamerbedrag, fee en uitbetaling.">
            <x-slot:action>
                <x-ui.button :href="route('sell')">Kavel plaatsen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="d-stack" style="gap:18px" x-data="{ tab: 'afrekeningen' }">

            {{-- De afrekening per verkoop over de volle breedte, zoals in het ontwerp; alle facturen als tweede tabblad. --}}
            <div class="d-card-head" style="align-items:center">
                <h2 class="d-h3" x-text="tab === 'afrekeningen' ? 'Afrekeningen' : 'Facturen'">Afrekeningen</h2>
                @if ($invoices->isNotEmpty())
                    <div class="d-chips" role="tablist" aria-label="Wat wil je zien">
                        <button type="button" role="tab" class="d-chip d-chip-sm" :class="tab === 'afrekeningen' && 'is-on'" :aria-selected="tab === 'afrekeningen'" @click="tab = 'afrekeningen'">Afrekeningen <span style="font-weight:700;opacity:.7">{{ $lines->count() }}</span></button>
                        <button type="button" role="tab" class="d-chip d-chip-sm" :class="tab === 'facturen' && 'is-on'" :aria-selected="tab === 'facturen'" @click="tab = 'facturen'">Alle facturen <span style="font-weight:700;opacity:.7">{{ $invoices->count() }}</span></button>
                    </div>
                @endif
            </div>

            <section class="d-datatable" aria-label="Afrekeningen" x-show="tab === 'afrekeningen'" style="margin-top:-6px">
                <table>
                    <thead>
                        <tr>
                            <th>Kavel</th>
                            <th class="num">Hamerbedrag</th>
                            <th class="num">Fee</th>
                            <th class="num">Btw {{ $vatPercent }}%</th>
                            <th class="num">Netto</th>
                            <th>Uitbetaling</th>
                            <th>Factuur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $line)
                            @php $invoice = $invoiceFor->get($line['order']->id); @endphp
                            <tr>
                                <td class="clip"><a href="{{ route('orders.show', $line['order']) }}" class="d-link" style="font-size:14px">{{ $line['order']->lot?->title ?? $line['order']->order_number }}</a><small>{{ $line['order']->order_number }} · {{ $line['order']->created_at->timezone($displayTimezone)->isoFormat('D MMM YYYY') }}</small></td>
                                <td class="num">{{ Money::format($line['hammer_cents']) }}</td>
                                <td class="num">- {{ Money::format($line['fee_cents']) }}</td>
                                <td class="num is-soft">- {{ Money::format($line['fee_vat_cents']) }}</td>
                                <td class="num"><strong>{{ Money::format($line['payout_cents']) }}</strong></td>
                                <td class="nowrap">{{ $line['payout_status'] }}</td>
                                <td class="nowrap">
                                    @if ($invoice)
                                        <a href="{{ route('invoices.show', $invoice) }}" class="d-link">Bekijk factuur</a>
                                    @else
                                        <span class="d-muted">volgt</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row">Totaal</th>
                            <td class="num"><strong>{{ Money::format($totals['hammer_cents']) }}</strong></td>
                            <td class="num"><strong>- {{ Money::format($totals['fee_cents']) }}</strong></td>
                            <td class="num"><strong>- {{ Money::format($totals['fee_vat_cents']) }}</strong></td>
                            <td class="num"><strong>{{ Money::format($totals['payout_cents']) }}</strong></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            @if ($invoices->isNotEmpty())
                <div x-show="tab === 'facturen'" x-cloak style="margin-top:-6px">
                    <p class="d-sub" style="margin:0 0 10px">Kavelfacturen namens jou aan de koper, en de facturen van Aucvy voor de succesfee. Opslaan als pdf via Afdrukken.</p>
                    <section class="d-datatable" aria-labelledby="facturen">
                        <h3 id="facturen" class="sr-only">Facturen</h3>
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

            <div class="d-callout-info">
                <span class="mark" aria-hidden="true">i</span>
                <div>
                    <strong>Betalen kopers ook kosten?</strong>
                    Kopers betalen geen opgeld, alleen de administratiekosten per bestelling. Jouw fee wordt alleen ingehouden bij een geslaagde verkoop en staat vooraf per kavel in de kostenopbouw.
                </div>
            </div>

            {{-- Niet in het ontwerp, wel nodig: hoe je tarief tot stand komt en de maanden op een rij. --}}
            <div class="d-fin-extra">
                {{-- De staffel (besluit Raph en Don, 2 november 2026): omzet op Aucvy in de laatste twaalf maanden bepaalt de fee. --}}
                @if (isset($tier))
                    <section class="d-card" aria-labelledby="staffel">
                        <div class="d-card-head">
                            <div>
                                <h2 id="staffel" class="d-h3">Je succesfee: {{ $percent($feePercentage) }}</h2>
                                <p class="d-sub">Op basis van {{ $whole($tier['revenue_cents']) }} omzet op Aucvy in de laatste twaalf maanden.
                                    @if ($tier['next'])
                                        Nog {{ $whole($tier['next']['remaining_cents']) }} tot {{ $percent($tier['next']['percent']) }}.
                                    @else
                                        Dit is de laagste trede.
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if ($tier['next'])
                            <div class="d-depth-bar" style="height:8px;border-radius:4px" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $tierProgress ?? 0 }}" aria-label="Voortgang naar het volgende tarief">
                                <i style="width:{{ $tierProgress ?? 0 }}%;border-radius:4px"></i>
                            </div>
                        @endif
                        <ul class="d-tiers is-row" aria-label="De staffel">
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
                </section>
            </div>
        </div>
    @endif
</x-layouts.dashboard>
