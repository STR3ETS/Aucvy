<x-layouts.admin title="Gesprek openen" :subtitle="$conversation->lot->title.' · '.$conversation->sellerLabel().' en '.$conversation->buyerLabel()">
    <p class="text-sm text-aj-muted"><a href="{{ route('admin.conversations') }}" class="d-tablelink">Gesprekken</a> · openen zonder vlag</p>

    <form method="GET" action="{{ route('admin.conversations.show', $conversation) }}" class="mt-6 max-w-xl d-card">
        <h2 class="d-h3">Waarom open je dit gesprek?</h2>
        <p class="mt-1 text-sm text-aj-muted">Dit gesprek heeft geen vlag, melding of geschil. Een reden is verplicht en komt in het auditlog; de deelnemers zien dat een medewerker meekijkt.</p>
        <label class="mt-4 block">
            <span class="text-sm font-bold">Reden</span>
            <input type="text" name="reden" required minlength="10" maxlength="300" placeholder="Bijvoorbeeld: koper belde over een niet-nagekomen afspraak" class="mt-1 w-full d-input" style="min-height:42px">
        </label>
        <button type="submit" class="d-btn d-btn-dark d-btn-sq mt-4" style="height:42px;font-size:13px">Openen en vastleggen</button>
    </form>
</x-layouts.admin>
