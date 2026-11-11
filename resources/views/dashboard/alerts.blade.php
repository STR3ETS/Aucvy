@use('App\Enums\NotificationChannel')

@php
    $displayTimezone = config('aucvy.display_timezone');
    // In welke tabel een alert hoort: waar iets nieuws bij kwam eerst, dan wat aan staat en wat gepauzeerd is.
    $groupOf = fn ($search): string => match (true) {
        $search->paused_at !== null => 'gepauzeerd',
        ($matches[$search->id]['new'] ?? 0) > 0 => 'nieuw',
        default => 'aan',
    };
    $grouped = $searches->groupBy($groupOf);
    $tableSpecs = [
        'nieuw' => ['Nieuwe matches', 'Alerts waar in de laatste zeven dagen iets nieuws bij kwam. Bekijk de kavels voordat de eerste veiling sluit.', ['new', 'total', 'checked']],
        'aan' => ['Staat aan', 'Ivy kijkt elk uur of er iets nieuws bij past. Je hoort het in je meldingen en per mail. Een nieuwe alert maak je op de veilingen: zoek met je filters en kies daar Alert instellen.', ['total', 'checked']],
        'gepauzeerd' => ['Gepauzeerd', 'Deze alerts staan uit: je krijgt er geen melding van. Met de afspeelknop zet je ze weer aan.', ['total', 'checked']],
    ];
    $tables = collect($tableSpecs)
        ->map(fn (array $spec, string $key): array => ['title' => $spec[0], 'help' => $spec[1], 'columns' => $spec[2], 'searches' => $grouped->get($key, collect())->values()])
        ->filter(fn (array $table): bool => $table['searches']->isNotEmpty());
    $widths = ['new' => '110px', 'total' => '110px', 'checked' => '200px'];
    $labels = ['new' => 'Nieuw', 'total' => 'Past nu', 'checked' => 'Gecontroleerd'];
    $listLimit = 10;
@endphp

