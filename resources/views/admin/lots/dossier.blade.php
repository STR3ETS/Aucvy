@use('App\Models\LotActivity')

<x-layouts.admin :title="'Dossier · '.$lot->title" :subtitle="$lot->public_id.' · '.$lot->status->label().' · '.($lot->seller?->name ?? '').($lot->company ? ' · '.$lot->company->name : '')">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.lots') }}" class="underline">Kavels</a> · <a href="{{ route('admin.lots.show', $lot) }}" class="underline">{{ $lot->public_id }}</a> · Dossier
        @if ($lot->order)
            · <a href="{{ route('admin.orders.show', $lot->order) }}" class="underline">Order {{ $lot->order->order_number }}</a>
        @endif
    </p>

    {{-- Het volledige dossier, elke regel ongeacht zichtbaarheid, met een filter per bron. --}}
    <nav class="mt-5 flex flex-wrap gap-1.5 text-xs" aria-label="Filter op bron">
        <a href="{{ route('admin.lots.dossier', $lot) }}" @class(['rounded-full px-2.5 py-1 font-bold', 'bg-aj-navy text-white' => $source === null, 'border border-aj-border bg-aj-surface' => $source !== null])>Alles</a>
        @foreach (LotActivity::SOURCES as $name)
            @continue(! $counts->has($name))
            <a href="{{ route('admin.lots.dossier', [$lot, 'bron' => $name]) }}" @class(['rounded-full px-2.5 py-1 font-bold', 'bg-aj-navy text-white' => $source === $name, 'border border-aj-border bg-aj-surface' => $source !== $name])>{{ __('dossier.sources.'.$name) }} <span class="tabular opacity-70">{{ $counts->get($name) }}</span></a>
        @endforeach
    </nav>

    <x-dossier.timeline :activities="$activities" class="mt-4" empty="Nog niets in het dossier van deze kavel." />
</x-layouts.admin>
