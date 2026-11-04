{{--
    De biedhistorie, live aangevuld.

    Of een bod handmatig of via een ingesteld maximum kwam, tonen we bewust
    niet (meer): voor de kijker telt alleen wíé op hoeveel staat. Het veld
    is_automatic blijft wel in de payload zitten.
--}}
<div {{ $attributes }}>
    <h2 class="d-h2-page" style="margin-bottom:6px">Biedhistorie</h2>

    <template x-if="history.length === 0">
        <p class="d-note">Nog geen biedingen. Het eerste bod mag op de startprijs.</p>
    </template>

    <div class="d-rows" x-show="history.length > 0" x-cloak>
        <template x-for="entry in history.slice(0, 8)" :key="entry.sequence">
            <div class="d-row">
                <span>Bod #<span x-text="entry.sequence"></span> · <span x-text="entry.bidder_label"></span></span>
                <strong x-text="formatCents(entry.amount_cents)"></strong>
            </div>
        </template>
    </div>
</div>
