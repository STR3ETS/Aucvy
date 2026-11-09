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

{{--
    De cookieverklaring (ontwerp van november). Deze tekst is echt: de lijst
    volgt uit de constanten in de code, en de keuze is hier te wijzigen.
--}}
<x-legal.page title="Cookies" first="Alleen" accent="eigen cookies."
    lead="Welke cookies Aucvy gebruikt, waarvoor, en hoe je je keuze wijzigt."
    :summary="['Noodzakelijk' => 'Altijd aan', 'Statistiek' => 'Jouw keuze', 'Marketing' => 'Niet in gebruik', 'Van andere partijen' => 'Geen']"
    :toc="['welke' => 'Welke cookies', 'keuze' => 'Jouw keuze']">

    @if (session('status'))
        <p class="d-soft is-ok">{{ session('status') }}</p>
    @endif

    <h2 id="welke">Welke cookies</h2>
    <p>Aucvy zet alleen eigen cookies. Er draaien geen advertentie- of trackingcookies van andere partijen.</p>
    <div style="overflow-x:auto">
        <table>
            <thead><tr><th>Soort</th><th>Naam</th><th>Waarvoor</th><th>Bewaard</th></tr></thead>
            <tbody>
                @foreach ($cookies as [$kind, $name, $purpose, $duration])
                    <tr>
                        <td><strong>{{ $kind }}</strong></td>
                        <td><code>{{ $name }}</code></td>
                        <td>{{ $purpose }}</td>
                        <td>{{ $duration }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 id="keuze">Jouw keuze</h2>
    <p>
        @if ($choice)
            Je koos op {{ \Illuminate\Support\Carbon::parse($choice['at'])->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM YYYY') }}: statistiek {{ $choice['statistics'] ? 'aan' : 'uit' }}, marketing {{ $choice['marketing'] ? 'aan' : 'uit' }}.
        @else
            Je hebt nog geen keuze gemaakt. Zonder keuze staan statistiek en marketing uit.
        @endif
    </p>
    <form method="POST" action="{{ route('cookies.consent') }}" class="d-cookie-choice">
        @csrf
        <input type="hidden" name="choice" value="custom">
        <label>
            <input type="checkbox" checked disabled>
            <span><strong>Noodzakelijk</strong>Altijd aan.</span>
        </label>
        <label>
            <input type="checkbox" name="statistics" value="1" @checked($choice['statistics'] ?? false)>
            <span><strong>Statistiek</strong>Meten welke versie van een pagina beter werkt. Zonder namen of profielen.</span>
        </label>
        <label>
            <input type="checkbox" name="marketing" value="1" @checked($choice['marketing'] ?? false)>
            <span><strong>Marketing</strong>Nu niet in gebruik.</span>
        </label>
        <div><button type="submit" class="d-btn d-btn-sq">Keuze opslaan</button></div>
    </form>
</x-legal.page>
