@use('App\Support\Money\Money')

<x-layouts.admin title="Ivy" subtitle="Noodremmen per onderdeel, de kennisbank, onbekende vragen, leads en overdrachten. Geen geldacties zonder medewerker.">
    @if (session('status'))<p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>@endif
    @if ($errors->any())<div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>@endif

    <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_360px] lg:items-start">
        <div class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="kb">
                <h2 id="kb" class="text-sm font-extrabold">Kennisbank</h2>
                <p class="text-xs text-aj-muted">Ivy antwoordt alleen hieruit en uit de instellingen. Bedragen als {fee_tiers}, {admin_fee}, {bid_limit}, {deposits} worden bij het antwoorden ingevuld.</p>
                <div class="mt-2 overflow-x-auto"><table class="admin-table"><thead><tr><th>Slug</th><th>Titel</th><th>Voor</th><th>Versie</th><th>Status</th></tr></thead><tbody>
                    @foreach ($articles as $article)
                        <tr x-data="{ edit: false }"><td class="text-xs">{{ $article->slug }}</td><td><button type="button" class="font-semibold underline" @click="edit = !edit">{{ $article->title }}</button>
                            <form method="POST" action="{{ route('admin.ivy.kb', $article) }}" x-show="edit" x-cloak class="mt-2 space-y-1 text-xs">@csrf
                                <input type="hidden" name="slug" value="{{ $article->slug }}"><input type="text" name="title" value="{{ $article->title }}" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:30px">
                                <select name="audience" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:30px">@foreach (['all' => 'Iedereen', 'visitor' => 'Bezoeker', 'buyer' => 'Koper', 'seller' => 'Verkoper'] as $value => $label)<option value="{{ $value }}" @selected($article->audience === $value)>{{ $label }}</option>@endforeach</select>
                                <select name="status" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:30px"><option value="published" @selected($article->status === 'published')>Gepubliceerd</option><option value="draft" @selected($article->status === 'draft')>Concept</option></select>
                                <textarea name="body" rows="4" class="w-full rounded-aj-sm border border-aj-border-strong p-2">{{ $article->body }}</textarea>
                                <input type="url" name="source_url" value="{{ $article->source_url }}" placeholder="Bronlink" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:30px">
                                <button type="submit" class="btn btn-dark" style="height:30px;font-size:12px">Opslaan als nieuwe versie</button>
                            </form></td><td class="text-xs">{{ $article->audience }}</td><td class="tabular text-xs">v{{ $article->current_version }}</td><td class="text-xs">{{ $article->status }}</td></tr>
                    @endforeach
                </tbody></table></div>
                <details class="mt-3 text-sm"><summary class="cursor-pointer font-semibold underline">Nieuw artikel</summary>
                    <form method="POST" action="{{ route('admin.ivy.kb') }}" class="mt-2 grid gap-2 text-xs sm:grid-cols-2">@csrf
                        <input type="text" name="slug" required placeholder="slug-in-kleine-letters" pattern="[a-z0-9-]+" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:32px"><input type="text" name="title" required placeholder="Titel" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:32px">
                        <select name="audience" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:32px"><option value="all">Iedereen</option><option value="visitor">Bezoeker</option><option value="buyer">Koper</option><option value="seller">Verkoper</option></select><select name="status" class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:32px"><option value="published">Gepubliceerd</option><option value="draft">Concept</option></select>
                        <textarea name="body" rows="3" required placeholder="Tekst" class="rounded-aj-sm border border-aj-border-strong p-2 sm:col-span-2"></textarea><input type="url" name="source_url" placeholder="Bronlink (mag leeg)" class="rounded-aj-sm border border-aj-border-strong px-2 sm:col-span-2" style="min-height:32px">
                        <button type="submit" class="btn btn-dark sm:col-span-2" style="height:34px;font-size:12px">Toevoegen</button>
                    </form></details>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="gaps">
                <h2 id="gaps" class="text-sm font-extrabold">Onbekende vragen <span class="tabular text-aj-muted">{{ $gaps->count() }}</span></h2>
                <p class="text-xs text-aj-muted">Geanonimiseerd en geteld. Beantwoord ze met een artikel; de vraag verdwijnt dan uit deze lijst.</p>
                <ul class="mt-2 divide-y divide-aj-border text-sm">
                    @forelse ($gaps as $gap)
                        <li class="py-2">{{ $gap->sample_question }} <span class="text-xs text-aj-muted">· {{ $gap->count }}× · {{ $gap->role }} · laatst {{ $gap->last_seen_at->diffForHumans() }}</span></li>
                    @empty
                        <li class="py-2 text-xs text-aj-muted">Geen open vragen.</li>
                    @endforelse
                </ul>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="vlaggen">
                <h2 id="vlaggen" class="text-sm font-extrabold">Noodremmen</h2>
                <ul class="mt-2 space-y-1.5 text-sm">
                    @foreach ($flags as $key => $on)
                        <li class="flex items-center justify-between gap-2"><span>{{ $key }}</span>
                            <form method="POST" action="{{ route('admin.ivy.flag') }}">@csrf<input type="hidden" name="flag" value="{{ $key }}"><input type="hidden" name="value" value="{{ $on ? 0 : 1 }}"><button type="submit" @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-aj-success/10 text-aj-success-text' => $on, 'bg-aj-bg text-aj-muted' => ! $on])>{{ $on ? 'aan' : 'uit' }}</button></form></li>
                    @endforeach
                </ul>
                <p class="mt-2 text-[11px] text-aj-muted">Vandaag: {{ $today['runs'] }} beurten, {{ Money::format($today['cost_cents']) }}. Het dagbudget staat in de instellingen.</p>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="overdrachten">
                <h2 id="overdrachten" class="text-sm font-extrabold">Overdrachten <span class="tabular text-aj-muted">{{ $handoffs->count() }}</span></h2>
                <ul class="mt-2 divide-y divide-aj-border text-sm">
                    @forelse ($handoffs as $handoff)
                        <li class="py-2"><span class="font-semibold">{{ $handoff->reason }}</span> · {{ $handoff->conversation?->user?->name ?? 'bezoeker' }}<span class="block text-xs text-aj-muted">{{ \Illuminate\Support\Str::limit($handoff->summary, 160) }}</span>
                            <form method="POST" action="{{ route('admin.ivy.handoff', $handoff) }}" class="mt-1">@csrf<button type="submit" class="text-xs font-bold underline">Afgerond</button></form></li>
                    @empty
                        <li class="py-2 text-xs text-aj-muted">Geen open overdrachten.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="leads">
                <h2 id="leads" class="text-sm font-extrabold">Leads van de website</h2>
                <ul class="mt-2 divide-y divide-aj-border text-sm">
                    @forelse ($leads as $lead)
                        <li class="py-2">{{ $lead->name }} · {{ $lead->email }} <span class="rounded-full bg-aj-bg px-2 text-xs">{{ $lead->temperature }}</span><span class="block text-xs text-aj-muted">{{ $lead->intent }} · {{ $lead->created_at->diffForHumans() }}</span></li>
                    @empty
                        <li class="py-2 text-xs text-aj-muted">Nog geen leads.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.admin>
