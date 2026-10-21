<x-layouts.dashboard title="Nieuwe veiling" eyebrow="Veilingen" intro="Zet kavels bij elkaar met één sluitschema, een bezichtiging en je eigen voorwaarden. Kavels voeg je in de volgende stap toe.">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.sales') }}">Veilingen</a> <span aria-hidden="true">/</span> <span>Nieuw</span>
    </x-slot:crumbs>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('dashboard.sales.store') }}" class="mx-auto max-w-3xl rounded-aj-s border border-aj-border bg-aj-surface p-6">
        @csrf
        @include('dashboard.partials.sale-fields', ['sale' => null])
        <div class="mt-5 flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Veiling aanmaken</button>
            <a class="btn btn-ghost" href="{{ route('dashboard.sales') }}">Annuleren</a>
        </div>
    </form>
</x-layouts.dashboard>
