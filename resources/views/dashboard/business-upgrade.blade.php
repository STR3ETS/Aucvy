{{--
    Zakelijk worden met hetzelfde account: bedrijfsnaam en KvK-nummer, daarna
    de KvK-controle. Kopen, biedingen, bestellingen en de volglijst blijven
    gewoon staan.
--}}
<x-layouts.dashboard title="Zakelijk verkopen" eyebrow="Account" intro="Verkopen op Aucvy kan alleen als bedrijf. Je maakt je eigen account zakelijk; kopen en bieden blijft gewoon werken.">
    <div class="d-upgrade">
        <section class="d-card">
            <div class="d-titleline" style="margin-bottom:6px">
                <h2 class="d-h2">Je bedrijf</h2>
                <x-dashboard.help text="Na het opslaan controleren we de KvK-gegevens en de tenaamstelling (KYB). Pas daarna kun je publiceren. Teamleden nodig je later uit bij Instellingen, Team." />
            </div>
            <p class="d-lead">Eén account voor alles: wat je kocht, je biedingen en je volglijst blijven staan.</p>

            <form method="POST" action="{{ route('account.business.store') }}" class="d-stack" style="margin-top:16px">
                @csrf
                <label class="d-field">Bedrijfsnaam
                    <input name="company_name" type="text" required maxlength="120" autocomplete="organization" value="{{ old('company_name') }}" class="d-input">
                    @error('company_name')<span class="d-hint is-warn">{{ $message }}</span>@enderror
                </label>
                <label class="d-field">KvK-nummer
                    <input name="kvk_number" type="text" required maxlength="20" inputmode="numeric" value="{{ old('kvk_number') }}" class="d-input">
                    <span class="d-hint">De controle op tenaamstelling volgt in de volgende stap.</span>
                    @error('kvk_number')<span class="d-hint is-warn">{{ $message }}</span>@enderror
                </label>
                <div><button type="submit" class="d-btn d-btn-sq">Maak mijn account zakelijk</button></div>
            </form>
        </section>

        <aside class="d-card">
            <h2 class="d-h3">Wat er verandert</h2>
            <ul class="d-upgrade-list">
                <li><x-v4.icon name="check" :size="16" stroke="2.4" /><span>Je kunt kavels plaatsen en veilingen bundelen.</span></li>
                <li><x-v4.icon name="check" :size="16" stroke="2.4" /><span>Kopen en bieden blijft werken, met hetzelfde account.</span></li>
                <li><x-v4.icon name="check" :size="16" stroke="2.4" /><span>Uitbetalingen, facturen en een team komen erbij.</span></li>
                <li><x-v4.icon name="shield-check" :size="16" stroke="2" /><span>Publiceren kan na de KvK-controle.</span></li>
            </ul>
        </aside>
    </div>
</x-layouts.dashboard>
