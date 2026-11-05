{{-- Instellingen, onderdeel apparaten: Waar je nu bent ingelogd, en elders uitloggen. Eigen pagina sinds het ontwerp van november. --}}
<section id="apparaten" class="d-card" aria-labelledby="sessies">
    <h2 id="sessies" class="d-h3" style="font-size:17px">Apparaten</h2>
    <p class="d-sub">Waar je nu bent ingelogd.</p>
    <div class="d-prefs" style="margin-top:8px">
        @foreach ($sessions as $session)
            <div class="d-pref">
                <span>
                    <strong>{{ $session['agent'] }} @if ($session['current'])<span class="d-tag tone-ok" style="display:inline-flex;margin-left:6px">dit apparaat</span>@endif</strong>
                    <span>{{ $session['ip'] ?? 'ip onbekend' }} · {{ $session['last_active']->timezone(config('aucvy.display_timezone'))->diffForHumans() }}</span>
                </span>
            </div>
        @endforeach
    </div>
    <form method="POST" action="{{ route('account.sessions.logout') }}" class="flex flex-wrap items-end gap-2" style="margin-top:12px">
        @csrf
        <label class="d-field" style="flex:1 1 200px;min-width:0">Andere apparaten uitloggen
            <input type="password" name="password" required autocomplete="current-password" placeholder="Je wachtwoord" class="d-input">
        </label>
        <button type="submit" class="d-btn d-btn-secondary d-btn-sq">Uitloggen elders</button>
    </form>
</section>
