@use('App\Enums\NotificationChannel')

@php
    $displayTimezone = config('aucvy.display_timezone');
    $newTotal = collect($matches)->sum('new');
@endphp

<x-layouts.dashboard title="Alerts" eyebrow="Dashboard" intro="Ivy houdt je zoekopdrachten in de gaten en laat het weten als er iets nieuws bij past.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Nieuwe alert</a>
    </x-slot:actions>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif
    @error('query')
        <p class="d-soft is-err" style="margin:0 0 14px">{{ $message }}</p>
    @enderror

    @if ($searches->isEmpty())
        <x-ui.empty-state title="Nog geen alerts"
            body="Zoek op de veilingen met de filters die je interesseren en kies daar Alert instellen. Vanaf dan hoor je het als er iets nieuws bij past.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="d-cols">
            <div class="d-col-main">
                @if ($newTotal > 0)
                    <div class="d-callout-info" style="align-items:center">
                        <span class="mark" aria-hidden="true" style="width:40px;height:40px">i</span>
                        <div style="flex:1 1 240px;min-width:0">
                            <strong style="margin:0">{{ $newTotal === 1 ? 'Eén nieuwe match voor je zoekopdrachten' : $newTotal.' nieuwe matches voor je zoekopdrachten' }}</strong>
                            <span style="color:#1e56e0">Bekijk ze voordat de eerste veiling sluit.</span>
                        </div>
                        <span class="d-muted" style="flex:0 0 auto">laatste 7 dagen</span>
                    </div>
                @endif

                <h2 class="d-h3" style="margin-top:4px">Je alerts</h2>

                @foreach ($searches as $search)
                    @php
                        $found = $matches[$search->id] ?? ['new' => 0, 'total' => 0];
                        $paused = $search->paused_at !== null;
                        $url = route('lots.index', $search->query ?? []);
                    @endphp
                    <article class="d-card d-alert{{ $paused ? ' is-paused' : '' }}" x-data="{ rename: false }">
                        <div class="d-alert-head">
                            <div style="min-width:0">
                                <strong>{{ $search->label() }}</strong>
                                <span class="d-muted">{{ $search->summary() }} · {{ $search->channel === NotificationChannel::Push ? 'push' : 'per mail' }}</span>
                            </div>
                            {{-- Aan of uit: het vinkje verstuurt het formulier meteen. --}}
                            <form method="POST" action="{{ route('dashboard.alerts.update', $search) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="paused" value="{{ $paused ? 0 : 1 }}">
                                <label class="d-alert-toggle">
                                    <span>{{ $paused ? 'Uit' : 'Aan' }}</span>
                                    <input type="checkbox" @checked(! $paused) onchange="this.form.submit()" aria-label="{{ $search->label() }} {{ $paused ? 'aanzetten' : 'pauzeren' }}">
                                </label>
                                <noscript><button type="submit" class="d-link">{{ $paused ? 'Hervatten' : 'Pauzeren' }}</button></noscript>
                            </form>
                        </div>

                        <div class="d-alert-foot">
                            <div class="d-chips" style="gap:6px">
                                @if ($paused)
                                    <span class="d-tag tone-grey">Gepauzeerd</span>
                                @elseif ($found['new'] > 0)
                                    <span class="d-tag tone-atlas">{{ $found['new'] === 1 ? '1 nieuwe match' : $found['new'].' nieuwe matches' }}</span>
                                @else
                                    <span class="d-tag tone-grey">Geen nieuwe matches</span>
                                @endif
                                <span class="d-muted">{{ $found['total'] === 1 ? '1 kavel past nu' : $found['total'].' kavels passen nu' }} · {{ $search->last_run_at ? 'gecontroleerd '.$search->last_run_at->timezone($displayTimezone)->diffForHumans() : 'nog niet gecontroleerd' }}</span>
                            </div>
                            <a class="d-btn d-btn-secondary d-btn-sq" href="{{ $url }}">{{ $found['new'] > 1 ? 'Bekijk matches' : ($found['new'] === 1 ? 'Bekijk match' : ($found['total'] > 0 ? 'Bekijk kavels' : 'Zoekvraag verfijnen')) }}</a>
                        </div>

                        <div class="d-alert-actions">
                            <button type="button" class="d-iconbtn" data-tip="Naam wijzigen" aria-label="Naam van deze alert wijzigen" @click="rename = ! rename"><x-v4.icon name="pencil" :size="17" stroke="2" /></button>
                            <form method="POST" action="{{ route('dashboard.alerts.destroy', $search) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="d-iconbtn is-danger" data-tip="Verwijderen" aria-label="Deze alert verwijderen"><x-v4.icon name="trash" :size="17" stroke="2" /></button>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('dashboard.alerts.update', $search) }}" x-show="rename" x-cloak class="d-chips" style="margin-top:10px">
                            @csrf
                            @method('PATCH')
                            <label class="sr-only" for="name-{{ $search->id }}">Naam van de alert</label>
                            <input id="name-{{ $search->id }}" type="text" name="name" value="{{ $search->name }}" maxlength="80" placeholder="Bijvoorbeeld: heftrucks onder 10.000"
                                   class="d-input" style="margin:0;flex:1 1 220px;width:auto">
                            <button type="submit" class="d-btn d-btn-sq">Opslaan</button>
                        </form>
                    </article>
                @endforeach
            </div>

            <aside class="d-col-aside" style="border:0;background:none;display:flex;flex-direction:column;gap:14px">
                <div class="d-callout-info" style="flex-direction:column;gap:10px">
                    <div style="display:flex;gap:12px;align-items:center">
                        <span class="mark" aria-hidden="true">i</span>
                        <strong style="margin:0">Zo werkt een alert</strong>
                    </div>
                    <ol class="d-numsteps">
                        <li><span>1</span>Zoek op de veilingen met de filters die je interesseren.</li>
                        <li><span>2</span>Kies daar Alert instellen.</li>
                        <li><span>3</span>Vanaf dan hoor je het als er iets nieuws bij past. We kijken elk uur.</li>
                    </ol>
                </div>

                <section class="d-soft" style="padding:16px" aria-labelledby="horen">
                    <h2 id="horen" class="d-h3" style="font-size:15px">Hoe wil je het horen?</h2>
                    <p class="d-sub">Alerts komen in je meldingen en per mail. Dat stel je in bij je instellingen.</p>
                    <a class="d-link" style="display:inline-block;margin-top:8px;text-decoration:underline" href="{{ route('account.settings.section', 'meldingen') }}">Naar instellingen</a>
                </section>
            </aside>
        </div>
    @endif
</x-layouts.dashboard>
