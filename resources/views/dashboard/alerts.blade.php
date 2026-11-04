@use('App\Enums\NotificationChannel')

<x-layouts.dashboard title="Alerts" eyebrow="Dashboard" intro="Bewaarde zoekopdrachten. Elk uur kijken we of er nieuwe kavels bij passen; alleen bij een treffer hoor je iets.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Nieuwe alert instellen</a>
    </x-slot:actions>

    <x-slot:tiles>
        <x-dashboard.tile tone="blue" icon="search" label="Actief" :value="$searches->whereNull('paused_at')->count()" sub="zoekopdrachten bewaakt" />
        <x-dashboard.tile tone="orange" icon="pause" label="Gepauzeerd" :value="$searches->whereNotNull('paused_at')->count()" sub="tijdelijk stil" />
    </x-slot:tiles>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">{{ session('status') }}</p>
    @endif
    @error('query')
        <p class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $message }}</p>
    @enderror

    @if ($searches->isEmpty())
        <x-ui.empty-state title="Nog geen alerts"
            body="Zoek op de veilingen met de filters die je interesseren en kies daar Alert instellen. Vanaf dan hoor je het als er iets nieuws bij past.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <ul class="space-y-2">
            @foreach ($searches as $search)
                <li class="d-card" x-data="{ rename: false }">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="min-w-0 flex-1">
                            <a href="{{ route('lots.index', $search->query ?? []) }}" class="block truncate text-sm font-bold hover:underline">{{ $search->label() }}</a>
                            <span class="block text-xs text-aj-muted">
                                {{ $search->summary() }}
                                · {{ $search->channel === NotificationChannel::Push ? 'push' : 'per mail' }}
                                · {{ $search->last_run_at ? 'laatst gecontroleerd '.$search->last_run_at->timezone(config('aucvy.display_timezone'))->diffForHumans() : 'nog niet gecontroleerd' }}
                            </span>
                        </span>

                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $search->paused_at ? 'bg-aj-bg text-aj-muted' : 'bg-aj-success/10 text-aj-success-text' }}">
                            {{ $search->paused_at ? 'Gepauzeerd' : 'Actief' }}
                        </span>

                        <form method="POST" action="{{ route('dashboard.alerts.update', $search) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="paused" value="{{ $search->paused_at ? 0 : 1 }}">
                            <button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px">{{ $search->paused_at ? 'Hervatten' : 'Pauzeren' }}</button>
                        </form>
                        <button type="button" class="btn btn-ghost" style="height:36px;font-size:13px" @click="rename = ! rename">Naam</button>
                        <form method="POST" action="{{ route('dashboard.alerts.destroy', $search) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px;color:var(--color-aj-error)">Verwijderen</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('dashboard.alerts.update', $search) }}" x-show="rename" x-cloak class="mt-3 flex flex-wrap gap-2">
                        @csrf
                        @method('PATCH')
                        <label class="sr-only" for="name-{{ $search->id }}">Naam van de alert</label>
                        <input id="name-{{ $search->id }}" type="text" name="name" value="{{ $search->name }}" maxlength="80" placeholder="Bijvoorbeeld: heftrucks onder 10.000"
                               class="min-w-0 flex-1 d-input" style="min-height:40px">
                        <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">Opslaan</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.dashboard>
