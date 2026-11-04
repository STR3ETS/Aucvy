@use('App\Enums\SaleState')

@php
    $tz = config('aucvy.display_timezone');
    $sellerName = $sale->company?->name ?? $sale->seller->publicName();
    $intro = $lots->count().' '.($lots->count() === 1 ? 'kavel' : 'kavels').' van '.$sellerName.' · '.($state === SaleState::Closed ? 'gesloten' : 'sluit vanaf '.$sale->ends_at->timezone($tz)->isoFormat('dddd D MMMM, HH:mm'));
@endphp

<x-layouts.public :title="$sale->title" eyebrow="Veiling" :intro="$intro" :description="Str::limit($sale->description ?? $intro, 155)">
    <div class="grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
        <div class="space-y-5">
            @if ($sale->description)
                <section class="d-card" aria-labelledby="over">
                    <h2 id="over" class="text-lg font-extrabold">Over deze veiling</h2>
                    <p class="mt-2 whitespace-pre-line text-sm text-aj-soft">{{ $sale->description }}</p>
                </section>
            @endif

            <section aria-labelledby="kavels">
                <div class="flex flex-wrap items-baseline gap-3">
                    <h2 id="kavels" class="text-lg font-extrabold">Kavels</h2>
                    <p class="text-sm text-aj-muted">Sluiten {{ intdiv($sale->closing_gap_seconds, 60) }} {{ $sale->closing_gap_seconds === 60 ? 'minuut' : 'minuten' }} na elkaar; een bod in de laatste minuut verlengt alleen die kavel.</p>
                </div>
                @if ($lots->isEmpty())
                    <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center text-sm text-aj-muted">De kavels volgen binnenkort. Zet de veiling in je agenda: eerste sluiting {{ $sale->ends_at->timezone($tz)->isoFormat('D MMMM, HH:mm') }}.</p>
                @else
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($lots as $lot)
                            <x-v4.lot-card :lot="$lot" />
                        @endforeach
                    </div>
                    @if ($pending > 0)
                        <p class="mt-3 text-xs text-aj-muted">Nog {{ $pending }} {{ $pending === 1 ? 'kavel volgt' : 'kavels volgen' }}.</p>
                    @endif
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="d-card" aria-labelledby="schema">
                <h2 id="schema" class="text-sm font-extrabold">Sluitschema</h2>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Eerste sluiting</dt><dd class="font-semibold">{{ $sale->ends_at->timezone($tz)->isoFormat('D MMM, HH:mm') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Laatste sluiting</dt><dd class="font-semibold">{{ $sale->lastClosingAt()->timezone($tz)->isoFormat('D MMM, HH:mm') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Verkoper</dt><dd class="font-semibold">{{ $sellerName }}</dd></div>
                </dl>
            </section>

            @if ($sale->viewing_at || $sale->viewing_note)
                <section class="d-card" aria-labelledby="bezichtiging">
                    <h2 id="bezichtiging" class="text-sm font-extrabold">Bezichtiging</h2>
                    @if ($sale->viewing_at)
                        <p class="mt-1 text-sm font-semibold">{{ $sale->viewing_at->timezone($tz)->isoFormat('dddd D MMMM, HH:mm') }}</p>
                    @endif
                    @if ($sale->viewing_note)
                        <p class="mt-1 text-sm text-aj-muted">{{ $sale->viewing_note }}</p>
                    @endif
                    <p class="mt-2 text-xs text-aj-muted">Het adres volgt per mail na bevestiging van de verkoper; aanvragen doe je op de kavel zelf.</p>
                </section>
            @endif

            @if ($sale->pickup_from || $sale->pickup_until || $sale->pickup_note)
                <section class="d-card" aria-labelledby="ophalen">
                    <h2 id="ophalen" class="text-sm font-extrabold">Ophalen</h2>
                    @if ($sale->pickup_from || $sale->pickup_until)
                        <p class="mt-1 text-sm font-semibold">{{ $sale->pickup_from?->isoFormat('D MMM') ?? '' }}{{ $sale->pickup_from && $sale->pickup_until ? ' tot en met ' : '' }}{{ $sale->pickup_until?->isoFormat('D MMM') ?? '' }}</p>
                    @endif
                    @if ($sale->pickup_note)
                        <p class="mt-1 text-sm text-aj-muted">{{ $sale->pickup_note }}</p>
                    @endif
                </section>
            @endif

            @if ($sale->terms)
                <section class="d-card" aria-labelledby="voorwaarden">
                    <h2 id="voorwaarden" class="text-sm font-extrabold">Voorwaarden van de verkoper</h2>
                    <p class="mt-1 whitespace-pre-line text-sm text-aj-muted">{{ $sale->terms }}</p>
                    <p class="mt-2 text-xs text-aj-muted">Naast de <a class="underline" href="{{ route('assurance') }}">regels van Aucvy</a>, die altijd gelden.</p>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.public>
