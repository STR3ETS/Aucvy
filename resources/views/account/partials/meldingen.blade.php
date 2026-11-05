{{-- Instellingen, onderdeel meldingen: De meldingen en mails die je zelf aan of uit zet. Eigen pagina sinds het ontwerp van november. --}}
<section id="meldingen" class="d-card" aria-labelledby="meldingen-kop">
    <h2 id="meldingen-kop" class="d-h3" style="font-size:17px">Meldingen</h2>
    <p class="d-sub">Berichten over je bestellingen, uitbetalingen en verificatie krijg je altijd.</p>
    @unless ($hasPush)
        <p class="d-hint">Pushmeldingen staan voor dit apparaat nog niet aan. Zet ze aan via de knop op een veilingpagina.</p>
    @endunless
    <form method="POST" action="{{ route('account.notifications') }}" style="margin-top:10px">
        @csrf
        @method('PATCH')
        <div class="d-prefs">
            @foreach ([
                'outbid_push' => ['Overboden: push', 'Direct op je apparaat, nog tijdens de veiling.'],
                'outbid_email' => ['Overboden: mail', 'Als er geen apparaat voor push is ingeschreven.'],
                'alerts_email' => ['Alerts: mail', 'Nieuwe kavels die aan je zoekopdracht voldoen.'],
                'watchlist_push' => ['Volglijst: push', 'Een gevolgde veiling sluit binnen een uur.'],
                'watchlist_email' => ['Volglijst: mail', 'Als er geen push is.'],
            ] as $key => [$label, $hint])
                <label class="d-pref">
                    <span><strong>{{ $label }}</strong><span>{{ $hint }}</span></span>
                    <input type="checkbox" name="{{ $key }}" value="1" @checked($preferences[$key])>
                </label>
            @endforeach
        </div>
        <button type="submit" class="d-btn d-btn-sq" style="margin-top:12px">Voorkeuren opslaan</button>
    </form>

    {{-- Toestemming voor marketingmail (Mail Builder): service blijft altijd komen. --}}
    <form method="POST" action="{{ route('account.mail-consent') }}" style="margin-top:18px;padding-top:14px;border-top:1px solid rgba(14,21,38,.08)">
        @csrf
        <h3 class="d-h3" style="font-size:14px">Mail van Aucvy</h3>
        <div class="d-prefs">
            <label class="d-pref">
                <span><strong>Herinneringen en tips van Ivy</strong><span>Een concept dat nog niet af is, een gevolgde kavel die sluit, kavels die bij je zoekvraag passen. Maximaal {{ (int) config('aucvy.mail.weekly_marketing_limit', 3) }} per week.</span></span>
                <input type="checkbox" name="activation" value="1" @checked($mailConsent['activation'])>
            </label>
            <label class="d-pref">
                <span><strong>Nieuwsbrief en campagnes</strong><span>Nieuwe kavels per week, kijkdagen en één nieuwsbrief per maand. Alleen met jouw ja.</span></span>
                <input type="checkbox" name="campaign" value="1" @checked($mailConsent['campaign'])>
            </label>
        </div>
        <p class="d-hint">Mails over je account en je aankopen (betaalverzoek, uitbetaling, geschil) zijn nodig en blijven komen.</p>
        <button type="submit" class="d-btn d-btn-secondary d-btn-sq" style="margin-top:10px">Mailvoorkeuren opslaan</button>
    </form>
</section>
