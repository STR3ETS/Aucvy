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

    // Het proces in vier stappen, met de stand van de uitbetaling die nu onderweg is (anders de laatste).
    $current = $payouts->first(fn ($payout) => in_array($payout->status, [PayoutStatus::Held, PayoutStatus::Scheduled, PayoutStatus::Released], true)) ?? $payouts->first();
    // Een uitbetaling ontstaat pas bij betaling; "ingepland" wacht dus op de levering en de houdtermijn.
    $rank = match ($current?->status) {
        PayoutStatus::Scheduled, PayoutStatus::Held => 3,
        PayoutStatus::Released => 4,
        PayoutStatus::Paid => 5,
        default => 1,
    };
    $steps = [
        ['Verkocht', 'Koper heeft gewonnen'],
        ['Betaling ontvangen', 'Door Aucvy'],
        ['Houdtermijn', 'Koper controleert'],
        ['Uitbetaald', 'Naar jouw rekening'],
    ];
    $tagTone = fn (PayoutStatus $status): string => match ($status) {
        PayoutStatus::Released, PayoutStatus::Paid => 'tone-ok',
        PayoutStatus::Held => 'tone-warn',
        PayoutStatus::Failed => 'tone-live',
        default => 'tone-grey',
    };
@endphp

<x-layouts.dashboard title="Uitbetalingen" eyebrow="Dashboard" intro="Alles overzichtelijk, van verkoop tot uitbetaling, met de houdtermijn erbij.">
    <x-slot:tiles>
        <x-dashboard.tile tone="green" icon="check" label="Vrijgegeven" :value="Money::format($paid)" sub="vrijgegeven voor uitbetaling" />
        <x-dashboard.tile tone="orange" icon="clock" label="In houdtermijn" :value="Money::format((int) $pending->sum('amount_cents'))" :sub="$held->count().' '.($held->count() === 1 ? 'uitbetaling' : 'uitbetalingen').' in de houdtermijn'" />
        <x-dashboard.tile tone="blue" icon="trending-up" label="Uitbetalingen" :value="$payouts->count()" sub="in totaal" />
        <x-dashboard.tile tone="purple" icon="shopping-bag" label="Succesfee" :value="Money::format($fees)" sub="ingehouden, exclusief btw" :href="route('dashboard.finance')" />
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
        <div class="d-stack d-sections">
            <div class="d-payout-top">
                {{-- Waar de uitbetaling die nu loopt in het proces staat. --}}
                <section class="d-card" aria-labelledby="proces">
                    <h2 id="proces" class="d-h3">Uitbetalingsproces</h2>
                    @if ($current)
                        <p class="d-sub">{{ $rank >= 5 ? 'Je laatste uitbetaling' : 'Nu onderweg' }}: {{ $current->order->lot->title }}@if ($current->status === PayoutStatus::Held && $current->hold_until), vrijgave {{ $current->hold_until->timezone($displayTimezone)->isoFormat('D MMMM, HH:mm') }}@elseif ($current->status === PayoutStatus::Scheduled), de houdtermijn start na de levering @endif</p>
                    @endif
                    <ol class="d-hsteps">
                        @foreach ($steps as $index => [$label, $hint])
                            @php $step = $index + 1; @endphp
                            <li class="{{ $step < $rank ? 'is-done' : ($step === $rank ? 'is-now' : '') }}">
                                <span class="dot" aria-hidden="true">@if ($step < $rank)<x-v4.icon name="check" :size="13" stroke="3" />@endif</span>
                                <strong>{{ $label }}</strong>
                                <span>{{ $hint }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <section class="d-card" aria-labelledby="rekening">
                    <h2 id="rekening" class="d-h3">Uitbetalingsrekening</h2>
                    @if ($company?->payout_iban_last4)
                        <p class="tabular" style="margin:10px 0 0;font-size:15px;font-weight:700">NL·· ···· ···· {{ $company->payout_iban_last4 }}</p>
                        <p class="d-sub" style="margin-top:2px">{{ $company->payout_iban_name ?? $company->name }}</p>
                        <span class="d-tag tone-ok" style="margin-top:8px">Geverifieerd</span>
                        <a class="d-btn d-btn-secondary d-btn-wide d-btn-sq" style="margin-top:12px" href="{{ route('organisation.show') }}#rekening">Rekening wijzigen</a>
                    @else
                        <p class="d-sub">Er staat nog geen bankrekening bij je bedrijf. Zonder rekening kan een uitbetaling niet worden overgemaakt.</p>
                        <a class="d-btn d-btn-wide d-btn-sq" style="margin-top:12px" href="{{ route('organisation.show') }}#rekening">Rekening toevoegen</a>
                    @endif
                </section>
            </div>

            <section aria-labelledby="recent" x-data="{ all: false }">
                <h2 id="recent" class="d-h3" style="margin-bottom:10px">Recente uitbetalingen</h2>
                <div class="d-datatable">
                    <table>
                        <thead><tr><th>Kavel</th><th class="num">Verkoopprijs</th><th class="num">Succesfee</th><th class="num">Netto</th><th>Status</th><th>Datum</th><th><span class="sr-only">Openen</span></th></tr></thead>
                        <tbody>
                            @foreach ($payouts as $payout)
                                @php
                                    $moment = match (true) {
                                        $payout->status === PayoutStatus::Held && $payout->hold_until !== null => ['vrijgave', $payout->hold_until],
                                        $payout->paid_at !== null => ['betaald', $payout->paid_at],
                                        $payout->released_at !== null => ['vrijgegeven', $payout->released_at],
                                        default => ['aangemaakt', $payout->created_at],
                                    };
                                @endphp
                                <tr @if ($loop->index >= 10) x-show="all" x-cloak @endif>
                                    <td class="clip"><a href="{{ route('dashboard.payouts.show', $payout) }}" class="d-link" style="font-size:14px">{{ $payout->order->lot->title }}</a><small>Bestelling {{ $payout->order->order_number }}</small></td>
                                    <td class="num">{{ Money::format($payout->order->hammer_cents) }}</td>
                                    <td class="num">- {{ Money::format($payout->fee_cents) }}</td>
                                    <td class="num"><strong>{{ Money::format($payout->amount_cents) }}</strong></td>
                                    <td><span class="d-tag {{ $tagTone($payout->status) }}">{{ $payout->status->label() }}</span></td>
                                    <td class="nowrap">{{ $moment[1]?->timezone($displayTimezone)->isoFormat('D MMM YYYY') }}<small>{{ $moment[0] }}{{ $moment[0] === 'vrijgave' ? ' om '.$moment[1]->timezone($displayTimezone)->format('H:i') : '' }}</small></td>
                                    <td class="num"><a href="{{ route('dashboard.payouts.show', $payout) }}" class="d-link">Bekijk</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <x-dashboard.more :total="$payouts->count()" />
            </section>
        </div>
    @endif
</x-layouts.dashboard>
