@use('App\Enums\PayoutStatus')
@use('App\Models\Invoice')
@use('App\Support\Money\Money')

@php
    $percent = fn (float|int|string $value): string => rtrim(rtrim(number_format((float) $value, 1, ',', '.'), '0'), ',').'%';
    $whole = fn (int $cents): string => '€ '.number_format($cents / 100, 0, ',', '.');
    $displayTimezone = config('aucvy.display_timezone');
    $vatPercent = (int) Money::vatRatePercent();
    $day = fn ($at): string => rtrim($at->timezone($displayTimezone)->isoFormat('D MMM YYYY'), '.');

    // Per verkoop de factuur die erbij hoort: die van Aucvy voor de fee, anders de kavelfactuur.
    $invoiceFor = $invoices->groupBy('order_id')->map(fn ($group) => $group->firstWhere('kind', Invoice::FEE) ?? $group->firstWhere('kind', Invoice::LOT));

    // Hoe ver de omzet binnen de huidige trede zit, voor de ring naar het volgende tarief.
    $tierProgress = 100;
    if (isset($tier) && $tier['next']) {
        $lower = 0;
        foreach ($tier['tiers'] as $step) {
            if ((float) $step['percent'] === (float) $feePercentage) {
                $span = max(1, (int) $step['up_to_cents'] - $lower);
                $tierProgress = (int) max(4, min(100, round(($tier['revenue_cents'] - $lower) / $span * 100)));
                break;
            }
            $lower = (int) $step['up_to_cents'];
        }
    }
    $payoutTone = fn (?PayoutStatus $status): string => match ($status) {
        PayoutStatus::Released, PayoutStatus::Paid => 'tone-ok',
        PayoutStatus::Held => 'tone-warn',
        PayoutStatus::Failed => 'tone-live',
        default => 'tone-grey',
    };
    $tabs = array_filter([
        'afrekeningen' => ['banknote', 'Afrekeningen', $lines->count()],
        'facturen' => $invoices->isNotEmpty() ? ['file-text', 'Facturen', $invoices->count()] : null,
        'maanden' => ['trending-up', 'Per maand', $perMonth->count()],
    ]);
    $listLimit = 10;
    $monthLimit = 6;
@endphp

