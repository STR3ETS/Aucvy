@use('App\Enums\PayoutStatus')
@use('App\Support\Money\Money')

@php
    // Vrijgegeven of uitbetaald: het platform geeft vrij, de overboeking zelf
    // loopt via de betaalprovider en wordt hier (nog) niet teruggemeld.
    $paid = (int) $payouts->whereIn('status', [PayoutStatus::Released, PayoutStatus::Paid])->sum('amount_cents');
    $pending = $payouts->whereIn('status', [PayoutStatus::Scheduled, PayoutStatus::Held]);
    $held = $payouts->where('status', PayoutStatus::Held);
    $fees = (int) $payouts->sum('fee_cents');
    $displayTimezone = config('aucvy.display_timezone');

    // In welke tabel een uitbetaling hoort: wat nog onderweg is eerst, dan wat is vrijgegeven en wat niet lukte.
    $groupOf = fn ($payout): string => match ($payout->status) {
        PayoutStatus::Scheduled, PayoutStatus::Held => 'onderweg',
        PayoutStatus::Failed => 'mislukt',
        default => 'klaar',
    };
    $grouped = $payouts->groupBy($groupOf);
    $tableSpecs = [
        'onderweg' => ['Onderweg', 'Zo loopt een uitbetaling: verkocht, betaling ontvangen door Aucvy, de houdtermijn waarin de koper controleert en daarna uitbetaald naar jouw rekening. De ring toont hoe ver een uitbetaling is.'],
        'klaar' => ['Vrijgegeven', 'Uitbetalingen die Aucvy heeft vrijgegeven. De overboeking zelf loopt via de betaalprovider.'],
        'mislukt' => ['Niet gelukt', 'Deze uitbetalingen konden niet worden overgemaakt. Controleer je uitbetalingsrekening of neem contact op.'],
    ];

    // De tegels zijn het filter (?toon=). Zonder filter staan alle tabellen onder elkaar.
    $filters = ['onderweg' => 'onderweg', 'klaar' => 'vrijgegeven'];
    $filter = array_key_exists((string) request('toon'), $filters) ? (string) request('toon') : 'alle';
    $filterUrl = fn (string $key): string => route('dashboard.payouts', $filter === $key ? [] : ['toon' => $key]);
    $tables = collect($tableSpecs)
        ->filter(fn (array $spec, string $key): bool => $filter === 'alle' || $filter === $key)
        ->map(fn (array $spec, string $key): array => ['title' => $spec[0], 'help' => $spec[1], 'payouts' => $grouped->get($key, collect())->values()])
        ->filter(fn (array $table): bool => $table['payouts']->isNotEmpty());
    // Eén tabel in beeld toont tien rijen, meerdere tabellen elk vijf.
    $listLimit = $tables->count() === 1 ? 10 : 5;
    $tagTone = fn (PayoutStatus $status): string => match ($status) {
        PayoutStatus::Released, PayoutStatus::Paid => 'tone-ok',
        PayoutStatus::Held => 'tone-warn',
        PayoutStatus::Failed => 'tone-live',
        default => 'tone-grey',
    };
    $day = fn ($at): string => rtrim($at->timezone($displayTimezone)->isoFormat('D MMM YYYY'), '.');
@endphp

