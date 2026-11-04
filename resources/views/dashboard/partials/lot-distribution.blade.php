{{--
    Verspreiding (briefing nieuwe functies): de deelkit per kanaal met tekst,
    beeld in drie formaten en link met UTM, en de social push met één
    planning. Alleen echte cijfers: klikken uit de UTM.
--}}
@use('App\Distribution\ShareVisual')
@use('App\Models\LotDistribution')

@php
    $kit = $kit ?? collect();
    $planning = $planning ?? collect();
    $canDistribute = $lot->status->isPublic() && $lot->auction !== null && ! $lot->auction->isClosed();
@endphp

@if (! $canDistribute)
    <p class="mt-4 rounded-aj-sm border border-dashed border-aj-border-strong p-5 text-center text-sm text-aj-muted">
        {{ $lot->auction?->isClosed() ? 'De veiling is gesloten; de advertenties zijn offline gehaald.' : 'Nog niet te verspreiden. Zodra de kavel live staat, staan hier de deelkit en de social planning klaar.' }}
    </p>
    @if ($planning->isNotEmpty() || $kit->where('status', '!=', LotDistribution::DRAFT)->isNotEmpty())
        <div class="mt-4 overflow-x-auto"><table class="admin-table"><thead><tr><th>Moment</th><th>Kanaal</th><th>Status</th><th>Klikken</th></tr></thead><tbody>
            @foreach ($planning->concat($kit->where('status', '!=', LotDistribution::DRAFT)) as $row)
                <tr><td>{{ $row->momentLabel() }}</td><td>{{ $row->channelLabel() }}</td><td>{{ $row->statusLabel() }}{{ $row->posted_at ? ' · '.$row->posted_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') : '' }}</td><td class="tabular">{{ $row->clicks }}</td></tr>
            @endforeach
        </tbody></table></div>
    @endif
@else
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-aj-muted">Beeld en link per kanaal; de tekst komt van Nova of van jou. Je plaatst zelf, tot een kanaal gekoppeld is.</p>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('dashboard.lots.distribution.ivy', $lot) }}">@csrf<button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px"><x-ui.agent-avatar agent="nova" class="size-4" /> Teksten door Nova</button></form>
            <a href="{{ route('account.connections') }}" class="btn btn-ghost" style="height:36px;font-size:13px">Kanalen koppelen</a>
        </div>
    </div>

    <section class="mt-4" aria-labelledby="beeld">
        <h3 id="beeld" class="text-sm font-extrabold">Deelbeeld</h3>
        <div class="mt-2 grid gap-3 sm:grid-cols-3">
            @foreach (ShareVisual::FORMATS as $format)
                <a href="{{ route('lots.share-image', [$lot, $format, 'download' => 1]) }}" class="rounded-aj-sm border border-aj-border bg-aj-bg p-2 text-center text-xs hover:border-aj-border-strong">
                    <img src="{{ route('lots.share-image', [$lot, $format]) }}" alt="Deelbeeld {{ __('distribution.formats.'.$format) }}" class="mx-auto max-h-40 rounded" loading="lazy">
                    <span class="mt-1 block font-semibold">{{ __('distribution.formats.'.$format) }}</span>
                    <span class="text-aj-muted">Downloaden</span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="mt-6" aria-labelledby="deelkit">
        <h3 id="deelkit" class="text-sm font-extrabold">Deelkit per kanaal</h3>
        <ul class="mt-2 space-y-3">
            @foreach ($kit as $row)
                @php $link = \App\Distribution\ShareKit::link($lot, $row->channel()); $text = $row->renderedBody(); $open = $row->channel()->openUrl($link, $text); @endphp
                <li class="rounded-aj-sm border border-aj-border bg-aj-bg p-4" x-data="{ copied: null, async copy(value, what) { try { await navigator.clipboard.writeText(value); this.copied = what; setTimeout(() => this.copied = null, 1800); } catch (e) { window.prompt('Kopieer:', value); } } }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-extrabold">{{ $row->channelLabel() }}</span>
                        <span class="text-xs text-aj-muted">{{ $row->statusLabel() }}{{ $row->posted_at ? ' · '.$row->posted_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') : '' }} · {{ $row->clicks }} {{ $row->clicks === 1 ? 'klik' : 'klikken' }}{{ $row->body_source === 'ivy' ? ' · tekst van Nova' : ($row->body_source === 'seller' ? ' · eigen tekst' : '') }}</span>
                    </div>
                    <form method="POST" action="{{ route('dashboard.lots.distribution.text', [$lot, $row]) }}" class="mt-2">
                        @csrf
                        <textarea name="body" rows="4" maxlength="{{ $row->channel()->maxLength() }}" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-2 text-sm">{{ $row->body }}</textarea>
                        <p class="mt-1 text-[11px] text-aj-muted">{bod}, {sluit} en {link} worden ingevuld met de stand van het moment van plaatsen.</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-ghost" style="height:34px;font-size:12px">Tekst opslaan</button>
                            <button type="button" class="btn btn-dark" style="height:34px;font-size:12px" @click="copy({{ Js::from($text) }}, 'tekst')"><span x-text="copied === 'tekst' ? 'Gekopieerd' : 'Kopieer tekst'"></span></button>
                            <button type="button" class="btn btn-ghost" style="height:34px;font-size:12px" @click="copy({{ Js::from($link) }}, 'link')"><span x-text="copied === 'link' ? 'Gekopieerd' : 'Kopieer link'"></span></button>
                            @if ($open)
                                <a href="{{ $open }}" target="_blank" rel="noopener" class="btn btn-ghost" style="height:34px;font-size:12px">Open {{ $row->channelLabel() }}</a>
                            @endif
                        </div>
                    </form>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @if ($row->status !== LotDistribution::POSTED)
                            <form method="POST" action="{{ route('dashboard.lots.distribution.posted', [$lot, $row]) }}" class="flex flex-wrap gap-2">
                                @csrf
                                <input type="url" name="url" placeholder="Link naar je bericht (mag leeg)" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-xs" style="min-height:34px;min-width:240px">
                                <button type="submit" class="btn btn-primary" style="height:34px;font-size:12px">Ik heb dit geplaatst</button>
                            </form>
                        @else
                            @if ($row->url)<a href="{{ $row->url }}" target="_blank" rel="noopener" class="text-xs font-semibold underline">Bekijk het bericht</a>@endif
                            <form method="POST" action="{{ route('dashboard.lots.distribution.withdrawn', [$lot, $row]) }}">@csrf<button type="submit" class="text-xs font-semibold underline">Offline gehaald</button></form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="mt-6" aria-labelledby="push">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h3 id="push" class="text-sm font-extrabold">Social push</h3>
                <p class="text-xs text-aj-muted">Drie momenten, één akkoord. Het bod wordt pas ingevuld vlak voor het plaatsen; bij sluiting gaat alles offline. Zonder koppeling staat het bericht op tijd voor je klaar.</p>
            </div>
            @if ($planning->where('status', LotDistribution::DRAFT)->isNotEmpty())
                <form method="POST" action="{{ route('dashboard.lots.distribution.approve', $lot) }}">@csrf<button type="submit" class="btn btn-primary" style="height:38px;font-size:13px">Planning goedkeuren</button></form>
            @endif
        </div>
        <div class="mt-2 overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Moment</th><th>Kanaal</th><th>Wanneer</th><th>Status</th><th>Klikken</th><th></th></tr></thead>
                <tbody>
                    @foreach ($planning as $row)
                        <tr x-data="{ edit: false }">
                            <td>{{ $row->momentLabel() }}</td>
                            <td>{{ $row->channelLabel() }}</td>
                            <td class="whitespace-nowrap text-xs">{{ $row->scheduled_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</td>
                            <td class="text-xs">{{ $row->statusLabel() }}@if ($row->last_error) <span class="block text-aj-error">{{ $row->last_error }}</span>@endif</td>
                            <td class="tabular">{{ $row->clicks }}</td>
                            <td class="text-xs">
                                @if (in_array($row->status, [LotDistribution::DRAFT, LotDistribution::SCHEDULED, LotDistribution::READY], true))
                                    <button type="button" class="font-semibold underline" @click="edit = ! edit">Tekst</button>
                                    <form method="POST" action="{{ route('dashboard.lots.distribution.withdrawn', [$lot, $row]) }}" class="inline">@csrf<button type="submit" class="ml-2 font-semibold underline">Overslaan</button></form>
                                    @if ($row->status === LotDistribution::READY)
                                        <form method="POST" action="{{ route('dashboard.lots.distribution.posted', [$lot, $row]) }}" class="inline">@csrf<button type="submit" class="ml-2 font-semibold underline">Geplaatst</button></form>
                                    @endif
                                    <form method="POST" action="{{ route('dashboard.lots.distribution.text', [$lot, $row]) }}" x-show="edit" x-cloak class="mt-2 w-72">
                                        @csrf
                                        <textarea name="body" rows="4" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-2 text-xs">{{ $row->body }}</textarea>
                                        <button type="submit" class="btn btn-ghost mt-1" style="height:30px;font-size:12px">Opslaan</button>
                                    </form>
                                @elseif ($row->url)
                                    <a href="{{ $row->url }}" target="_blank" rel="noopener" class="font-semibold underline">Bekijken</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-[11px] text-aj-muted">Bij sluiting: alle advertenties offline, geen beeld. Marktplaats staat één advertentie per kavel toe en vraagt betaald adverteren voor een link; de deelkit houdt zich daaraan.</p>
    </section>
@endif
