@use('App\Enums\SaleState')

<x-layouts.dashboard title="Veilingen" eyebrow="Dashboard" intro="Zet kavels bij elkaar in één veiling met een sluitschema, een bezichtiging en je eigen voorwaarden.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('dashboard.sales.create') }}">Nieuwe veiling</a>
    </x-slot:actions>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif

    @if ($sales->isEmpty())
        <x-ui.empty-state
            title="Nog geen veilingen"
            body="Een veiling bundelt kavels: één sluitschema, één bezichtiging, één set voorwaarden. Handig bij een faillissement, een verhuizing of een partij.">
            <x-slot:action>
                <x-ui.button :href="route('dashboard.sales.create')">Nieuwe veiling</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <ul class="space-y-3">
            @foreach ($sales as $sale)
                @php $state = $sale->state(); @endphp
                <li class="flex flex-wrap items-center gap-4 d-card">
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('dashboard.sales.edit', $sale) }}" class="text-base font-extrabold hover:underline">{{ $sale->title }}</a>
                        <p class="text-sm text-aj-muted">
                            {{ $sale->lots_count }} {{ $sale->lots_count === 1 ? 'kavel' : 'kavels' }}
                            · eerste sluiting {{ $sale->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}
                            @if ($sale->viewing_at) · bezichtiging {{ $sale->viewing_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }} @endif
                        </p>
                    </div>
                    <span class="rounded-full border px-3 py-1 text-xs font-bold {{ match ($state) { SaleState::Live => 'border-aj-success/40 bg-aj-success/10 text-aj-success-text', SaleState::Scheduled => 'border-aj-blue/40 bg-aj-blue/10 text-aj-blue-text', SaleState::Cancelled => 'border-aj-error/40 bg-aj-error/5 text-aj-error', default => 'border-aj-border text-aj-muted' } }}">{{ $state->label() }}</span>
                    @if ($sale->isPublished())
                        <a class="btn btn-ghost" href="{{ route('sales.show', $sale) }}" style="height:38px;font-size:13px">Bekijk</a>
                    @endif
                    <a class="btn btn-primary" href="{{ route('dashboard.sales.edit', $sale) }}" style="height:38px;font-size:13px">Beheren</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.dashboard>