{{--
    Uitbetalingen, net als Mijn kavels: per stand een eigen tabel in een eigen
    kaart, de koppen één keer en de actie als pictogram. Het proces in vier
    stappen staat niet meer als los blok bovenaan: elke uitbetaling toont met
    een ring hoe ver hij is, en de uitleg staat achter het vraagteken.
--}}
<x-layouts.dashboard title="Uitbetalingen" eyebrow="Dashboard" intro="Alles overzichtelijk, van verkoop tot uitbetaling, met de houdtermijn erbij.">
    <x-slot:tiles>
        <x-dashboard.tile tone="green" icon="check" label="Vrijgegeven" :value="Money::format($paid)" sub="vrijgegeven voor uitbetaling" :href="$filterUrl('klaar')" :active="$filter === 'klaar'" />
        <x-dashboard.tile tone="orange" icon="clock" label="In houdtermijn" :value="Money::format((int) $pending->sum('amount_cents'))" :sub="$held->count().' '.($held->count() === 1 ? 'uitbetaling' : 'uitbetalingen').' in de houdtermijn'" :href="$filterUrl('onderweg')" :active="$filter === 'onderweg'" />
        <x-dashboard.tile tone="blue" icon="trending-up" label="Uitbetalingen" :value="$payouts->count()" sub="in totaal" :href="route('dashboard.payouts')" />
        <x-dashboard.tile tone="purple" icon="shopping-bag" label="Succesfee" :value="Money::format($fees)" sub="ingehouden, exclusief btw" :href="route('dashboard.finance')" />
    </x-slot:tiles>

    <div class="d-sections d-stack">
        {{-- De rekening waar het geld heen gaat: één regel, met wijzigen als pictogram. --}}
        <section class="d-card d-payacct" aria-labelledby="rekening">
            <span class="d-payacct-icon" aria-hidden="true"><x-v4.icon name="banknote" :size="20" stroke="1.8" /></span>
            <div class="d-payacct-text">
                <div class="d-titleline">
                    <h2 id="rekening" class="d-h3">Uitbetalingsrekening</h2>
                    <x-dashboard.help text="Naar deze rekening maakt Aucvy je uitbetalingen over. Zonder rekening kan een uitbetaling niet worden overgemaakt." />
                </div>
                @if ($company?->payout_iban_last4)
                    <p><b class="tabular">NL·· ···· ···· {{ $company->payout_iban_last4 }}</b><span>{{ $company->payout_iban_name ?? $company->name }}</span><span class="d-tag tone-ok">Geverifieerd</span></p>
                @else
                    <p>Er staat nog geen bankrekening bij je bedrijf.</p>
                @endif
            </div>
            @if ($company?->payout_iban_last4)
                <a class="d-iconbtn" href="{{ route('organisation.show') }}#rekening" data-tip="Rekening wijzigen" aria-label="Uitbetalingsrekening wijzigen"><x-v4.icon name="pencil" :size="17" stroke="2" /></a>
            @else
                <a class="d-btn d-btn-sq" href="{{ route('organisation.show') }}#rekening">Rekening toevoegen</a>
            @endif
        </section>

        @if ($payouts->isEmpty())
            <x-ui.empty-state
                title="Nog geen uitbetalingen"
                body="Na een verkoop en de houdtermijn verschijnt hier wat er wanneer wordt uitbetaald.">
                <x-slot:action>
                    <x-ui.button :href="route('sell')">Kavel plaatsen</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            @if ($filter !== 'alle')
                <div class="d-listbar" style="margin:0">
                    <p class="d-listbar-count">
                        <b class="tabular">{{ $tables->sum(fn (array $table): int => $table['payouts']->count()) }}</b> {{ $filters[$filter] }}
                        <a class="d-link" href="{{ route('dashboard.payouts') }}">Alle {{ $payouts->count() }} tonen</a>
                    </p>
                </div>
            @endif

            @if ($tables->isEmpty())
                <p class="feed-empty" style="margin:0">{{ $filter === 'onderweg' ? 'Er is nu geen uitbetaling onderweg.' : 'Nog niets vrijgegeven. Na de houdtermijn staat een uitbetaling hier.' }}</p>
            @endif

            @foreach ($tables as $key => $table)
                <section class="d-card" aria-labelledby="uitbetalingen-{{ $key }}" x-data="{ all: false }">
                    <div class="d-titleline" style="margin-bottom:12px">
                        <h2 id="uitbetalingen-{{ $key }}" class="d-h2">{{ $table['title'] }}</h2>
                        <span class="d-count tabular">{{ $table['payouts']->count() }}</span>
                        <x-dashboard.help :text="$table['help']" />
                    </div>

                    <div class="d-lottable is-wide" style="--cols:minmax(0,1fr) 130px 120px 130px 270px 44px">
                        <div class="d-lottable-head" aria-hidden="true">
                            <span>Kavel</span>
                            <span class="num">Verkoopprijs</span>
                            <span class="num">Succesfee</span>
                            <span class="num">Netto</span>
                            <span>Stand</span>
                            <span></span>
                        </div>

                        @foreach ($table['payouts'] as $payout)
                            @php
                                $order = $payout->order;
                                $lot = $order->lot;
                                // Hoe ver de uitbetaling is: de ring loopt mee met de vier stappen.
                                [$ringTone, $ringPercent, $stand] = match (true) {
                                    $payout->status === PayoutStatus::Held && $payout->hold_until !== null => ['above', 75, 'In houdtermijn, vrijgave '.$payout->hold_until->timezone($displayTimezone)->isoFormat('D MMM, HH:mm')],
                                    $payout->status === PayoutStatus::Held => ['above', 75, 'In houdtermijn'],
                                    $payout->status === PayoutStatus::Scheduled => ['within', 50, 'Betaald, de houdtermijn start na de levering'],
                                    $payout->status === PayoutStatus::Failed => ['none', 100, 'Niet gelukt'],
                                    $payout->status === PayoutStatus::Paid => ['below', 100, 'Uitbetaald op '.$day($payout->paid_at ?? $payout->released_at ?? $payout->updated_at)],
                                    $payout->released_at !== null => ['below', 100, 'Vrijgegeven op '.$day($payout->released_at)],
                                    default => ['below', 100, $payout->status->label()],
                                };
                            @endphp
                            <article class="d-lotrow" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                <div class="d-lotrow-lot">
                                    <a class="thumb" href="{{ route('dashboard.payouts.show', $payout) }}" tabindex="-1" aria-hidden="true">
                                        @if ($lot->media->isNotEmpty())
                                            <img src="{{ $lot->media->first()->path }}" alt="" loading="lazy">
                                        @else
                                            <x-v4.icon name="image" :size="20" stroke="1.6" />
                                        @endif
                                    </a>
                                    <div class="text">
                                        <strong><a href="{{ route('dashboard.payouts.show', $payout) }}">{{ $lot->title }}</a></strong>
                                        <span class="d-lotrow-tags">
                                            <span class="d-tag {{ $tagTone($payout->status) }}">{{ $payout->status->label() }}</span>
                                            <span class="d-bid-meta">Bestelling {{ $order->order_number }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="d-lotrow-facts">
                                    <span class="cell is-value"><span class="label">Verkoopprijs</span><b class="tabular">{{ Money::format($order->hammer_cents) }}</b></span>
                                    <span class="cell is-value"><span class="label">Succesfee</span><span class="tabular">- {{ Money::format($payout->fee_cents) }}</span></span>
                                    <span class="cell is-value"><span class="label">Netto</span><b class="tabular">{{ Money::format($payout->amount_cents) }}</b></span>
                                    <span class="cell d-bought-verdict is-{{ $ringTone }}">
                                        <span class="d-ring is-sm is-{{ $ringTone }}" role="img" aria-label="{{ $ringPercent }}% van het uitbetalingsproces">
                                            <svg viewBox="0 0 100 100" aria-hidden="true">
                                                <circle class="track" cx="50" cy="50" r="38"></circle>
                                                <circle class="value" cx="50" cy="50" r="38" pathLength="100" stroke-dasharray="{{ $ringPercent }} 100"></circle>
                                            </svg>
                                        </span>
                                        <span>{{ $stand }}</span>
                                    </span>
                                </div>

                                {{-- De actie als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                                <div class="d-lotrow-actions">
                                    <a href="{{ route('dashboard.payouts.show', $payout) }}" class="d-iconbtn" data-tip="Bekijk uitbetaling" aria-label="Uitbetaling van {{ $lot->title }} bekijken"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <x-dashboard.more :total="$table['payouts']->count()" :limit="$listLimit" />
                </section>
            @endforeach
        @endif
    </div>
</x-layouts.dashboard>
