@use('App\Support\Money\Money')

<x-layouts.admin title="Moderatie" :subtitle="$lots->total().' '.($lots->total() === 1 ? 'kavel wacht' : 'kavels wachten').' op beoordeling · vandaag '.$reviewedToday.' beoordeeld'">
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif
    {{-- Vooraf modereren (besluit Raph, 2 november 2026): wat Ivy niet zelf
         kon goedkeuren. Goedkeuren zet de kavel live, afwijzen haalt hem weg. --}}
    <section class="mt-6" aria-labelledby="in-beoordeling">
        <h2 id="in-beoordeling" class="text-lg font-extrabold">Wacht op akkoord vóór publicatie</h2>
        @if ($pending->isEmpty())
            <p class="mt-3 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-5 text-center text-sm text-aj-muted">Niets in beoordeling.</p>
        @else
            <ul class="mt-3 space-y-4">
                @foreach ($pending as $lot)
                    <li class="rounded-aj-s border border-aj-warn/40 bg-aj-surface p-5" x-data="{ reject: false }">
                        <div class="flex flex-wrap gap-5">
                            <span class="block h-28 w-40 flex-none overflow-hidden rounded-aj-sm border border-aj-border bg-aj-navy">
                                @if ($lot->media->isNotEmpty())
                                    <img src="{{ $lot->media->first()->path }}" alt="" class="h-full w-full object-cover">
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold uppercase tracking-wide text-aj-muted">{{ $lot->category?->name ?? 'Zonder categorie' }} · {{ $lot->quantity }} {{ config('listings.unit_options.'.$lot->unit, $lot->unit) }}</p>
                                <h3 class="mt-1 text-lg font-extrabold"><a href="{{ route('admin.lots.show', $lot) }}" class="hover:underline">{{ $lot->title }}</a></h3>
                                <p class="mt-1 text-sm text-aj-muted">
                                    Door {{ $lot->seller?->name }}{{ $lot->company ? ' · '.$lot->company->name : '' }}
                                    · ingediend {{ $lot->submitted_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}
                                    · garantie: {{ config('listings.warranty_options.'.($lot->draft_meta['warranty'] ?? ''), 'onbekend') }}
                                </p>
                                <ul class="mt-3 space-y-1 text-sm">
                                    @foreach ((array) ($lot->screening['issues'] ?? []) as $issue)
                                        <li class="text-aj-warn-text">{{ $issue['message'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                                @if ($lot->description)
                                    <p class="mt-3 line-clamp-3 text-sm text-aj-soft">{{ $lot->description }}</p>
                                @endif
                            </div>
                            <div class="flex w-full flex-col gap-2 sm:w-auto">
                                <form method="POST" action="{{ route('admin.moderation.approve', $lot) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-full" style="height:40px;font-size:13px">Goedkeuren en live zetten</button>
                                </form>
                                <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px;color:var(--color-aj-error)" @click="reject = ! reject" :aria-expanded="reject">Afwijzen</button>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('admin.moderation.reject', $lot) }}" x-show="reject" x-cloak class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-4">
                            @csrf
                            <label for="pending-reason-{{ $lot->id }}" class="block text-sm font-bold">Reden voor de verkoper</label>
                            <textarea id="pending-reason-{{ $lot->id }}" name="reason" rows="3" required minlength="10" maxlength="500" class="mt-2 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm"></textarea>
                            <button type="submit" class="btn btn-dark mt-3" style="height:40px;font-size:13px">Kavel afwijzen</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <h2 class="mt-10 text-lg font-extrabold">Online, nog niet nagekeken</h2>
    @if ($lots->isEmpty())
        <p class="mt-6 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center text-sm text-aj-muted">
            De wachtrij is leeg. Elke nieuwe publicatie komt hier één keer langs.
        </p>
    @else
        <ul class="mt-6 space-y-4">
            @foreach ($lots as $lot)
                <li class="rounded-aj-s border border-aj-border bg-aj-surface p-5" x-data="{ reject: false }">
                    <div class="flex flex-wrap gap-5">
                        <a href="{{ route('admin.lots.show', $lot) }}" class="block h-28 w-40 flex-none overflow-hidden rounded-aj-sm border border-aj-border bg-aj-navy">
                            @if ($lot->media->isNotEmpty())
                                <img src="{{ $lot->media->first()->path }}" alt="" class="h-full w-full object-cover">
                            @endif
                        </a>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold uppercase tracking-wide text-aj-muted">{{ $lot->category?->name ?? 'Zonder categorie' }} · {{ $lot->sale_type?->label() }}</p>
                            <h2 class="mt-1 text-lg font-extrabold">
                                <a href="{{ route('admin.lots.show', $lot) }}" class="hover:underline">{{ $lot->title }}</a>
                            </h2>
                            <p class="mt-1 text-sm text-aj-muted">
                                Door <a href="{{ route('admin.users.show', $lot->seller) }}" class="underline">{{ $lot->seller->name }}</a>
                                @if ($lot->company) · {{ $lot->company->name }} ({{ $lot->company->isVerified() ? 'KvK gecontroleerd' : 'KvK niet gecontroleerd' }}) @endif
                                · geplaatst {{ $lot->published_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}
                            </p>
                            <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                <div><dt class="inline text-aj-muted">Stand</dt> <dd class="tabular inline font-semibold">{{ $lot->auction ? Money::format($lot->currentPriceCents()) : '-' }}</dd></div>
                                <div><dt class="inline text-aj-muted">Foto's</dt> <dd class="tabular inline font-semibold">{{ $lot->media->count() }}</dd></div>
                                <div><dt class="inline text-aj-muted">Documenten</dt> <dd class="tabular inline font-semibold">{{ $lot->documents->count() }}</dd></div>
                                <div><dt class="inline text-aj-muted">Compleetheid</dt> <dd class="tabular inline font-semibold">{{ $lot->completeness_score }}%</dd></div>
                                <div><dt class="inline text-aj-muted">Conditie</dt> <dd class="inline font-semibold">{{ $lot->condition?->label() ?? '-' }}</dd></div>
                            </dl>
                            @if ($lot->description)
                                <p class="mt-3 line-clamp-2 text-sm text-aj-soft">{{ $lot->description }}</p>
                            @endif
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto">
                            <a href="{{ route('lots.show', $lot) }}" target="_blank" rel="noopener" class="btn btn-ghost" style="height:40px;font-size:13px">Bekijk op de site</a>
                            <form method="POST" action="{{ route('admin.moderation.approve', $lot) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full" style="height:40px;font-size:13px">Goedkeuren</button>
                            </form>
                            <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px;color:var(--color-aj-error)"
                                    @click="reject = ! reject" :aria-expanded="reject">Afwijzen</button>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.moderation.reject', $lot) }}" x-show="reject" x-cloak
                          class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-4">
                        @csrf
                        <label for="reason-{{ $lot->id }}" class="block text-sm font-bold">Reden voor de verkoper</label>
                        <p class="text-xs text-aj-muted">Gaat per mail naar de verkoper. De kavel gaat offline; een lopende veiling sluit zonder winnaar.</p>
                        <textarea id="reason-{{ $lot->id }}" name="reason" rows="3" required minlength="10" maxlength="500"
                                  class="mt-2 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm"
                                  placeholder="Bijvoorbeeld: eigendomsbewijs ontbreekt, of de foto's tonen een ander object dan de titel."></textarea>
                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Kavel afwijzen</button>
                            <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="reject = false">Annuleren</button>
                        </div>
                    </form>
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $lots->links() }}</div>
    @endif
</x-layouts.admin>
