@php
    $choice = app(\App\Support\Privacy\CookieConsent::class)->choice(request());

    // De cookies die Aucvy echt zet, uit dezelfde constanten als de code.
    $cookies = [
        ['Noodzakelijk', config('session.cookie'), 'Houdt je ingelogd en onthoudt je sessie.', config('session.lifetime').' minuten na je laatste klik'],
        ['Noodzakelijk', 'XSRF-TOKEN', 'Beschermt formulieren tegen misbruik van buitenaf.', 'Zolang de sessie loopt'],
        ['Noodzakelijk', 'remember_web_*', 'Alleen als je "ingelogd blijven" aanvinkt.', '400 dagen, of tot je uitlogt'],
        ['Noodzakelijk', \App\Lots\DraftAccess::COOKIE, 'Bewaart je concept in de verkoopwizard als je nog niet bent ingelogd.', '14 dagen'],
        ['Noodzakelijk', \App\Support\Privacy\CookieConsent::COOKIE, 'Onthoudt je keuze op deze pagina.', '12 maanden'],
        ['Statistiek', \App\Support\Experiments\HomeExperiment::COOKIE, 'Onthoudt welke versie van de homepage je ziet als we twee versies vergelijken.', config('aucvy.experiments.home.cookie_days').' dagen'],
    ];
@endphp
<x-layouts.public title="Cookies" eyebrow="Juridisch" heading="Cookieverklaring"
    intro="Welke cookies Aucvy gebruikt, waarvoor, en hoe je je keuze wijzigt.">
    <div class="mx-auto max-w-3xl space-y-8 text-sm leading-relaxed">
        @if (session('status'))
            <p class="rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-aj-success-text">{{ session('status') }}</p>
        @endif

        <section aria-labelledby="welke">
            <h2 id="welke" class="text-lg font-extrabold">Welke cookies</h2>
            <p class="mt-1 text-aj-soft">Aucvy zet alleen eigen cookies. Er draaien geen advertentie- of trackingcookies van andere partijen.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead><tr class="border-b border-aj-border"><th class="py-2 pr-3">Soort</th><th class="py-2 pr-3">Naam</th><th class="py-2 pr-3">Waarvoor</th><th class="py-2">Bewaard</th></tr></thead>
                    <tbody>
                        @foreach ($cookies as [$kind, $name, $purpose, $duration])
                            <tr class="border-b border-aj-border align-top">
                                <td class="py-2 pr-3 font-bold">{{ $kind }}</td>
                                <td class="py-2 pr-3 font-mono text-xs">{{ $name }}</td>
                                <td class="py-2 pr-3">{{ $purpose }}</td>
                                <td class="py-2 text-aj-muted">{{ $duration }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="keuze">
            <h2 id="keuze" class="text-lg font-extrabold">Jouw keuze</h2>
            <p class="mt-1 text-aj-soft">
                @if ($choice)
                    Je koos op {{ \Illuminate\Support\Carbon::parse($choice['at'])->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM YYYY') }}: statistiek {{ $choice['statistics'] ? 'aan' : 'uit' }}, marketing {{ $choice['marketing'] ? 'aan' : 'uit' }}.
                @else
                    Je hebt nog geen keuze gemaakt. Zonder keuze staan statistiek en marketing uit.
                @endif
            </p>
            <form method="POST" action="{{ route('cookies.consent') }}" class="mt-3 space-y-2">
                @csrf
                <input type="hidden" name="choice" value="custom">
                <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                    <input type="checkbox" checked disabled class="mt-0.5 size-4">
                    <span><span class="font-bold">Noodzakelijk</span><span class="block text-xs text-aj-muted">Altijd aan.</span></span>
                </label>
                <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                    <input type="checkbox" name="statistics" value="1" class="mt-0.5 size-4" @checked($choice['statistics'] ?? false)>
                    <span><span class="font-bold">Statistiek</span><span class="block text-xs text-aj-muted">Meten welke versie van een pagina beter werkt. Zonder namen of profielen.</span></span>
                </label>
                <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3">
                    <input type="checkbox" name="marketing" value="1" class="mt-0.5 size-4" @checked($choice['marketing'] ?? false)>
                    <span><span class="font-bold">Marketing</span><span class="block text-xs text-aj-muted">Nu niet in gebruik.</span></span>
                </label>
                <button type="submit" class="btn btn-primary">Keuze opslaan</button>
            </form>
        </section>
    </div>
</x-layouts.public>
