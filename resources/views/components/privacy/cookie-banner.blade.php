@unless (app(\App\Support\Privacy\CookieConsent::class)->decided(request()) || request()->routeIs('legal.cookies'))
    {{-- Cookiebanner (besluit Raph, 2 november 2026). Weigeren is even makkelijk
         als accepteren; zonder JavaScript werken de knoppen als gewoon formulier. --}}
    <div x-data="{
            open: true,
            details: false,
            statistics: false,
            marketing: false,
            async save(choice) {
                const body = new FormData(this.$refs.form);
                body.set('choice', choice);
                const response = await fetch(@js(route('cookies.consent')), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body,
                });
                if (response.ok) {
                    const result = await response.json();
                    this.open = false;
                    window.dispatchEvent(new CustomEvent('aucvy:consent', { detail: result }));
                }
            },
         }"
         x-show="open"
         role="dialog" aria-modal="false" aria-labelledby="cookie-banner-title"
         class="fixed inset-x-0 bottom-0 z-50 p-3 sm:p-4" style="padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 12px)">
        <form method="POST" action="{{ route('cookies.consent') }}" x-ref="form" @submit.prevent="save($event.submitter?.value ?? 'custom')"
              class="mx-auto max-w-3xl d-card text-sm shadow-lg">
            @csrf
            <h2 id="cookie-banner-title" class="text-base font-extrabold">Cookies op Aucvy</h2>
            <p class="mt-1 text-aj-soft">
                We gebruiken noodzakelijke cookies om in te loggen, te bieden en je concept te bewaren. Met je toestemming meten we ook hoe de site gebruikt wordt.
                <a href="{{ route('legal.cookies') }}" class="font-semibold underline">Welke cookies</a>
            </p>

            <div x-show="details" x-cloak class="mt-3 space-y-2">
                <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                    <input type="checkbox" checked disabled class="mt-0.5 size-4">
                    <span><span class="font-bold">Noodzakelijk</span><span class="block text-xs text-aj-muted">Altijd aan: inloggen, beveiliging, je concept in de wizard.</span></span>
                </label>
                <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                    <input type="checkbox" name="statistics" value="1" class="mt-0.5 size-4" x-model="statistics">
                    <span><span class="font-bold">Statistiek</span><span class="block text-xs text-aj-muted">Meten welke versie van een pagina beter werkt. Zonder namen of profielen.</span></span>
                </label>
                <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                    <input type="checkbox" name="marketing" value="1" class="mt-0.5 size-4" x-model="marketing">
                    <span><span class="font-bold">Marketing</span><span class="block text-xs text-aj-muted">Advertenties afstemmen op je bezoek. Nu niet in gebruik.</span></span>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="submit" name="choice" value="all" class="btn btn-primary">Alles accepteren</button>
                <button type="submit" name="choice" value="necessary" class="btn btn-ghost">Alleen noodzakelijk</button>
                <button type="button" class="btn btn-ghost" x-show="! details" @click="details = true">Instellen</button>
                <button type="submit" name="choice" value="custom" class="btn btn-ghost" x-show="details" x-cloak>Keuze opslaan</button>
            </div>
        </form>
    </div>
@endunless
