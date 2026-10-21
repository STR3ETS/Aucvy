@use('App\Enums\LotStatus')
@use('App\Support\Money\Money')

<x-layouts.dashboard :title="$lot->title" eyebrow="Mijn kavels" :intro="$lot->public_id.' · '.$lot->status->label().($lot->auction ? ' · stand '.Money::format($lot->currentPriceCents()) : '')">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.lots') }}">Mijn kavels</a> <span aria-hidden="true">/</span> <span>{{ $lot->public_id }}</span>
    </x-slot:crumbs>

    <x-slot:actions>
        @if ($lot->status->isPublic())
            <a class="btn btn-white" href="{{ route('lots.show', $lot) }}">Bekijk op de site</a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_300px] lg:items-start">

        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="bewerken">
            <h2 id="bewerken" class="text-lg font-extrabold">Bewerken</h2>
            @unless ($editable)
                <p class="mt-1 text-sm text-aj-muted">{{ __('lots.manage.not_editable') }}</p>
            @else
                <p class="mt-1 text-sm text-aj-muted">Omschrijving, kenmerken en logistiek kun je altijd aanpassen. {{ $titleLocked ? 'De titel staat vast omdat er al op geboden is.' : 'De titel kan nog, zolang er niet geboden is.' }}</p>

                <form method="POST" action="{{ route('dashboard.lots.update', $lot) }}" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')

                    <label class="block">
                        <span class="text-sm font-bold">Titel</span>
                        <input type="text" name="title" value="{{ old('title', $lot->title) }}" maxlength="150" @disabled($titleLocked)
                               class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm disabled:bg-aj-bg disabled:text-aj-muted" style="min-height:var(--cta-h)">
                    </label>

                    <label class="block">
                        <span class="text-sm font-bold">Omschrijving</span>
                        <textarea name="description" rows="7" class="mt-1 w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm">{{ old('description', $lot->description) }}</textarea>
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block">
                            <span class="text-sm font-bold">Conditie</span>
                            <select name="condition" class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                                <option value="">Kies de conditie</option>
                                @foreach ($conditions as $condition)
                                    <option value="{{ $condition->value }}" @selected(old('condition', $lot->condition?->value) === $condition->value)>{{ $condition->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold">Aantal</span>
                            <input type="number" name="quantity" value="{{ old('quantity', $lot->quantity) }}" min="1" max="100000" required
                                   class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold">Merk</span>
                            <input type="text" name="brand" value="{{ old('brand', $lot->brand) }}" maxlength="80" class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold">Model of type</span>
                            <input type="text" name="model" value="{{ old('model', $lot->model) }}" maxlength="80" class="mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold">Bouwjaar</span>
                            <input type="number" name="year" value="{{ old('year', $lot->year) }}" min="1900" max="{{ now()->year }}" class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold">Transport vanaf (€)</span>
                            <span class="block text-xs text-aj-muted">Leeg laten als je alleen ophalen aanbiedt.</span>
                            <input type="text" inputmode="decimal" name="transport_from" value="{{ old('transport_from', $lot->transport_from_cents !== null ? number_format($lot->transport_from_cents / 100, 2, ',', '') : '') }}"
                                   class="tabular mt-1 w-full rounded-aj-sm border border-aj-border-strong px-3 text-sm" style="min-height:var(--cta-h)">
                        </label>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                            <input type="checkbox" name="pickup_only" value="1" class="mt-0.5 size-4" @checked(old('pickup_only', $lot->pickup_only))>
                            <span><span class="font-bold">Alleen ophalen</span><span class="block text-xs text-aj-muted">De koper regelt het vervoer zelf.</span></span>
                        </label>
                        <label class="flex items-start gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                            <input type="checkbox" name="viewing_available" value="1" class="mt-0.5 size-4" @checked(old('viewing_available', $lot->viewing_available))>
                            <span><span class="font-bold">Bezichtiging mogelijk</span><span class="block text-xs text-aj-muted">Kopers kunnen een kijkmoment voorstellen.</span></span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary">Wijzigingen opslaan</button>
                </form>
            @endunless
        </section>

        <aside class="space-y-4">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="status">
                <h2 id="status" class="text-sm font-extrabold">Status</h2>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Status</dt><dd class="font-semibold">{{ $lot->status->label() }}</dd></div>
                    @if ($lot->auction)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Biedingen</dt><dd class="tabular font-semibold">{{ $lot->auction->bid_count }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Sluit</dt><dd class="font-semibold">{{ $lot->auction->isClosed() ? 'gesloten' : $lot->auction->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</dd></div>
                        @if ($lot->auction->paused_at)
                            <div class="flex justify-between gap-3"><dt class="text-aj-muted">Gepauzeerd sinds</dt><dd class="font-semibold">{{ $lot->auction->paused_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</dd></div>
                        @endif
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Compleetheid</dt><dd class="tabular font-semibold">{{ $lot->completeness_score }}%</dd></div>
                </dl>
                @if ($lot->status === LotStatus::Cancelled && $lot->moderation_note)
                    <p class="mt-3 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-xs text-aj-error">Offline gehaald door moderatie: {{ $lot->moderation_note }}</p>
                @endif
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="acties">
                <h2 id="acties" class="text-sm font-extrabold">Acties</h2>
                <div class="mt-3 space-y-2">
                    @if (in_array($lot->status, [LotStatus::Scheduled, LotStatus::Live], true) && ($lot->auction?->bid_count ?? 0) === 0)
                        <form method="POST" action="{{ route('dashboard.lots.pause', $lot) }}">
                            @csrf
                            <button type="submit" class="btn btn-ghost w-full justify-center"><x-v4.icon name="pause" :size="15" stroke="2.2" /> Pauzeren</button>
                        </form>
                        <p class="text-xs text-aj-muted">De veiling staat stil en is even niet te zien. De resterende tijd loopt bij hervatten verder.</p>
                    @elseif (in_array($lot->status, [LotStatus::Live, LotStatus::Closing], true))
                        <p class="text-xs text-aj-muted">Pauzeren kan niet meer: er is al geboden.</p>
                    @endif

                    @if ($lot->status === LotStatus::Paused)
                        <form method="POST" action="{{ route('dashboard.lots.resume', $lot) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-full justify-center"><x-v4.icon name="play" :size="15" stroke="2.2" /> Hervatten</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('dashboard.lots.duplicate', $lot) }}">
                        @csrf
                        @if (in_array($lot->status, [LotStatus::Unsold, LotStatus::Cancelled], true))
                            <input type="hidden" name="relist" value="1">
                            <button type="submit" class="btn btn-primary w-full justify-center">Opnieuw plaatsen</button>
                            <p class="mt-1 text-xs text-aj-muted">Als nieuw concept met dezelfde inhoud en instellingen; deze kavel blijft als historie staan.</p>
                        @else
                            <button type="submit" class="btn btn-ghost w-full justify-center">Dupliceren</button>
                            <p class="mt-1 text-xs text-aj-muted">Een nieuw concept met dezelfde inhoud, foto's en documenten.</p>
                        @endif
                    </form>
                </div>
            </section>

            @if ($lot->status->isPublic())
                {{-- Delen (briefing stap 10): link met herkomst per kanaal, tekst om te plakken. --}}
                <x-lots.share :lot="$lot" class="rounded-aj-s border border-aj-border bg-aj-surface p-5" />
            @endif

            @if ($editable)
                {{-- Foto's: toevoegen, verwijderen, omslag kiezen. Zelfde endpoints als de wizard. --}}
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Foto's"
                         x-data="lotMedia({{ $lot->id }}, {{ Js::from($lot->media->sortBy('position')->values()->map(fn ($item) => ['id' => $item->id, 'url' => $item->path, 'position' => (int) $item->position])) }})">
                    <h2 class="text-sm font-extrabold">Foto's</h2>
                    <p class="mt-1 text-xs text-aj-muted">De eerste is de omslag. Verwijderen kan altijd; voeg eerst een nieuwe toe als je de laatste vervangt.</p>
                    <ul class="photo-grid photo-grid-sm mt-3" x-show="media.length" x-cloak>
                        <template x-for="(item, index) in media" :key="item.id">
                            <li class="photo" :class="index === 0 && 'is-cover'">
                                <img :src="item.url" alt="">
                                <span class="cover-tag" x-show="index === 0">Omslag</span>
                                <button type="button" class="photo-cover" x-show="index !== 0" @click="cover(item)">Als omslag</button>
                                <button type="button" class="photo-remove" @click="remove(item)" aria-label="Foto verwijderen"><x-v4.icon name="x" :size="14" stroke="2.4" /></button>
                            </li>
                        </template>
                    </ul>
                    <div class="dropzone dropzone-sm mt-3" :class="over && 'is-over'"
                         @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; add([...$event.dataTransfer.files])">
                        <label class="btn btn-ghost" style="height:38px;font-size:13px">
                            <x-v4.icon name="upload" :size="15" stroke="2.2" /> Foto's toevoegen
                            <input type="file" accept="image/*" multiple class="sr-only" @change="add([...$event.target.files]); $event.target.value = ''">
                        </label>
                    </div>
                    <ul class="upload-list" x-show="uploads.length" x-cloak>
                        <template x-for="(upload, index) in uploads" :key="index">
                            <li :class="upload.error ? 'is-error' : (upload.pct === 100 ? 'is-done' : '')">
                                <span class="name" x-text="upload.name"></span>
                                <span class="bar" x-show="! upload.error"><i :style="'width:' + upload.pct + '%'"></i></span>
                                <span class="msg" x-text="upload.error ?? (upload.pct === 100 ? 'Klaar' : upload.pct + '%')"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-2 text-xs text-aj-error" x-show="error" x-text="error" x-cloak></p>
                </section>
            @elseif ($lot->media->isNotEmpty())
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Foto's">
                    <h2 class="text-sm font-extrabold">Foto's</h2>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        @foreach ($lot->media->sortBy('position')->take(6) as $item)
                            <img src="{{ $item->path }}" alt="" class="aspect-square w-full rounded-aj-sm object-cover" loading="lazy">
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($lot->status->isPublic())
                {{-- Engelse vertaling door Ivy (briefing stap 4). --}}
                @php $english = $lot->translation('en'); @endphp
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Engelse vertaling">
                    <h2 class="text-sm font-extrabold">Engelse vertaling</h2>
                    @if ($english)
                        <p class="mt-1 text-xs text-aj-muted">Door Ivy, {{ \Carbon\Carbon::parse($english['translated_at'])->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}. Kopers lezen hem via "Read in English" op de kavelpagina; de Nederlandse tekst blijft bindend.</p>
                        <p class="mt-2 text-sm font-bold" lang="en">{{ $english['title'] }}</p>
                        <p class="mt-1 line-clamp-4 whitespace-pre-line text-xs text-aj-muted" lang="en">{{ $english['description'] }}</p>
                    @else
                        <p class="mt-1 text-xs text-aj-muted">Nog geen vertaling die bij de huidige tekst hoort. Ivy maakt hem bij publicatie en na een tekstwijziging; dat duurt meestal een minuut.</p>
                    @endif
                    <form method="POST" action="{{ route('dashboard.lots.translate', $lot) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px">{{ $english ? 'Vertaling vernieuwen' : 'Nu vertalen' }}</button>
                    </form>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
