@use('App\Models\IvyAlert')
@use('App\Support\Money\Money')

<x-layouts.dashboard title="Je Ivy-overzicht" eyebrow="Ivy" :intro="$count === 1 ? '1 ding wacht op jou. De rest houd ik in de gaten.' : $count.' dingen wachten op jou. De rest houd ik in de gaten.'">
    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
        <div class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="wacht">
                <h2 id="wacht" class="text-sm font-extrabold">Wacht op jou <span class="tabular rounded-full bg-aj-bg px-2 text-xs">{{ $count }}</span></h2>
                @if ($tasks->isEmpty())
                    <p class="mt-2 text-sm text-aj-muted">Niets wacht op je. Ik meld het zodra er iets te kiezen valt.</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($tasks as $alert)
                            <li class="rounded-aj-sm border border-aj-border bg-aj-bg p-4" id="alert-{{ $alert->id }}">
                                <p class="text-sm font-extrabold">{{ $alert->title() }}</p>
                                <p class="mt-1 text-sm">{{ $alert->body() }}</p>
                                @if ($alert->advice())<p class="mt-2 rounded-aj-sm border border-aj-blue/20 bg-aj-blue/5 p-2 text-xs"><span class="font-bold">Wat Ivy ervan vindt:</span> {{ $alert->advice() }} <span class="text-aj-muted">Indicatie, geen garantie.</span></p>@endif
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($alert->choices ?? [] as $choice)
                                        <form method="POST" action="{{ route('ivy.alerts.choose', [$alert, $choice['key']]) }}">@csrf<button type="submit" @class(['btn', 'btn-primary' => $choice['primary'] ?? false, 'btn-ghost' => ! ($choice['primary'] ?? false)]) style="height:36px;font-size:13px">{{ $choice['label'] }}</button></form>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-[11px] text-aj-muted">{{ $alert->updated_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}{{ $alert->expires_at ? ' · geldig tot '.$alert->expires_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') : '' }} · een bod bevestig je altijd op een scherm met het actuele bedrag.</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="gedaan">
                <h2 id="gedaan" class="text-sm font-extrabold">Ivy heeft gedaan</h2>
                <ul class="mt-3 divide-y divide-aj-border text-sm">
                    @foreach ($proposals as $proposal)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span>{{ $proposal->typeLabel() }}@if ($proposal->amount_cents) {{ Money::format($proposal->amount_cents) }}@endif · {{ $proposal->lot?->title }}<span class="block text-xs text-aj-muted">{{ __('ivy.proposal_status.'.$proposal->status) }} · {{ $proposal->updated_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span></span>
                            @if ($proposal->isOpen())<a href="{{ route('ivy.proposals.show', $proposal) }}" class="btn btn-ghost" style="height:32px;font-size:12px">Bekijk voorstel</a>@endif
                        </li>
                    @endforeach
                    @foreach ($info as $alert)
                        <li class="py-2"><span class="font-semibold">{{ $alert->title() }}</span><span class="block text-xs text-aj-muted">{{ $alert->body() }}</span>
                            <span class="mt-1 flex flex-wrap gap-2">@foreach ($alert->choices ?? [] as $choice)<form method="POST" action="{{ route('ivy.alerts.choose', [$alert, $choice['key']]) }}">@csrf<button type="submit" class="text-xs font-bold underline">{{ $choice['label'] }}</button></form>@endforeach</span>
                        </li>
                    @endforeach
                    @foreach ($done as $alert)
                        <li class="py-2 text-aj-muted">{{ $alert->title() }} <span class="text-xs">· {{ $alert->acted_choice ? 'keuze: '.$alert->acted_choice : 'afgehandeld' }}</span></li>
                    @endforeach
                    @if ($proposals->isEmpty() && $info->isEmpty() && $done->isEmpty())
                        <li class="py-2 text-aj-muted">Nog niets gedaan.</li>
                    @endif
                </ul>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="bewaakt">
                <h2 id="bewaakt" class="text-sm font-extrabold">Ivy bewaakt</h2>
                <ul class="mt-2 space-y-1.5 text-sm">
                    @foreach ($watching['modes'] as $mode)
                        <li>{{ $mode->lot?->title }}: <span class="font-semibold">{{ $mode->modeLabel() }}</span>@if ($mode->limit_cents) tot {{ Money::format($mode->limit_cents) }}@endif</li>
                    @endforeach
                    @foreach ($watching['searches'] as $search)
                        <li>Zoekvraag "{{ $search->name ?: $search->label() }}"</li>
                    @endforeach
                    <li class="text-aj-muted">{{ $watching['watchlist'] }} kavels op je volglijst</li>
                </ul>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="voorkeuren">
                <h2 id="voorkeuren" class="text-sm font-extrabold">Meldingen buiten het platform</h2>
                <p class="text-xs text-aj-muted">Per soort en per kanaal. Stil tussen {{ config('aucvy.ivy.quiet_start') }}:00 en {{ config('aucvy.ivy.quiet_end') }}:00, behalve bij kavels waarvoor je dat zelf aanzet. Service-mails zoals het betaalverzoek staan vast.</p>
                <form method="POST" action="{{ route('ivy.preferences') }}" class="mt-2 space-y-1 text-xs">
                    @csrf
                    @foreach ($preferences as $key => $channels)
                        <div class="flex items-center justify-between gap-2"><span>{{ __('ivy.alerts.'.$key.'.title') }}</span><span class="flex gap-3"><label><input type="checkbox" name="prefs[{{ $key }}][push]" value="1" @checked($channels['push'])> push</label><label><input type="checkbox" name="prefs[{{ $key }}][mail]" value="1" @checked($channels['mail'])> mail</label></span></div>
                    @endforeach
                    <button type="submit" class="btn btn-ghost mt-2" style="height:32px;font-size:12px">Opslaan</button>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