{{--
    Financiën: één kaart met drie tabs (de afrekening per verkoop, alle
    facturen en de maanden op een rij) in dezelfde tabelstijl als Mijn
    kavels, en daaronder hoe je succesfee tot stand komt. De uitleg staat
    achter het vraagteken, de acties zijn pictogrammen.
--}}
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
        <div class="d-sections d-stack">
            <section class="d-card" aria-labelledby="boekhouding" x-data="{ tab: 'afrekeningen' }">
                <div class="d-titleline">
                    <h2 id="boekhouding" class="d-h2">Je boekhouding</h2>
                    <x-dashboard.help text="Per verkoop wat er binnenkwam en wat eraf ging. Kopers betalen geen opgeld, alleen de administratiekosten per bestelling. Jouw fee wordt alleen ingehouden bij een geslaagde verkoop en staat vooraf per kavel in de kostenopbouw." />
                </div>

                <div class="d-tabs" role="tablist" aria-label="Wat wil je zien">
                    @foreach ($tabs as $key => [$icon, $label, $count])
                        <button type="button" role="tab" id="fin-tab-{{ $key }}" aria-controls="fin-{{ $key }}"
                                :aria-selected="tab === '{{ $key }}'" :class="tab === '{{ $key }}' && 'is-on'" class="{{ $loop->first ? 'is-on' : '' }}"
                                @click="tab = '{{ $key }}'"><x-v4.icon :name="$icon" :size="16" stroke="2.1" /> {{ $label }} <span class="d-count tabular">{{ $count }}</span></button>
                    @endforeach
                </div>

                {{-- De afrekening per verkoop, met het totaal eronder. --}}
                <div id="fin-afrekeningen" role="tabpanel" aria-labelledby="fin-tab-afrekeningen" x-show="tab === 'afrekeningen'" x-data="{ all: false }">
                    <div class="d-lottable is-wide" style="--cols:minmax(0,1fr) 130px 120px 110px 130px 150px 86px">
                        <div class="d-lottable-head" aria-hidden="true">
                            <span>Kavel</span>
                            <span class="num">Hamerbedrag</span>
                            <span class="num">Fee</span>
                            <span class="num">Btw {{ $vatPercent }}%</span>
                            <span class="num">Netto</span>
                            <span>Uitbetaling</span>
                            <span></span>
                        </div>

                        @foreach ($lines as $line)
                            @php
                                $order = $line['order'];
                                $name = $order->lot?->title ?? $order->order_number;
                                $invoice = $invoiceFor->get($order->id);
                            @endphp
                            <article class="d-lotrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                <div class="d-lotrow-lot">
                                    <a class="thumb" href="{{ route('orders.show', $order) }}" tabindex="-1" aria-hidden="true">
                                        @if ($order->lot?->media->first())
                                            <img src="{{ $order->lot->media->first()->path }}" alt="" loading="lazy">
                                        @else
                                            <x-v4.icon name="image" :size="20" stroke="1.6" />
                                        @endif
                                    </a>
                                    <div class="text">
                                        <strong><a href="{{ route('orders.show', $order) }}">{{ $name }}</a></strong>
                                        <span class="d-bid-meta">{{ $order->order_number }} · {{ $day($order->created_at) }}</span>
                                    </div>
                                </div>

                                <div class="d-lotrow-facts">
                                    <span class="cell is-value"><span class="label">Hamerbedrag</span><b class="tabular">{{ Money::format($line['hammer_cents']) }}</b></span>
                                    <span class="cell is-value"><span class="label">Fee</span><span class="tabular">- {{ Money::format($line['fee_cents']) }}</span></span>
                                    <span class="cell is-value"><span class="label">Btw {{ $vatPercent }}%</span><span class="tabular">- {{ Money::format($line['fee_vat_cents']) }}</span></span>
                                    <span class="cell is-value"><span class="label">Netto</span><b class="tabular">{{ Money::format($line['payout_cents']) }}</b></span>
                                    <span class="cell is-moment"><span class="d-tag {{ $payoutTone($order->payout?->status) }}">{{ $line['payout_status'] }}</span></span>
                                </div>

                                {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                                <div class="d-lotrow-actions">
                                    @if ($invoice)
                                        <a href="{{ route('invoices.show', $invoice) }}" class="d-iconbtn" data-tip="Bekijk factuur" aria-label="Factuur {{ $invoice->number }} bekijken"><x-v4.icon name="file-text" :size="17" stroke="2" /></a>
                                    @endif
                                    <a href="{{ route('orders.show', $order) }}" class="d-iconbtn" data-tip="Bekijk bestelling" aria-label="Bestelling {{ $name }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                </div>
                            </article>
                        @endforeach

                        <div class="d-lotrow d-lottotal">
                            <div class="d-lotrow-lot"><div class="text"><strong>Totaal</strong><span class="d-bid-meta">{{ $totals['sales'] }} {{ $totals['sales'] === 1 ? 'verkoop' : 'verkopen' }}</span></div></div>
                            <div class="d-lotrow-facts">
                                <span class="cell is-value"><span class="label">Hamerbedrag</span><b class="tabular">{{ Money::format($totals['hammer_cents']) }}</b></span>
                                <span class="cell is-value"><span class="label">Fee</span><b class="tabular">- {{ Money::format($totals['fee_cents']) }}</b></span>
                                <span class="cell is-value"><span class="label">Btw {{ $vatPercent }}%</span><b class="tabular">- {{ Money::format($totals['fee_vat_cents']) }}</b></span>
                                <span class="cell is-value"><span class="label">Netto</span><b class="tabular">{{ Money::format($totals['payout_cents']) }}</b></span>
                                <span class="cell"></span>
                            </div>
                            <div class="d-lotrow-actions"></div>
                        </div>
                    </div>
                    <x-dashboard.more :total="$lines->count()" :limit="$listLimit" />
                </div>

                {{-- Alle facturen: namens jou aan de koper, en van Aucvy voor de fee. --}}
                @if ($invoices->isNotEmpty())
                    <div id="fin-facturen" role="tabpanel" aria-labelledby="fin-tab-facturen" x-show="tab === 'facturen'" x-cloak x-data="{ all: false }">
                        <p class="d-lead">Kavelfacturen namens jou aan de koper, en de facturen van Aucvy voor de succesfee. Opslaan als pdf gaat via Afdrukken.</p>
                        <div class="d-lottable" style="--cols:minmax(0,1fr) 300px 150px 130px 44px">
                            <div class="d-lottable-head" aria-hidden="true">
                                <span>Factuur</span>
                                <span>Soort</span>
                                <span>Bestelling</span>
                                <span class="num">Bedrag</span>
                                <span></span>
                            </div>
                            @foreach ($invoices as $invoice)
                                <article class="d-lotrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                    <div class="d-lotrow-lot">
                                        <span class="thumb" aria-hidden="true"><x-v4.icon name="file-text" :size="20" stroke="1.6" /></span>
                                        <div class="text">
                                            <strong><a href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a></strong>
                                            <span class="d-bid-meta">{{ rtrim($invoice->issued_at->isoFormat('D MMM YYYY'), '.') }}</span>
                                        </div>
                                    </div>
                                    <div class="d-lotrow-facts">
                                        <span class="cell is-moment">{{ __('invoices.for.'.$invoice->kind) }}</span>
                                        <span class="cell is-moment">{{ $invoice->order->order_number }}</span>
                                        <span class="cell is-value"><span class="label">Bedrag</span><b class="tabular">{{ Money::format($invoice->total_cents) }}</b></span>
                                    </div>
                                    <div class="d-lotrow-actions">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="d-iconbtn" data-tip="Bekijk factuur" aria-label="Factuur {{ $invoice->number }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <x-dashboard.more :total="$invoices->count()" :limit="$listLimit" />
                    </div>
                @endif

                {{-- De maanden op een rij, nieuwste eerst. --}}
                <div id="fin-maanden" role="tabpanel" aria-labelledby="fin-tab-maanden" x-show="tab === 'maanden'" x-cloak x-data="{ all: false }">
                    <div class="d-lottable" style="--cols:minmax(0,1fr) 110px 150px 130px 150px">
                        <div class="d-lottable-head" aria-hidden="true">
                            <span>Maand</span>
                            <span class="num">Verkopen</span>
                            <span class="num">Hamerbedrag</span>
                            <span class="num">Fee</span>
                            <span class="num">Netto</span>
                        </div>
                        @foreach ($perMonth as $month => $sum)
                            <article class="d-lotrow" @if ($loop->index >= $monthLimit) x-show="all" x-cloak @endif>
                                <div class="d-lotrow-lot"><div class="text"><strong>{{ ucfirst(\Carbon\Carbon::createFromFormat('!Y-m', $month)->isoFormat('MMMM YYYY')) }}</strong></div></div>
                                <div class="d-lotrow-facts">
                                    <span class="cell is-value"><span class="label">Verkopen</span><b class="tabular">{{ $sum['sales'] }}</b></span>
                                    <span class="cell is-value"><span class="label">Hamerbedrag</span><b class="tabular">{{ Money::format($sum['hammer_cents']) }}</b></span>
                                    <span class="cell is-value"><span class="label">Fee</span><span class="tabular">- {{ Money::format($sum['fee_cents']) }}</span></span>
                                    <span class="cell is-value"><span class="label">Netto</span><b class="tabular">{{ Money::format($sum['payout_cents']) }}</b></span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                    <x-dashboard.more :total="$perMonth->count()" :limit="$monthLimit" />
                </div>
            </section>

            {{-- De staffel (besluit Raph en Don, 2 november 2026): omzet op Aucvy in de laatste twaalf maanden bepaalt de fee. --}}
            @if (isset($tier))
                <section class="d-card" aria-labelledby="staffel">
                    <div class="d-feecard-top">
                        <span class="d-ring" role="img" aria-label="{{ $tier['next'] ? $tierProgress.'% van de weg naar het volgende tarief' : 'Het laagste tarief' }}">
                            <svg viewBox="0 0 100 100" aria-hidden="true">
                                <circle class="track" cx="50" cy="50" r="38"></circle>
                                <circle class="value" cx="50" cy="50" r="38" pathLength="100" stroke-dasharray="{{ $tierProgress }} 100"></circle>
                            </svg>
                            <b class="d-ring-center tabular">{{ $percent($feePercentage) }}</b>
                        </span>
                        <div style="min-width:0">
                            <div class="d-titleline">
                                <h2 id="staffel" class="d-h2">Je succesfee</h2>
                                <x-dashboard.help text="Hoe meer je via Aucvy verkoopt, hoe lager je fee. Het tarief ligt vast op het moment van verkoop en wordt nooit achteraf verrekend." />
                            </div>
                            <p class="d-lead" style="margin:8px 0 0">
                                Je betaalt nu <b>{{ $percent($feePercentage) }}</b>, op basis van <b>{{ $whole($tier['revenue_cents']) }}</b> omzet op Aucvy in de laatste twaalf maanden.
                                @if ($tier['next'])
                                    Nog <b>{{ $whole($tier['next']['remaining_cents']) }}</b> tot {{ $percent($tier['next']['percent']) }}.
                                @else
                                    Dit is de laagste trede.
                                @endif
                            </p>
                        </div>
                    </div>

                    <ul class="d-feesteps" aria-label="De staffel">
                        @foreach ($tier['tiers'] as $step)
                            <li @class(['is-on' => (float) $step['percent'] === (float) $feePercentage])>
                                <span>{{ $step['up_to_cents'] === null ? 'Daarboven' : 'Tot '.$whole($step['up_to_cents']) }}</span>
                                <strong class="tabular">{{ $percent($step['percent']) }}</strong>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    @endif
</x-layouts.dashboard>
