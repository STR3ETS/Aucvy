@use('App\Models\IvyActionProposal')
@use('App\Support\Money\Money')

<x-layouts.dashboard title="Controleer het even" eyebrow="Ivy" intro="Een bod is bindend. Bekijk het actuele bedrag en alle kosten, en bevestig dan zelf.">
    @if (session('status'))<p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>@endif
    @if (session('warning'))<p class="mb-4 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-3 text-sm">{{ session('warning') }}</p>@endif

    <div class="mx-auto max-w-xl d-card"
         x-data="{ state: {{ Js::from($proposal->status) }}, current: {{ Js::from($current_cents) }}, endsAt: {{ Js::from($ends_at?->toIso8601String()) }}, busy: false, message: null, key: {{ Js::from($idempotencyKey) }},
            async confirm() {
                if (this.busy) return; this.busy = true; this.message = null;
                try {
                    const response = await fetch({{ Js::from(route('ivy.proposals.confirm', $proposal)) }}, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' }, body: JSON.stringify({ expected_current_cents: this.current, expected_ends_at: this.endsAt, idempotency_key: this.key }) });
                    const data = await response.json();
                    this.state = data.state; this.message = data.message;
                    if (response.status === 409) { this.current = data.current_cents; }
                    if (data.state === 'executed') { setTimeout(() => window.location.href = {{ Js::from($lot ? route('lots.show', $lot) : route('ivy.inbox')) }}, 1500); }
                } catch (e) { this.message = 'Bevestigen lukte niet. Probeer het opnieuw.'; } finally { this.busy = false; }
            } }">
        <p class="text-xs font-bold uppercase tracking-wide text-aj-muted">{{ $proposal->typeLabel() }}</p>
        <h2 class="mt-1 text-lg font-extrabold">{{ $lot->title }}</h2>
        <p class="text-xs text-aj-muted">Kavel {{ $lot->public_id }}</p>

        @if ($closed)
            <p class="mt-4 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">De veiling is gesloten. Dit voorstel kan niet meer worden uitgevoerd.</p>
        @else
            <div class="mt-4 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-3 text-sm" x-show="state === 'price_changed'" x-cloak>
                Het bod is net veranderd. Het staat nu op <strong x-text="current !== null ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(current / 100) : ''"></strong>.
                @if ($still_valid) Jouw {{ $proposal->amount_cents ? Money::format($proposal->amount_cents) : 'voorstel' }} blijft geldig; bevestig opnieuw. @else Je bod is nu te laag; Ivy zet een nieuw voorstel klaar met de eerstvolgende stap, nooit automatisch hoger. @endif
            </div>
            @if ($price_changed && $proposal->status === IvyActionProposal::PRICE_CHANGED)
                <p class="mt-4 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-3 text-sm">Het bod is net veranderd. Het staat nu op <strong>{{ Money::format((int) $current_cents) }}</strong>. {{ $still_valid ? 'Jouw voorstel blijft geldig; bevestig opnieuw.' : 'Je bod is nu te laag; vraag Ivy om een nieuw voorstel.' }}</p>
            @endif

            <dl class="mt-4 space-y-1.5 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Bod nu</dt><dd class="tabular font-semibold" x-text="current !== null ? new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(current / 100) : '-'"></dd></div>
                @if ($minimum_cents !== null)<div class="flex justify-between gap-3"><dt class="text-aj-muted">Minimaal volgend bod</dt><dd class="tabular font-semibold">{{ Money::format($minimum_cents) }}</dd></div>@endif
                @if ($proposal->amount_cents !== null)<div class="flex justify-between gap-3 border-t border-aj-border pt-1.5"><dt class="font-bold">{{ $proposal->type === IvyActionProposal::AUTO_BID ? 'Jouw limiet' : 'Jouw bod' }}</dt><dd class="tabular text-lg font-extrabold">{{ Money::format($proposal->amount_cents) }}</dd></div>@endif
                @if ($ends_at)<div class="flex justify-between gap-3"><dt class="text-aj-muted">Sluit</dt><dd class="font-semibold">{{ $ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</dd></div>@endif
            </dl>

            @if ($costs)
                <p class="mt-4 text-xs font-bold uppercase tracking-wide text-aj-muted">Kosten bij winst</p>
                <dl class="mt-1 space-y-1 text-sm">
                    @foreach ($costs->lines() as $line)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">{{ $line->label }}</dt><dd class="tabular">{{ Money::format($line->amountCents) }}</dd></div>
                    @endforeach
                    <div class="flex justify-between gap-3 border-t border-aj-border pt-1 font-extrabold"><dt>Totaal</dt><dd class="tabular">{{ Money::format($costs->totalCents) }}</dd></div>
                </dl>
            @endif

            @if ($proposal->type === IvyActionProposal::AUTO_BID)
                <p class="mt-4 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">Bevestig: automatisch bieden tot {{ Money::format((int) $proposal->amount_cents) }}. Elk bod is bindend. Ik bied alleen als je overboden bent, in de kleinste stap. Je kunt dit altijd stoppen. Mandaat {{ \App\Ivy\Chat\LotModes::CONSENT_VERSION }}.</p>
            @else
                <p class="mt-4 text-sm text-aj-muted">Een bod is bindend. Verandert de prijs voordat je bevestigt, dan zie je dat eerst.</p>
            @endif

            <p class="mt-3 rounded-aj-sm p-2 text-sm" x-show="message" x-text="message" x-cloak :class="state === 'executed' ? 'bg-aj-success/10 text-aj-success-text' : 'bg-aj-warn/10'"></p>

            <div class="mt-4 flex flex-wrap gap-2" x-show="state !== 'executed' && state !== 'expired' && state !== 'rejected' && state !== 'cancelled'">
                <button type="button" class="btn btn-primary" style="height:44px;font-size:14px" @click="confirm()" :disabled="busy">
                    <span x-text="state === 'price_changed' ? 'Opnieuw bevestigen' : 'Ja, bevestig'"></span>
                </button>
                <form method="POST" action="{{ route('ivy.proposals.reject', $proposal) }}">@csrf<button type="submit" class="btn btn-ghost" style="height:44px;font-size:14px">Laat gaan</button></form>
            </div>
            <p class="mt-3 text-xs text-aj-muted" x-show="state === 'executed'" x-cloak>Gedaan. Ik zet het in je inbox en het logboek.</p>
        @endif
    </div>
</x-layouts.dashboard>
