{{--
    De biedhistorie, live aangevuld.

    Of een bod handmatig of via een ingesteld maximum kwam, tonen we bewust
    niet (meer): voor de kijker telt alleen wíé op hoeveel staat. Het veld
    is_automatic blijft wel in de payload zitten.
--}}
<div {{ $attributes->merge(['class' => 'rounded-aj-sm border border-aj-border-strong bg-aj-surface p-4']) }}>
    <p class="text-sm font-extrabold">Biedhistorie</p>

    <template x-if="history.length === 0">
        <p class="mt-3 text-sm text-aj-muted">
            Nog geen biedingen. Het eerste bod mag op de startprijs.
        </p>
    </template>

    <ol class="mt-3 space-y-1.5" x-show="history.length > 0" x-cloak>
        <template x-for="entry in history.slice(0, 8)" :key="entry.sequence">
            <li class="flex items-baseline justify-between gap-3 text-sm">
                <span class="tabular font-semibold" x-text="formatCents(entry.amount_cents)"></span>
                <span class="truncate text-aj-muted" x-text="entry.bidder_label"></span>
            </li>
        </template>
    </ol>
</div>