{{--
    Alerts, net als Mijn kavels: per stand een eigen tabel in een eigen kaart,
    de koppen één keer en de acties als pictogram. De uitleg over hoe een
    alert werkt staat achter het vraagteken; hoe je het wilt horen stel je in
    via de knop bovenaan.
--}}
<x-layouts.dashboard title="Alerts" eyebrow="Dashboard" intro="Ivy houdt je zoekopdrachten in de gaten en laat het weten als er iets nieuws bij past.">
    <x-slot:actions>
        <a class="btn btn-white" href="{{ route('lots.index') }}">Nieuwe alert</a>
        <a class="btn btn-outline-white" href="{{ route('account.settings.section', 'meldingen') }}">Meldingen instellen</a>
    </x-slot:actions>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif
    @error('query')
        <p class="d-soft is-err" style="margin:0 0 14px">{{ $message }}</p>
    @enderror

    @if ($searches->isEmpty())
        <x-ui.empty-state title="Nog geen alerts"
            body="Zoek op de veilingen met de filters die je interesseren en kies daar Alert instellen. Vanaf dan hoor je het als er iets nieuws bij past.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="d-sections d-stack">
            @foreach ($tables as $key => $table)
                @php
                    $columns = $table['columns'];
                    $template = 'minmax(0,1fr) '.collect($columns)->map(fn (string $column): string => $widths[$column])->implode(' ').' 180px';
                @endphp
                <section class="d-card" aria-labelledby="alerts-{{ $key }}" x-data="{ all: false }">
                    <div class="d-titleline" style="margin-bottom:12px">
                        <h2 id="alerts-{{ $key }}" class="d-h2">{{ $table['title'] }}</h2>
                        <span class="d-count tabular">{{ $table['searches']->count() }}</span>
                        <x-dashboard.help :text="$table['help']" />
                    </div>

                    <div class="d-lottable" style="--cols:{{ $template }}">
                        <div class="d-lottable-head" aria-hidden="true">
                            <span>Alert</span>
                            @foreach ($columns as $column)
                                <span @class(['num' => $column !== 'checked'])>{{ $labels[$column] }}</span>
                            @endforeach
                            <span></span>
                        </div>

                        @foreach ($table['searches'] as $search)
                            @php
                                $found = $matches[$search->id] ?? ['new' => 0, 'total' => 0];
                                $paused = $search->paused_at !== null;
                                $url = route('lots.index', $search->query ?? []);
                                $viewTip = match (true) {
                                    $found['new'] > 1 => 'Bekijk matches',
                                    $found['new'] === 1 => 'Bekijk match',
                                    $found['total'] > 0 => 'Bekijk kavels',
                                    default => 'Zoekvraag verfijnen',
                                };
                                [$tagTone, $tagLabel] = match ($key) {
                                    'nieuw' => ['tone-atlas', $found['new'] === 1 ? '1 nieuwe match' : $found['new'].' nieuwe matches'],
                                    'gepauzeerd' => ['tone-grey', 'Gepauzeerd'],
                                    default => ['tone-ok', 'Aan'],
                                };
                            @endphp
                            <article class="d-lotrow d-alertrow" x-data="{ rename: false }" @if ($loop->index >= $listLimit) x-show="all" x-cloak @endif>
                                <div class="d-lotrow-lot">
                                    <span @class(['thumb', 'is-new' => $key === 'nieuw']) aria-hidden="true"><x-v4.icon :name="$paused ? 'pause' : 'search'" :size="20" stroke="1.8" /></span>
                                    <div class="text">
                                        <strong><a href="{{ $url }}">{{ $search->label() }}</a></strong>
                                        <span class="d-lotrow-tags">
                                            <span class="d-tag {{ $tagTone }}">{{ $tagLabel }}</span>
                                            <span class="d-bid-meta">{{ $search->summary() }} · {{ $search->channel === NotificationChannel::Push ? 'push' : 'per mail' }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="d-lotrow-facts">
                                    @foreach ($columns as $column)
                                        @if ($column === 'new')
                                            <span class="cell is-value"><span class="label">Nieuw</span><b class="tabular">{{ $found['new'] }}</b></span>
                                        @elseif ($column === 'total')
                                            <span class="cell is-value"><span class="label">Past nu</span><b class="tabular">{{ $found['total'] }}</b> <span class="d-muted">{{ $found['total'] === 1 ? 'kavel' : 'kavels' }}</span></span>
                                        @else
                                            <span class="cell is-moment">{{ $search->last_run_at ? ucfirst($search->last_run_at->timezone($displayTimezone)->diffForHumans()) : 'Nog niet gecontroleerd' }}</span>
                                        @endif
                                    @endforeach
                                </div>

                                {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
                                <div class="d-lotrow-actions">
                                    <a href="{{ $url }}" @class(['d-iconbtn', 'is-dark' => $key === 'nieuw']) data-tip="{{ $viewTip }}" aria-label="{{ $viewTip }}: {{ $search->label() }}"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
                                    <form method="POST" action="{{ route('dashboard.alerts.update', $search) }}" style="display:flex">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="paused" value="{{ $paused ? 0 : 1 }}">
                                        <button type="submit" class="d-iconbtn" data-tip="{{ $paused ? 'Hervatten' : 'Pauzeren' }}" aria-label="{{ $search->label() }} {{ $paused ? 'hervatten' : 'pauzeren' }}"><x-v4.icon :name="$paused ? 'play' : 'pause'" :size="17" stroke="2" /></button>
                                    </form>
                                    <button type="button" class="d-iconbtn" :class="rename && 'is-on'" data-tip="Naam wijzigen" aria-label="Naam van {{ $search->label() }} wijzigen" :aria-expanded="rename" @click="rename = ! rename; if (rename) $nextTick(() => $refs.name.focus())"><x-v4.icon name="pencil" :size="17" stroke="2" /></button>
                                    <form method="POST" action="{{ route('dashboard.alerts.destroy', $search) }}" style="display:flex">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="d-iconbtn is-danger" data-tip="Verwijderen" aria-label="{{ $search->label() }} verwijderen"><x-v4.icon name="trash" :size="17" stroke="2" /></button>
                                    </form>
                                </div>

                                <form method="POST" action="{{ route('dashboard.alerts.update', $search) }}" x-show="rename" x-cloak class="d-alertrow-rename">
                                    @csrf
                                    @method('PATCH')
                                    <label class="sr-only" for="name-{{ $search->id }}">Naam van de alert</label>
                                    <input id="name-{{ $search->id }}" x-ref="name" type="text" name="name" value="{{ $search->name }}" maxlength="80" placeholder="Bijvoorbeeld: heftrucks onder 10.000" class="d-input" style="margin:0">
                                    <button type="submit" class="d-btn d-btn-dark d-btn-sm d-btn-sq">Opslaan</button>
                                </form>
                            </article>
                        @endforeach
                    </div>

                    <x-dashboard.more :total="$table['searches']->count()" :limit="$listLimit" />
                </section>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
