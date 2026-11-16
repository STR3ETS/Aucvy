@use('App\Support\Money\Money')

<x-layouts.admin title="Vandaag" :subtitle="$periodLabel.' · bedragen excl. btw'">

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Periode">
            @foreach (['maand' => now()->isoFormat('MMMM'), 'vorige' => now()->subMonth()->isoFormat('MMMM'), 'jaar' => 'Dit jaar'] as $key => $label)
                <a href="{{ route('admin.index', ['periode' => $key]) }}" @class(['d-chip', 'is-on' => $period === $key])>{{ ucfirst($label) }}</a>
            @endforeach
        </nav>
        @if ($role?->can('reports'))
            <a href="{{ route('admin.reports') }}" class="d-btn d-btn-dark d-btn-sq" style="height:38px;font-size:13px">Rapport maken</a>
        @endif
    </div>

    {{-- Geldcijfers: alleen Eigenaar en Financiën; de definities staan op de tegel. --}}
    @if ($money)
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($money as $tile)
                <a href="{{ $tile['url'] }}" class="d-card hover:border-aj-border-strong" title="{{ $tile['definition'] }}">
                    <p class="text-xs font-bold text-aj-muted">{{ $tile['label'] }}</p>
                    <p class="tabular mt-1 text-2xl font-extrabold">{{ $tile['value'] }}</p>
                    <p class="mt-0.5 text-xs text-aj-muted">{{ $tile['sub'] }}</p>
                    @if ($tile['trend'] !== null)
                        <p @class(['mt-1 text-xs font-bold', 'text-aj-success-text' => $tile['trend'] >= 0, 'text-aj-error' => $tile['trend'] < 0])><x-v4.icon :name="$tile['trend'] >= 0 ? 'arrow-up-right' : 'arrow-down-right'" :size="12" stroke="2.8" class="inline-block align-[-1px]" /> {{ number_format(abs($tile['trend']), 1, ',', '.') }}% t.o.v. de vorige periode</p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($work as $tile)
            <a href="{{ $tile['url'] }}" class="d-card hover:border-aj-border-strong" title="{{ $tile['definition'] }}">
                <p class="flex items-center justify-between text-xs font-bold text-aj-muted">{{ $tile['label'] }}@if ($tile['chip'])<span class="rounded-full bg-aj-warn/15 px-2 py-0.5 text-aj-warn-text">{{ $tile['chip'] }}</span>@endif</p>
                <p class="tabular mt-1 text-2xl font-extrabold">{{ $tile['value'] }}</p>
                <p class="mt-0.5 text-xs text-aj-muted">{{ $tile['sub'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_340px] lg:items-start">
        <div class="space-y-5">
            @if ($chart)
                @php $max = max(1, max(array_map(fn ($row) => max($row['revenue_cents'], $row['costs_cents']), $chart))); @endphp
                <section class="d-card" aria-labelledby="grafiek">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="grafiek" class="d-h3">Platformomzet en kosten, laatste 12 maanden</h2>
                        <span class="text-xs text-aj-muted"><span class="mr-3 inline-block size-2.5 rounded-sm bg-aj-blue"></span>Platformomzet <span class="ml-3 mr-1 inline-block size-2.5 rounded-sm bg-aj-border-strong"></span>Kosten</span>
                    </div>
                    <div class="mt-4 grid grid-cols-12 items-end gap-2" style="height:180px" role="img" aria-label="Staafdiagram van omzet en kosten per maand">
                        @foreach ($chart as $row)
                            <div class="flex h-full items-end justify-center gap-0.5" title="{{ $row['month'] }}: omzet {{ Money::format($row['revenue_cents']) }}, kosten {{ Money::format($row['costs_cents']) }}">
                                <span class="w-2.5 rounded-t bg-aj-blue" style="height: {{ (int) round($row['revenue_cents'] / $max * 100) }}%"></span>
                                <span class="w-2.5 rounded-t bg-aj-border-strong" style="height: {{ (int) round($row['costs_cents'] / $max * 100) }}%"></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-1 grid grid-cols-12 gap-2 text-center text-[11px] text-aj-muted">
                        @foreach ($chart as $row)<span>{{ $row['month'] }}</span>@endforeach
                    </div>
                    <details class="mt-3 text-sm">
                        <summary class="d-link" style="cursor:pointer">Bekijk als tabel</summary>
                        <table class="admin-table mt-2"><thead><tr><th>Maand</th><th>Omzet</th><th>Kosten</th><th>Winst</th></tr></thead><tbody>
                            @foreach ($chart as $row)<tr><td>{{ $row['month'] }}</td><td class="tabular">{{ Money::format($row['revenue_cents']) }}</td><td class="tabular">{{ Money::format($row['costs_cents']) }}</td><td class="tabular">{{ Money::format($row['revenue_cents'] - $row['costs_cents']) }}</td></tr>@endforeach
                        </tbody></table>
                    </details>
                </section>
            @endif

            <div class="grid gap-5 md:grid-cols-2">
                @php $maxBids = max(1, max(array_column($bidsPerHour, 'count'))); $totalBids = array_sum(array_column($bidsPerHour, 'count')); @endphp
                <section class="d-card" aria-labelledby="biedingen">
                    <h2 id="biedingen" class="d-h3">Biedingen op het platform</h2>
                    <p class="tabular mt-1 text-2xl font-extrabold">{{ $totalBids }} <span class="text-xs font-normal text-aj-muted">laatste 24 uur</span></p>
                    <div class="mt-3 flex h-16 items-end gap-0.5" role="img" aria-label="Biedingen per uur">
                        @foreach ($bidsPerHour as $hour)
                            <span class="flex-1 rounded-t bg-aj-blue/70" style="height: {{ max(4, (int) round($hour['count'] / $maxBids * 100)) }}%" title="{{ $hour['hour'] }}:00 uur: {{ $hour['count'] }} biedingen"></span>
                        @endforeach
                    </div>
                    @php $busiest = collect($bidsPerHour)->sortByDesc('count')->first(); @endphp
                    <p class="mt-2 text-xs text-aj-muted">{{ $totalBids > 0 ? 'Drukste moment: '.$busiest['hour'].':00 uur met '.$busiest['count'].' biedingen' : 'Nog geen biedingen in de laatste 24 uur' }}</p>
                </section>

                <section class="d-card" aria-labelledby="service">
                    <div class="flex items-baseline justify-between"><h2 id="service" class="d-h3">Klantenservice</h2>@if ($role?->can('service'))<a href="{{ route('admin.service') }}" class="d-link text-xs">Naar tickets</a>@endif</div>
                    <p class="text-xs text-aj-muted">Waar nieuwe tickets deze periode over gaan.</p>
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach ($ticketTopics as $label => $count)
                            <li class="flex items-center justify-between"><span>{{ $label }}</span><span class="tabular font-bold">{{ $count }}</span></li>
                        @endforeach
                    </ul>
                </section>
            </div>

            <section class="d-card" aria-labelledby="trechter">
                <h2 id="trechter" class="d-h3">Waar lekt het?</h2>
                <p class="text-xs text-aj-muted">Van ingediende kavel naar uitbetaling, in de periode.</p>
                <ol class="mt-3 grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($funnel as $index => $step)
                        @php $previous = $index > 0 ? $funnel[$index - 1]['value'] : null; @endphp
                        <li class="rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                            <span class="block text-xs text-aj-muted">{{ $step['label'] }}</span>
                            <span class="tabular block text-xl font-extrabold">{{ $step['value'] }}</span>
                            @if ($previous)
                                <span class="block text-xs text-aj-muted">{{ (int) round($step['value'] / $previous * 100) }}%</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($topSellers->isNotEmpty())
                <section class="d-card" aria-labelledby="verkopers">
                    <div class="flex items-baseline justify-between"><h2 id="verkopers" class="d-h3">Beste verkopers</h2><a href="{{ route('admin.customers') }}" class="d-link text-xs">Alle klanten</a></div>
                    <p class="text-xs text-aj-muted">Op omzet voor Aucvy, sinds de start.</p>
                    <ul class="mt-3 divide-y divide-aj-border text-sm">
                        @foreach ($topSellers as $seller)
                            <li class="flex items-center justify-between py-2">
                                <span>@if ($seller['user'])<a href="{{ route('admin.customers.show', $seller['user']) }}" class="d-tablelink">{{ $seller['name'] }}</a>@else{{ $seller['name'] }}@endif <span class="text-xs text-aj-muted">· {{ $seller['lots'] }} verkopen</span></span>
                                <span class="tabular font-bold">{{ Money::format($seller['revenue_cents']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section aria-labelledby="lijsten" x-data="{ tab: 'transacties' }">
                <h2 id="lijsten" class="sr-only">Lijsten</h2>
                <nav class="flex flex-wrap gap-2 text-sm" aria-label="Lijsten">
                    @foreach (['transacties' => 'Laatste transacties', 'kavels' => 'Kavels te controleren', 'klanten' => 'Nieuwe klanten', 'tickets' => 'Oudste tickets'] as $key => $label)
                        <button type="button" class="d-chip" :class="tab === '{{ $key }}' && 'is-on'" @click="tab = '{{ $key }}'">{{ $label }}</button>
                    @endforeach
                </nav>
                <div class="mt-3 overflow-x-auto d-card">
                    <table class="admin-table" x-show="tab === 'transacties'">
                        <thead><tr><th>Kavel</th><th>Bestelling</th><th>Status</th><th>Bedrag</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($latestOrders as $order)
                                <tr><td>{{ $order->lot?->title }}</td><td class="text-xs">{{ $order->order_number }} · {{ $order->seller?->company?->name ?? $order->seller?->name }} aan {{ $order->buyer?->publicName() }}</td><td>{{ $order->status->label() }}</td><td class="tabular">{{ Money::format($order->hammer_cents) }}</td><td><a href="{{ route('admin.lots.dossier', $order->lot) }}" class="d-iconbtn" data-tip="Open dossier" aria-label="Open dossier"><x-v4.icon name="file-text" :size="17" stroke="2" /></a></td></tr>
                            @empty
                                <tr><td colspan="5" class="text-aj-muted">Nog geen transacties.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <table class="admin-table" x-show="tab === 'kavels'" x-cloak>
                        <thead><tr><th>Kavel</th><th>Verkoper</th><th>Wacht</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($pendingLots as $lot)
                                <tr><td>{{ $lot->title }}</td><td class="text-xs">{{ $lot->seller?->name }}</td><td class="text-xs">{{ $lot->submitted_at?->diffForHumans(short: true) }}</td><td><a href="{{ route('admin.moderation') }}" class="d-btn d-btn-secondary d-btn-sq" style="height:30px;font-size:12px">Controleren</a></td></tr>
                            @empty
                                <tr><td colspan="4" class="text-aj-muted">Niets wacht op controle.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <table class="admin-table" x-show="tab === 'klanten'" x-cloak>
                        <thead><tr><th>Klant</th><th>Soort</th><th>Sinds</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($newCustomers as $customer)
                                <tr><td>{{ $customer->company?->name ?? $customer->name }}</td><td class="text-xs">{{ $customer->company ? 'Zakelijk' : 'Particulier' }} · {{ $customer->kyc_status->label() }}</td><td class="text-xs">{{ $customer->created_at->diffForHumans(short: true) }}</td><td>@if ($role?->can('customers'))<a href="{{ route('admin.customers.show', $customer) }}" class="d-btn d-btn-secondary d-btn-sq" style="height:30px;font-size:12px">Klantkaart</a>@endif</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <table class="admin-table" x-show="tab === 'tickets'" x-cloak>
                        <thead><tr><th>Ticket</th><th>Status</th><th>Wacht</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($oldestTickets as $ticket)
                                <tr><td>{{ $ticket->subject }} <span class="text-xs text-aj-muted">{{ $ticket->number }}</span></td><td>{{ $ticket->isLate() ? 'Te laat' : 'Open' }}</td><td class="text-xs">{{ $ticket->created_at->diffForHumans(short: true) }}</td><td>@if ($role?->can('service'))<a href="{{ route('admin.service.show', $ticket) }}" class="d-btn d-btn-secondary d-btn-sq" style="height:30px;font-size:12px">Open ticket</a>@endif</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-aj-muted">Geen open tickets.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="d-card" aria-labelledby="wacht">
                <h2 id="wacht" class="d-h3">Dit vraagt aandacht <span class="tabular rounded-full bg-aj-bg px-2 text-xs">{{ $tasks->count() + count($signals) }}</span></h2>
                <p class="text-xs text-aj-muted">Op urgentie gesorteerd. Er gaat niets uit zonder jouw akkoord.</p>
                <ul class="mt-3 space-y-2">
                    @foreach ($tasks as $task)
                        <li class="rounded-aj-sm border border-aj-border p-3 text-sm">
                            <span class="flex items-center gap-2">
                                @if ($task->agent)<x-ui.agent-avatar :agent="$task->agent" class="size-5" />@endif
                                <span class="font-bold">{{ $task->title }}</span>
                            </span>
                            <span class="mt-1 flex items-center justify-between gap-2 text-xs text-aj-muted">
                                <span>{{ $task->due_at ? 'voor '.$task->due_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') : 'geen deadline' }}{{ $task->customer ? ' · '.$task->customer->name : '' }}</span>
                                <span class="flex gap-1">
                                    @if ($task->url)<a href="{{ $task->url }}" class="d-tablelink">Bekijken</a>@endif
                                    <form method="POST" action="{{ route('admin.tasks.complete', $task) }}">@csrf<button type="submit" class="d-tablelink">Klaar</button></form>
                                </span>
                            </span>
                        </li>
                    @endforeach
                    @foreach ($signals as $signal)
                        <li>
                            <a href="{{ $signal['url'] }}" class="flex gap-2 rounded-aj-sm border border-aj-border p-3 text-sm hover:bg-aj-blue/5">
                                <span @class(['mt-1.5 size-2 flex-none rounded-full', 'bg-aj-error' => $signal['level'] === 'fail', 'bg-aj-warn' => $signal['level'] === 'warn', 'bg-aj-blue' => $signal['level'] === 'info'])></span>
                                <span><span class="font-bold">{{ $signal['title'] }}</span><span class="block text-xs text-aj-muted">{{ $signal['text'] }}</span></span>
                            </a>
                        </li>
                    @endforeach
                    @if ($tasks->isEmpty() && $signals === [])
                        <li class="text-sm text-aj-muted">Geen openstaande punten. Alles loopt.</li>
                    @endif
                </ul>
            </section>

            <section class="d-card" aria-labelledby="systemen">
                <h2 id="systemen" class="d-h3">Systemen</h2>
                <ul class="mt-2 space-y-1.5 text-sm">
                    @foreach ($systems as $check)
                        <li class="flex items-center justify-between gap-2">
                            <span>{{ $check['label'] }} <span class="block text-xs text-aj-muted">{{ \Illuminate\Support\Str::limit($check['value'], 48) }}</span></span>
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-aj-success/10 text-aj-success-text' => $check['status'] === 'ok', 'bg-aj-warn/15 text-aj-warn-text' => $check['status'] === 'warn', 'bg-aj-error/10 text-aj-error' => $check['status'] === 'fail'])>{{ ['ok' => 'Werkt', 'warn' => 'Let op', 'fail' => 'Storing'][$check['status']] }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.admin>
