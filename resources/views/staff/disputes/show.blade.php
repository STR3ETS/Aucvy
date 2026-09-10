<x-layouts.public :title="'Geschil · '.$dispute->order->order_number">
    <div class="mx-auto max-w-3xl">
        <p class="text-sm text-aj-muted">
            <a href="{{ route('staff.disputes.index') }}" class="underline">Geschillen</a>
            · {{ $dispute->order->order_number }}
        </p>
        <h1 class="mt-1 text-2xl font-extrabold tracking-tight">{{ $dispute->order->lot->title }}</h1>

        <dl class="mt-6 space-y-4">
            <div class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <dt class="text-xs font-extrabold text-aj-soft">
                    Melding van {{ $dispute->reporter->publicName() }}
                    - {{ __('orders.dispute_reasons.'.$dispute->reason) }}
                </dt>
                <dd class="mt-1 text-sm">{{ $dispute->description ?? 'Geen toelichting.' }}</dd>
            </div>

            <div class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <dt class="text-xs font-extrabold text-aj-soft">Reactie verkoper</dt>
                <dd class="mt-1 text-sm">
                    @if ($dispute->seller_response !== null)
                        {{ $dispute->seller_response }}
                    @else
                        <span class="text-aj-muted">
                            Nog geen reactie - termijn loopt tot
                            {{ $dispute->sla_due_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm') ?? '-' }}.
                        </span>
                    @endif
                </dd>
            </div>

            <div class="rounded-aj-sm border border-aj-border bg-aj-surface p-4">
                <dt class="text-xs font-extrabold text-aj-soft">De order</dt>
                <dd class="mt-1 text-sm">
                    {{ \App\Support\Money\Money::format($dispute->order->total_cents) }}
                    · {{ $dispute->order->status->label() }}
                    · uitbetaling: {{ $dispute->order->payout?->status->label() ?? 'nog niet aangemaakt' }}
                </dd>
            </div>
        </dl>

        {{-- De uitkomst, altijd met motivering - die gaat het auditlog in. --}}
        <form method="POST" action="{{ route('staff.disputes.resolve', $dispute) }}"
              class="mt-6 space-y-4 rounded-aj-s border border-aj-border bg-aj-surface p-6">
            @csrf
            <h2 class="text-lg font-extrabold">Uitkomst</h2>

            @if ($errors->any())
                <div role="alert" class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
                    {{ $errors->first() }}
                </div>
            @endif

            <label class="block sm:max-w-xs">
                <span class="text-sm font-bold">Beslissing</span>
                <select name="resolution" required
                        class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm"
                        style="min-height:var(--cta-h)">
                    <option value="alsnog_leveren">Alsnog leveren - order afronden</option>
                    <option value="prijscorrectie">Prijscorrectie - order afronden</option>
                    <option value="terugdraaien">Terugdraaien - koop ontbinden</option>
                    <option value="afgewezen">Melding afwijzen - order afronden</option>
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-bold">Motivering</span>
                <textarea name="notes" rows="4" required maxlength="4000"
                          class="mt-1 w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm"></textarea>
            </label>

            <button type="submit" class="btn btn-primary">Uitkomst vastleggen</button>
        </form>
    </div>
</x-layouts.public>
