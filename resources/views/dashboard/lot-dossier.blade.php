@use('App\Enums\LotStatus')
@use('App\Models\LotActivity')
@use('App\Support\Money\Money')

<x-layouts.dashboard :title="$lot->title" eyebrow="Mijn kavels" :intro="$lot->public_id.' · '.$lot->status->label().($lot->auction ? ' · stand '.Money::format($lot->currentPriceCents()) : '')">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.lots') }}">Mijn kavels</a> <span aria-hidden="true">/</span> <span>{{ $lot->public_id }}</span>
    </x-slot:crumbs>

    <x-slot:actions>
        @if ($lot->status->isPublic())
            <a class="btn btn-white" href="{{ route('lots.show', $lot) }}">Bekijk op de site</a>
        @endif
        <a class="btn btn-outline-white" href="{{ route('dashboard.lots.edit', $lot) }}">Bewerken</a>
    </x-slot:actions>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif

    {{-- Tabs (briefing nieuwe functies): Overzicht, Berichten, Documenten, Verspreiding, Dossier. --}}
    <nav class="mb-5 flex flex-wrap gap-2 text-sm" aria-label="Onderdelen van deze kavel">
        @foreach (\App\Http\Controllers\LotDossierController::TABS as $name)
            @php
                $badge = match ($name) {
                    'berichten' => $counts->get('message', 0),
                    'documenten' => $lot->documents->count(),
                    'verspreiding' => $counts->get('distribution', 0),
                    default => null,
                };
            @endphp
            <a href="{{ route('dashboard.lots.show', [$lot, 'tab' => $name]) }}"
               @class(['rounded-full px-3 py-1.5 font-bold', 'bg-aj-navy text-white' => $tab === $name, 'border border-aj-border bg-aj-surface hover:border-aj-border-strong' => $tab !== $name])
               @if ($tab === $name) aria-current="page" @endif>
                {{ __('dossier.tabs.'.$name) }}@if ($badge) <span class="tabular ml-1 rounded-full bg-aj-bg px-1.5 text-xs text-aj-text">{{ $badge }}</span>@endif
            </a>
        @endforeach
    </nav>

    @if ($tab === 'overzicht')
        <div class="grid gap-5 lg:grid-cols-[1fr_300px] lg:items-start">
            <div class="space-y-5">
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="laatste">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="laatste" class="text-lg font-extrabold">Laatste gebeurtenissen</h2>
                        <a href="{{ route('dashboard.lots.show', [$lot, 'tab' => 'dossier']) }}" class="text-sm font-semibold underline">Hele dossier</a>
                    </div>
                    <x-dossier.timeline :activities="$activities" class="mt-4" empty="Nog niets gebeurd met deze kavel." />
                </section>
            </div>

            <aside class="space-y-4">
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="status">
                    <h2 id="status" class="text-sm font-extrabold">Status</h2>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Status</dt><dd class="font-semibold">{{ $lot->status->label() }}</dd></div>
                        @if ($lot->auction)
                            <div class="flex justify-between gap-3"><dt class="text-aj-muted">Biedingen</dt><dd class="tabular font-semibold">{{ $lot->auction->bid_count }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-aj-muted">Sluit</dt><dd class="font-semibold">{{ $lot->auction->isClosed() ? 'gesloten' : $lot->auction->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Documenten</dt><dd class="tabular font-semibold">{{ $lot->documents->count() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Bezichtigingen</dt><dd class="tabular font-semibold">{{ $lot->viewings->count() }}</dd></div>
                        @if ($lot->order)
                            <div class="flex justify-between gap-3"><dt class="text-aj-muted">Order</dt><dd class="font-semibold"><a href="{{ route('orders.show', $lot->order) }}" class="underline">{{ $lot->order->order_number }}</a></dd></div>
                        @endif
                    </dl>
                    @if ($lot->status === LotStatus::PendingReview)
                        <p class="mt-3 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-3 text-xs">Ivy controleert de kavel; een medewerker kijkt mee als dat nodig is. Je krijgt bericht zodra hij live staat.</p>
                    @endif
                    @if ($lot->status === LotStatus::Draft && ! empty($lot->screening['issues']))
                        <div class="mt-3 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-3 text-xs">
                            <p class="font-bold">Nog aan te passen voor publicatie:</p>
                            <ul class="mt-1 list-disc pl-4">
                                @foreach ($lot->screening['issues'] as $issue)<li>{{ $issue['message'] ?? '' }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    @elseif ($tab === 'berichten')
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="berichten">
            <h2 id="berichten" class="text-lg font-extrabold">Berichten over deze kavel</h2>
            <p class="mt-1 text-sm text-aj-muted">Korte vragen van kopers en gesprekken na een bod of verkoop. Alles blijft in het dossier.</p>
            @include('dashboard.partials.lot-conversations', ['lot' => $lot])
        </section>
    @elseif ($tab === 'documenten')
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="documenten">
            <h2 id="documenten" class="text-lg font-extrabold">Documenten</h2>
            <p class="mt-1 text-sm text-aj-muted">Facturen en eigendomsbewijzen blijven privé; een handleiding of keuringsrapport mag openbaar. Lex controleert wat je toevoegt.</p>
            @include('dashboard.partials.lot-documents', ['lot' => $lot])
        </section>
    @elseif ($tab === 'verspreiding')
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="verspreiding">
            <h2 id="verspreiding" class="text-lg font-extrabold">Verspreiding</h2>
            <p class="mt-1 text-sm text-aj-muted">Deel de kavel op je eigen kanalen; per kanaal zie je of het geplaatst is en hoe vaak erop geklikt is.</p>
            @include('dashboard.partials.lot-distribution', ['lot' => $lot])
        </section>
    @else
        <section aria-labelledby="dossier">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="dossier" class="text-lg font-extrabold">Dossier</h2>
                <nav class="flex flex-wrap gap-1.5 text-xs" aria-label="Filter op bron">
                    <a href="{{ route('dashboard.lots.show', [$lot, 'tab' => 'dossier']) }}" @class(['rounded-full px-2.5 py-1 font-bold', 'bg-aj-navy text-white' => $source === null, 'border border-aj-border bg-aj-surface' => $source !== null])>Alles</a>
                    @foreach (LotActivity::SOURCES as $name)
                        @continue(! $counts->has($name))
                        <a href="{{ route('dashboard.lots.show', [$lot, 'tab' => 'dossier', 'bron' => $name]) }}" @class(['rounded-full px-2.5 py-1 font-bold', 'bg-aj-navy text-white' => $source === $name, 'border border-aj-border bg-aj-surface' => $source !== $name])>{{ __('dossier.sources.'.$name) }} <span class="tabular opacity-70">{{ $counts->get($name) }}</span></a>
                    @endforeach
                </nav>
            </div>
            <p class="mt-1 text-sm text-aj-muted">Alles wat met deze kavel gebeurde, op volgorde. De tijdlijn wordt nooit herschreven; een correctie is een nieuwe regel.</p>
            <x-dossier.timeline :activities="$activities" class="mt-4" empty="Nog niets gebeurd met deze kavel." />
        </section>
    @endif
</x-layouts.dashboard>
