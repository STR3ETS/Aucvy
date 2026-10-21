@use('App\Enums\LotStatus')
@use('App\Enums\SaleState')
@use('App\Support\Money\Money')

@php
    $tz = config('aucvy.display_timezone');
    $drafts = $sale->lots->where('status', LotStatus::Draft);
@endphp

<x-layouts.dashboard :title="$sale->title" eyebrow="Veilingen" :intro="$state->label().' · '.$sale->lots->count().' '.($sale->lots->count() === 1 ? 'kavel' : 'kavels').' · eerste sluiting '.$sale->ends_at->timezone($tz)->isoFormat('D MMM, HH:mm')">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.sales') }}">Veilingen</a> <span aria-hidden="true">/</span> <span>{{ $sale->public_id }}</span>
    </x-slot:crumbs>

    <x-slot:actions>
        @if ($sale->isPublished())
            <a class="btn btn-white" href="{{ route('sales.show', $sale) }}">Bekijk op de site</a>
        @endif
    </x-slot:actions>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif
    @if ($state === SaleState::Cancelled)
        <p class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">Deze veiling is geannuleerd. Lopende kavels liepen door; concepten staan weer los.</p>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_380px] lg:items-start">
        <div class="space-y-5">
            {{-- De kavels in de veiling, in sluitvolgorde. --}}
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="kavels">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 id="kavels" class="text-lg font-extrabold">Kavels</h2>
                    <p class="text-xs text-aj-muted">In deze volgorde sluiten ze, {{ intdiv($sale->closing_gap_seconds, 60) }} {{ $sale->closing_gap_seconds === 60 ? 'minuut' : 'minuten' }} na elkaar.</p>
                    @if ($drafts->isNotEmpty() && $sale->isPublished())
                        <form method="POST" action="{{ route('dashboard.sales.publish-drafts', $sale) }}" class="ml-auto">
                            @csrf
                            <button type="submit" class="btn btn-primary" style="height:38px;font-size:13px">{{ $drafts->count() }} {{ $drafts->count() === 1 ? 'concept' : 'concepten' }} publiceren</button>
                        </form>
                    @endif
                </div>
                @if ($sale->lots->isEmpty())
                    <p class="mt-4 rounded-aj-sm border border-dashed border-aj-border-strong p-5 text-center text-sm text-aj-muted">Nog geen kavels. Kies hieronder uit je concepten.</p>
                @else
                    <ol class="mt-4 divide-y divide-aj-border">
                        @foreach ($sale->lots as $lot)
                            @php $closing = $sale->closingFor((int) $lot->sale_position); $auction = $lot->auction; @endphp
                            <li class="flex flex-wrap items-center gap-3 py-3">
                                <span class="tabular w-6 text-sm font-bold text-aj-muted">{{ $lot->sale_position + 1 }}</span>
                                @if ($lot->media->first())
                                    <img src="{{ $lot->media->first()->path }}" alt="" class="size-12 rounded-aj-sm object-cover">
                                @else
                                    <span class="size-12 rounded-aj-sm bg-aj-bg"></span>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <b class="block truncate text-sm">{{ $lot->title ?? 'Zonder titel' }}</b>
                                    <span class="block text-xs text-aj-muted">
                                        {{ $lot->status->label() }}
                                        @if ($auction) · stand {{ Money::format($auction->current_price_cents) }} · {{ $auction->bid_count }} {{ $auction->bid_count === 1 ? 'bod' : 'biedingen' }} · sluit {{ $auction->ends_at->timezone($tz)->isoFormat('D MMM, HH:mm') }}
                                        @else · sluit gepland {{ $closing->timezone($tz)->isoFormat('D MMM, HH:mm') }} @endif
                                    </span>
                                    @if (isset($failed[$lot->id]))
                                        <span class="block text-xs text-aj-error">Niet gepubliceerd: {{ $failed[$lot->id] }}</span>
                                    @endif
                                </span>
                                <span class="flex items-center gap-1">
                                    <form method="POST" action="{{ route('dashboard.sales.move', [$sale, $lot]) }}">@csrf<input type="hidden" name="direction" value="up"><button type="submit" class="btn btn-ghost" style="height:30px;padding:0 8px;font-size:12px" aria-label="Omhoog" @disabled($loop->first)>↑</button></form>
                                    <form method="POST" action="{{ route('dashboard.sales.move', [$sale, $lot]) }}">@csrf<input type="hidden" name="direction" value="down"><button type="submit" class="btn btn-ghost" style="height:30px;padding:0 8px;font-size:12px" aria-label="Omlaag" @disabled($loop->last)>↓</button></form>
                                    @if ($lot->status === LotStatus::Draft)
                                        <a class="btn btn-ghost" href="{{ route('sell', ['concept' => $lot->id]) }}" style="height:30px;padding:0 10px;font-size:12px">Wizard</a>
                                    @else
                                        <a class="btn btn-ghost" href="{{ route('dashboard.lots.edit', $lot) }}" style="height:30px;padding:0 10px;font-size:12px">Bewerken</a>
                                    @endif
                                    <form method="POST" action="{{ route('dashboard.sales.detach', [$sale, $lot]) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost" style="height:30px;padding:0 8px;font-size:12px" aria-label="Uit de veiling halen">×</button></form>
                                </span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            @if ($state !== SaleState::Cancelled && $state !== SaleState::Closed)
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="toevoegen">
                    <h2 id="toevoegen" class="text-sm font-extrabold">Kavels toevoegen</h2>
                    <p class="mt-1 text-xs text-aj-muted">Je concepten die nog in geen veiling zitten. Een kavel die al live is houdt zijn eigen looptijd.</p>
                    @if ($attachable->isEmpty())
                        <p class="mt-3 text-sm text-aj-muted">Geen concepten over. <a class="font-bold underline" href="{{ route('sell', ['nieuw' => 1]) }}">Nieuw concept</a> of <a class="font-bold underline" href="{{ route('dashboard.import') }}">importeer een csv</a>.</p>
                    @else
                        <form method="POST" action="{{ route('dashboard.sales.attach', $sale) }}" class="mt-3">
                            @csrf
                            <ul class="grid gap-2 sm:grid-cols-2">
                                @foreach ($attachable as $lot)
                                    <li>
                                        <label class="flex items-center gap-3 rounded-aj-sm border border-aj-border p-2 text-sm">
                                            <input type="checkbox" name="lots[]" value="{{ $lot->id }}">
                                            <span class="min-w-0"><b class="block truncate">{{ $lot->title ?? 'Zonder titel' }}</b><span class="block text-xs text-aj-muted">{{ $lot->completeness_score }}% compleet · {{ $lot->status->label() }}</span></span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                            <button type="submit" class="btn btn-primary mt-3" style="height:38px;font-size:13px">Toevoegen aan de veiling</button>
                        </form>
                    @endif
                </section>
            @endif
        </div>

        <aside class="space-y-5">
            @if (! $sale->isPublished() && $state !== SaleState::Cancelled)
                <section class="rounded-aj-s border border-aj-blue/30 bg-aj-blue/5 p-5" aria-label="Publiceren">
                    <h2 class="text-sm font-extrabold">Nog niet gepubliceerd</h2>
                    <p class="mt-1 text-xs text-aj-muted">Publiceren zet de veilingpagina online en legt het sluitschema vast. Concepten erin publiceer je daarna per stuk of in één keer.</p>
                    <form method="POST" action="{{ route('dashboard.sales.publish', $sale) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-primary w-full justify-center">Veiling publiceren</button>
                    </form>
                </section>
            @endif

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="gegevens">
                <h2 id="gegevens" class="text-sm font-extrabold">Gegevens</h2>
                <form method="POST" action="{{ route('dashboard.sales.update', $sale) }}" class="mt-3">
                    @csrf @method('PATCH')
                    @include('dashboard.partials.sale-fields', ['sale' => $sale])
                    <button type="submit" class="btn btn-primary mt-4" style="height:38px;font-size:13px">Opslaan</button>
                    @if ($sale->isPublished())
                        <p class="mt-2 text-xs text-aj-muted">Schuift de eerste sluiting, dan schuiven kavels zonder bod mee. Waar al geboden is blijft de sluittijd staan.</p>
                    @endif
                </form>
            </section>

            @if ($state !== SaleState::Cancelled && $state !== SaleState::Closed)
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Annuleren">
                    <form method="POST" action="{{ route('dashboard.sales.cancel', $sale) }}" onsubmit="return confirm('Veiling annuleren? Concepten komen weer los te staan.')">
                        @csrf
                        <button type="submit" class="btn btn-ghost w-full justify-center" style="color:var(--color-aj-error)">Veiling annuleren</button>
                    </form>
                    <p class="mt-2 text-xs text-aj-muted">Kan alleen zolang er op geen enkele kavel geboden is.</p>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
