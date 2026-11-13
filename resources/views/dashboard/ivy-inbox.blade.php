@use('App\Support\Money\Money')

@php
    $doneCount = $proposals->count() + $info->count() + $done->count();
    $watchCount = $watching['modes']->count() + $watching['searches']->count() + $watching['watchlist'];
    $tz = config('aucvy.display_timezone');
    $preferenceLabels = collect($preferences)->keys()->mapWithKeys(fn (string $key) => [$key => __('ivy.alerts.'.$key.'.title')]);
@endphp

{{--
    Het Ivy-overzicht (K1), net als de andere dashboardpagina's: drie tegels,
    dan wat op jou wacht, wat Ivy deed en wat ze bewaakt, elk in een eigen
    kaart. Een keuze in een alert opent nooit zelf een bod: "Verhoog" wordt
    een voorstel met het bevestigingsscherm.
--}}
<x-layouts.dashboard title="Je Ivy-overzicht" eyebrow="Ivy" :intro="$count === 0 ? 'Niets wacht op je. De rest houd ik in de gaten.' : ($count === 1 ? '1 ding wacht op jou. De rest houd ik in de gaten.' : $count.' dingen wachten op jou. De rest houd ik in de gaten.')">
    <x-slot:actions>
        <button type="button" class="btn btn-white" onclick="window.dispatchEvent(new CustomEvent('ivy-open'))">Vraag Ivy</button>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="orange" label="Wacht op jou" :value="$count" :sub="$count === 0 ? 'niets te kiezen' : 'jouw keuze nodig'" href="#wacht" />
        <x-dashboard.tile tone="blue" label="Ivy heeft gedaan" :value="$doneCount" sub="voorstellen en meldingen" href="#gedaan" />
        <x-dashboard.tile tone="purple" label="Ivy bewaakt" :value="$watchCount" sub="zoekvragen, kavels en je volglijst" href="#bewaakt" />
    </x-slot:tiles>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 var(--d-gap)">{{ session('status') }}</p>
    @endif

    <div class="d-ivygrid">
        <div class="d-stack">
            {{-- Wacht op jou: de keuzes die Ivy niet zelf maakt. --}}
            <section class="d-card" id="wacht" aria-labelledby="wacht-titel">
                <div class="d-titleline">
                    <h2 id="wacht-titel" class="d-h2">Wacht op jou</h2>
                    <span class="d-count tabular">{{ $count }}</span>
                    <x-dashboard.help text="Wat Ivy niet zelf beslist. Een bod bevestig je altijd op een scherm met het actuele bedrag; Ivy biedt nooit zonder jou." />
                </div>

                @if ($tasks->isEmpty())
                    <div class="d-ivyempty">
                        <img src="{{ asset('images/agents/ivy.jpg') }}" alt="">
                        <span><strong>Niets wacht op je.</strong> Ik meld het zodra er iets te kiezen valt, zoals een overbod of een bod onder je reserve.</span>
                    </div>
                @else
                    <ul class="d-ivylist">
                        @foreach ($tasks as $alert)
                            <li class="d-ivyrow is-task" id="alert-{{ $alert->id }}">
                                <img class="avatar" src="{{ asset('images/agents/ivy.jpg') }}" alt="">
                                <div class="body">
                                    <strong>{{ $alert->title() }}</strong>
                                    <span>{{ $alert->body() }}</span>
                                    @if ($alert->advice())
                                        <p class="advice"><b>Wat Ivy ervan vindt:</b> {{ $alert->advice() }} <span class="d-muted">Indicatie, geen garantie.</span></p>
                                    @endif
                                    <div class="choices">
                                        @foreach ($alert->choices ?? [] as $choice)
                                            <form method="POST" action="{{ route('ivy.alerts.choose', [$alert, $choice['key']]) }}">
                                                @csrf
                                                <button type="submit" @class(['d-btn d-btn-sm d-btn-sq', 'd-btn-secondary' => ! ($choice['primary'] ?? false)])>{{ $choice['label'] }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                    <small class="tabular">{{ $alert->updated_at->timezone($tz)->isoFormat('D MMM, HH:mm') }}{{ $alert->expires_at ? ' · geldig tot '.$alert->expires_at->timezone($tz)->isoFormat('D MMM, HH:mm') : '' }}</small>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Ivy heeft gedaan: voorstellen die ze klaarzette, en meldingen die al afgehandeld zijn. --}}
            <section class="d-card" id="gedaan" aria-labelledby="gedaan-titel">
                <div class="d-titleline">
                    <h2 id="gedaan-titel" class="d-h2">Ivy heeft gedaan</h2>
                    <span class="d-count tabular">{{ $doneCount }}</span>
                    <x-dashboard.help text="Voorstellen die Ivy voor je klaarzette en meldingen die afgehandeld zijn. Een open voorstel bekijk je en bevestig je zelf." />
                </div>

                @if ($doneCount === 0)
                    <p class="d-muted" style="margin:0">Nog niets gedaan.</p>
                @else
                    <ul class="d-ivylist">
                        @foreach ($proposals as $proposal)
                            <li class="d-ivyrow">
                                <span class="thumb" aria-hidden="true">
                                    @if ($proposal->lot?->media->first())
                                        <img src="{{ $proposal->lot->media->first()->path }}" alt="">
                                    @else
                                        <x-v4.icon name="gavel" :size="18" stroke="2" />
                                    @endif
                                </span>
                                <div class="body">
                                    <strong>{{ $proposal->typeLabel() }}@if ($proposal->amount_cents) <span class="tabular">{{ Money::format($proposal->amount_cents) }}</span>@endif</strong>
                                    <span>{{ $proposal->lot?->title }}</span>
                                </div>
                                <span @class(['d-tag', 'tone-atlas' => $proposal->isOpen(), 'tone-grey' => ! $proposal->isOpen()])>{{ ucfirst(__('ivy.proposal_status.'.$proposal->status)) }}</span>
                                <small class="when tabular">{{ $proposal->updated_at->timezone($tz)->isoFormat('D MMM, HH:mm') }}</small>
                                <span class="actions">
                                    @if ($proposal->isOpen())
                                        <a href="{{ route('ivy.proposals.show', $proposal) }}" class="d-iconbtn is-dark" data-tip="Bekijk voorstel" aria-label="Bekijk voorstel"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                        @foreach ($info as $alert)
                            <li class="d-ivyrow">
                                <span class="thumb" aria-hidden="true"><x-v4.icon name="bell" :size="18" stroke="2" /></span>
                                <div class="body">
                                    <strong>{{ $alert->title() }}</strong>
                                    <span>{{ $alert->body() }}</span>
                                    @if (! empty($alert->choices))
                                        <div class="choices">
                                            @foreach ($alert->choices as $choice)
                                                <form method="POST" action="{{ route('ivy.alerts.choose', [$alert, $choice['key']]) }}">@csrf<button type="submit" class="d-link">{{ $choice['label'] }}</button></form>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                                <span class="d-tag tone-atlas">Melding</span>
                                <small class="when tabular">{{ $alert->updated_at->timezone($tz)->isoFormat('D MMM, HH:mm') }}</small>
                                <span class="actions"></span>
                            </li>
                        @endforeach
                        @foreach ($done as $alert)
                            <li class="d-ivyrow is-done">
                                <span class="thumb" aria-hidden="true"><x-v4.icon name="check" :size="18" stroke="2.4" /></span>
                                <div class="body">
                                    <strong>{{ $alert->title() }}</strong>
                                    <span>{{ $alert->acted_choice ? 'Jouw keuze: '.$alert->acted_choice : 'Afgehandeld' }}</span>
                                </div>
                                <span class="d-tag tone-ok">Klaar</span>
                                <small class="when tabular">{{ $alert->updated_at->timezone($tz)->isoFormat('D MMM, HH:mm') }}</small>
                                <span class="actions"></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <aside class="d-stack">
            {{-- Ivy bewaakt: zoekvragen, kavels waarvoor ze een opdracht heeft, en de volglijst. --}}
            <section class="d-card" id="bewaakt" aria-labelledby="bewaakt-titel">
                <div class="d-titleline">
                    <h2 id="bewaakt-titel" class="d-h2">Ivy bewaakt</h2>
                    <x-dashboard.help class="is-end" text="Wat Ivy voor je in de gaten houdt. Komt er iets bij dat past, dan hoor je het." />
                </div>
                <ul class="d-watchlist">
                    @foreach ($watching['modes'] as $mode)
                        <li>
                            <span class="icon" aria-hidden="true"><x-v4.icon name="gavel" :size="16" stroke="2" /></span>
                            <span><strong>{{ $mode->lot?->title }}</strong><small>{{ $mode->modeLabel() }}@if ($mode->limit_cents) tot <span class="tabular">{{ Money::format($mode->limit_cents) }}</span>@endif</small></span>
                        </li>
                    @endforeach
                    @foreach ($watching['searches'] as $search)
                        <li>
                            <span class="icon" aria-hidden="true"><x-v4.icon name="search" :size="16" stroke="2" /></span>
                            <span><strong>{{ $search->name ?: $search->label() }}</strong><small>Zoekvraag</small></span>
                        </li>
                    @endforeach
                    <li>
                        <span class="icon" aria-hidden="true"><x-v4.icon name="heart" :size="16" stroke="2" /></span>
                        <span><strong><span class="tabular">{{ $watching['watchlist'] }}</span> {{ $watching['watchlist'] === 1 ? 'kavel' : 'kavels' }} op je volglijst</strong><small>Je hoort het als er een bijna sluit</small></span>
                    </li>
                </ul>
                <div class="d-watchlist-links">
                    <a class="d-link" href="{{ route('dashboard.alerts') }}">Zoekvragen beheren</a>
                    <a class="d-link" href="{{ route('dashboard.watchlist') }}">Naar je volglijst</a>
                </div>
            </section>

            {{-- Meldingen buiten het platform: per soort en per kanaal. --}}
            <section class="d-card" aria-labelledby="voorkeuren-titel">
                <div class="d-titleline">
                    <h2 id="voorkeuren-titel" class="d-h2">Meldingen</h2>
                    <x-dashboard.help class="is-end" text="Per soort en per kanaal. Stil tussen {{ config('aucvy.ivy.quiet_start') }}:00 en {{ config('aucvy.ivy.quiet_end') }}:00, behalve bij kavels waarvoor je dat zelf aanzet. Service-mails zoals het betaalverzoek staan altijd aan." />
                </div>
                <form method="POST" action="{{ route('ivy.preferences') }}">
                    @csrf
                    <div class="d-prefs">
                        <span class="head" aria-hidden="true"></span><span class="head">Push</span><span class="head">Mail</span>
                        @foreach ($preferences as $key => $channels)
                            <span class="name">{{ $preferenceLabels[$key] }}</span>
                            <label class="d-switch"><input type="checkbox" name="prefs[{{ $key }}][push]" value="1" @checked($channels['push']) aria-label="{{ $preferenceLabels[$key] }} via push"><i aria-hidden="true"></i></label>
                            <label class="d-switch"><input type="checkbox" name="prefs[{{ $key }}][mail]" value="1" @checked($channels['mail']) aria-label="{{ $preferenceLabels[$key] }} via mail"><i aria-hidden="true"></i></label>
                        @endforeach
                    </div>
                    <button type="submit" class="d-btn d-btn-secondary d-btn-sm d-btn-sq" style="margin-top:14px">Opslaan</button>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
