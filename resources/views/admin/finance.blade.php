@use('App\Support\Money\Money')

<x-layouts.admin title="Financiën" subtitle="Wat er via het platform ging, wat Aucvy daaraan verdient en wat er overblijft.">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">
            {{ session('status') }}
            @if (session('export_url')) <a href="{{ session('export_url') }}" class="font-bold underline">Download de export (csv, 24 uur geldig)</a>@endif
        </p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap gap-1.5 text-sm" aria-label="Maand">
            @foreach ($months->take(6) as $option)
                <a href="{{ route('admin.finance', ['maand' => $option->format('Y-m')]) }}" @class(['rounded-full px-3 py-1 font-bold', 'bg-aj-navy text-white' => $option->format('Y-m') === $month->format('Y-m'), 'border border-aj-border bg-aj-surface' => $option->format('Y-m') !== $month->format('Y-m')])>{{ ucfirst($option->isoFormat('MMMM YYYY')) }}</a>
            @endforeach
        </nav>
        <form method="POST" action="{{ route('admin.finance.export', ['maand' => $month->format('Y-m')]) }}">@csrf<button type="submit" class="btn btn-dark" style="height:38px;font-size:13px">Exporteren voor de boekhouding</button></form>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('admin.transactions') }}" class="rounded-aj-s border border-aj-border bg-aj-surface p-4"><p class="text-xs font-bold text-aj-muted">Omzet van transacties</p><p class="tabular mt-1 text-2xl font-extrabold">{{ Money::format($volume) }}</p><p class="text-xs text-aj-muted">{{ $transactions }} bestellingen</p></a>
        <div class="rounded-aj-s border border-aj-border bg-aj-surface p-4"><p class="text-xs font-bold text-aj-muted">Platformomzet</p><p class="tabular mt-1 text-2xl font-extrabold">{{ Money::format($revenue) }}</p><p class="text-xs text-aj-muted">{{ $volume > 0 ? 'gemiddeld '.number_format($revenue / $volume * 100, 1, ',', '.').'% van de omzet' : 'nog geen omzet' }}</p></div>
        <div class="rounded-aj-s border border-aj-border bg-aj-surface p-4"><p class="text-xs font-bold text-aj-muted">Kosten</p><p class="tabular mt-1 text-2xl font-extrabold">{{ Money::format($costsTotal) }}</p><p class="text-xs text-aj-muted">{{ $revenue > 0 ? number_format($costsTotal / $revenue * 100, 0).'% van de platformomzet' : '' }}</p></div>
        <div class="rounded-aj-s border border-aj-border bg-aj-surface p-4"><p class="text-xs font-bold text-aj-muted">Winst</p><p class="tabular mt-1 text-2xl font-extrabold">{{ Money::format($profit) }}</p><p class="text-xs text-aj-muted">{{ $revenue > 0 ? 'marge '.number_format($profit / $revenue * 100, 0).'%' : '' }}</p></div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_360px] lg:items-start">
        <div class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="winst">
                <h2 id="winst" class="text-sm font-extrabold">Van omzet naar winst</h2>
                <p class="text-xs text-aj-muted">Excl. btw. Het geld van de transacties zelf is niet van Aucvy en telt hier niet mee.</p>
                @php $scale = max(1, abs($revenue), ...array_map(fn ($line) => abs($line['cents']), [...$lines, ...$costLines])); @endphp
                <dl class="mt-3 space-y-1.5 text-sm">
                    @foreach ($lines as $line)
                        <div class="grid grid-cols-[1fr_auto] items-center gap-3"><dt>{{ $line['label'] }}<span class="ml-2 inline-block h-1.5 rounded bg-aj-blue/60 align-middle" style="width: {{ (int) round(abs($line['cents']) / $scale * 120) }}px"></span></dt><dd class="tabular">{{ $line['cents'] < 0 ? '- ' : '' }}{{ Money::format(abs($line['cents'])) }}</dd></div>
                    @endforeach
                    <div class="grid grid-cols-[1fr_auto] gap-3 border-t border-aj-border pt-1.5 font-extrabold"><dt>Platformomzet</dt><dd class="tabular">{{ Money::format($revenue) }}</dd></div>
                    @foreach ($costLines as $line)
                        <div class="grid grid-cols-[1fr_auto] items-center gap-3"><dt>{{ $line['label'] }} <span class="text-xs text-aj-muted">· {{ $line['source'] }}</span><span class="ml-2 inline-block h-1.5 rounded bg-aj-border-strong align-middle" style="width: {{ (int) round(abs($line['cents']) / $scale * 120) }}px"></span></dt><dd class="tabular">- {{ Money::format(abs($line['cents'])) }}</dd></div>
                    @endforeach
                    <div class="grid grid-cols-[1fr_auto] gap-3 border-t border-aj-border pt-1.5 font-extrabold"><dt>Winst vóór belasting</dt><dd class="tabular">{{ Money::format($profit) }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-aj-muted">Winst is een stuurcijfer, geen jaarcijfer. Betaal- en AI-kosten komen automatisch binnen; de andere posten komen uit de boekhouding.</p>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="vast">
                <h2 id="vast" class="text-sm font-extrabold">Vastgezet <span class="tabular text-aj-muted">{{ $blockedPayouts->count() }} open · {{ Money::format((int) $blockedPayouts->sum('amount_cents')) }}</span></h2>
                @if ($blockedPayouts->isEmpty())
                    <p class="mt-2 text-sm text-aj-muted">Geen vastgezette uitbetalingen.</p>
                @else
                    <ul class="mt-2 divide-y divide-aj-border text-sm">
                        @foreach ($blockedPayouts as $payout)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span><span class="font-bold">{{ $payout->seller?->company?->name ?? $payout->seller?->name }}</span> <span class="tabular">{{ Money::format($payout->amount_cents) }}</span><span class="block text-xs text-aj-muted">{{ $payout->blocked_reason ?? $payout->failure_reason ?? $payout->status->label() }}</span></span>
                                <form method="POST" action="{{ route('admin.finance.iban', $payout) }}">@csrf<button type="submit" class="btn btn-ghost" style="height:32px;font-size:12px">Klant vragen om nieuw IBAN</button></form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="klantgeld">
                <h2 id="klantgeld" class="text-sm font-extrabold">Geld van klanten</h2>
                <p class="text-xs text-aj-muted">Staat bij de betaalpartij en is geen omzet.</p>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Vastgehouden tot levering</dt><dd class="tabular font-semibold">{{ Money::format($customerMoney['held']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">In houdtermijn</dt><dd class="tabular font-semibold">{{ Money::format($customerMoney['releasable']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">In geschil</dt><dd class="tabular font-semibold">{{ Money::format($customerMoney['disputed']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Uitbetaald deze maand</dt><dd class="tabular font-semibold">{{ Money::format($customerMoney['paid_out']) }}</dd></div>
                </dl>
                <p @class(['mt-3 rounded-aj-sm p-2 text-xs', 'bg-aj-success/10 text-aj-success-text' => $reconciliation === 0, 'bg-aj-error/10 text-aj-error' => $reconciliation !== 0])>
                    {{ $reconciliation === 0 ? 'Saldo betaalpartij en administratie kloppen. Verschil € 0,00.' : 'Saldo wijkt '.Money::format(abs($reconciliation)).' af; Finn heeft er een taak van gemaakt.' }}
                </p>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="kosten">
                <h2 id="kosten" class="text-sm font-extrabold">Kostenpost boeken</h2>
                <form method="POST" action="{{ route('admin.finance.costs') }}" class="mt-2 space-y-2 text-sm">
                    @csrf
                    <select name="category" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px">
                        @foreach ($costCategories as $category)@continue($category === 'payment')<option value="{{ $category }}">{{ __('admin.costs.'.$category) }}</option>@endforeach
                    </select>
                    <input type="number" name="amount" step="0.01" min="0" required placeholder="Bedrag in euro, excl. btw" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px">
                    <input type="month" name="period" value="{{ $month->format('Y-m') }}" required class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px">
                    <input type="text" name="note" maxlength="300" placeholder="Toelichting (mag leeg)" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px">
                    <button type="submit" class="btn btn-dark w-full justify-center" style="height:38px;font-size:13px">Boeken</button>
                </form>
            </section>
        </aside>
    </div>

    <p class="mt-5 text-xs text-aj-muted">Alleen eigenaar en financiën zien dit scherm.</p>
</x-layouts.admin>
