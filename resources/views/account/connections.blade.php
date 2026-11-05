@use('App\Models\ChannelConnection')

<x-layouts.dashboard title="Koppelingen" eyebrow="Account" intro="Kanalen waarop Aucvy namens jou plaatst. Zonder koppeling werkt de deelkit altijd: tekst en beeld kopiëren, zelf plaatsen.">
    <x-slot:crumbs>
        <a href="{{ route('account.settings') }}">Instellingen</a> <span aria-hidden="true">/</span> <span>Koppelingen</span>
    </x-slot:crumbs>
    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <ul class="grid gap-4 md:grid-cols-2">
        @foreach ($channels as $row)
            @php $connection = $row['connection']; $status = $connection?->status ?? ($row['available'] ? 'none' : 'unavailable'); @endphp
            <li class="d-card">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-extrabold">{{ $row['channel']->label() }}</h2>
                        <p class="mt-1 text-xs text-aj-muted">{{ __('distribution.connection_help.'.$row['channel']->value) }}</p>
                    </div>
                    <span @class(['shrink-0 rounded-full px-2 py-0.5 text-xs font-bold', 'bg-aj-success/10 text-aj-success-text' => $connection?->isUsable(), 'bg-aj-bg text-aj-muted' => ! $connection?->isUsable()])>{{ $connection ? $connection->statusLabel() : __('distribution.connection_status.'.$status) }}</span>
                </div>
                @if ($connection?->external_name)
                    <p class="mt-2 text-sm">Gekoppeld aan <span class="font-semibold">{{ $connection->external_name }}</span>{{ $connection->expires_at ? ' · geldig tot '.$connection->expires_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') : '' }}</p>
                @endif
                @if ($connection?->last_error)
                    <p class="mt-2 text-xs text-aj-error">{{ $connection->last_error }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    @if ($row['available'])
                        <a href="{{ route('account.connections.connect', $row['channel']->value) }}" class="btn btn-primary" style="height:36px;font-size:13px">{{ $connection ? 'Opnieuw koppelen' : 'Koppelen' }}</a>
                    @else
                        <span class="btn btn-ghost cursor-default opacity-70" style="height:36px;font-size:13px" aria-disabled="true">Koppeling volgt</span>
                    @endif
                    @if ($connection)
                        <form method="POST" action="{{ route('account.connections.disconnect', $row['channel']->value) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px">Verwijderen</button></form>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    <p class="mt-5 text-xs text-aj-muted">Tokens staan versleuteld opgeslagen. Elk kanaal plaatst via een eigen, idempotente job; bij sluiten, verkoop of intrekken van de kavel gaan alle advertenties offline.</p>
</x-layouts.dashboard>
