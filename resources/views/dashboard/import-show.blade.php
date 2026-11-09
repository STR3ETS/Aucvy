<x-layouts.dashboard :title="$import->isDone() ? 'Import afgerond' : 'Kolommen koppelen'" eyebrow="Importeren" :intro="$import->filename.' · '.$import->rows_total.' '.($import->rows_total === 1 ? 'rij' : 'rijen')">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.lots') }}">Mijn kavels</a> <span aria-hidden="true">/</span>
        <a href="{{ route('dashboard.import') }}">Importeren</a> <span aria-hidden="true">/</span> <span>{{ $import->filename }}</span>
    </x-slot:crumbs>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif
    @if ($readError ?? null)
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">Het bestand is niet meer te lezen: {{ $readError }} <a class="underline" href="{{ route('dashboard.import') }}">Upload het opnieuw</a>.</div>
    @endif

    {{-- Dezelfde drie stappen als op de uploadpagina. --}}
    <ol class="d-import-steps" aria-label="Stappen" style="margin-bottom:18px">
        <li class="is-done">1. Uploaden</li>
        <li class="{{ $import->isDone() ? 'is-done' : 'is-on' }}" @unless ($import->isDone()) aria-current="step" @endunless>2. Kolommen koppelen</li>
        <li class="{{ $import->isDone() ? 'is-on' : '' }}" @if ($import->isDone()) aria-current="step" @endif>3. Controleren</li>
    </ol>

    @if ($import->isDone())
        {{-- Het rapport: wat erin kwam en wat niet, met de reden per rij. --}}
        <div class="grid gap-5 lg:grid-cols-[1fr_300px] lg:items-start">
            <section class="d-card" aria-labelledby="rapport">
                <h2 id="rapport" class="text-lg font-extrabold">{{ $import->rows_created }} van {{ $import->rows_total }} rijen staan als concept klaar</h2>
                <p class="mt-1 text-sm text-aj-muted">Elk concept opent in de wizard: foto's toevoegen, eigendom verklaren, controleren en publiceren.</p>
                @if ($import->errors)
                    <h3 class="mt-5 text-sm font-extrabold">Niet geïmporteerd</h3>
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($import->errors as $row)
                            <li class="rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3">
                                <b>Rij {{ $row['line'] }}</b>{{ $row['title'] ? ' · '.$row['title'] : '' }}
                                <span class="block text-xs text-aj-error">{{ implode(' ', $row['errors']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-aj-muted">Pas de rijen aan in je spreadsheet en importeer alleen die opnieuw; wat al is geïmporteerd blijft staan.</p>
                @endif
                <div class="mt-5 flex flex-wrap gap-2">
                    <a class="btn btn-primary" href="{{ route('dashboard.lots') }}">Naar mijn kavels</a>
                    <a class="btn btn-ghost" href="{{ route('dashboard.import') }}">Nog een bestand</a>
                </div>
            </section>
            <aside class="d-card text-sm" aria-label="Mapping">
                <h2 class="text-sm font-extrabold">Gebruikte koppeling</h2>
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach ($fields as $key => $label)
                        @if (($import->mapping[$key] ?? null) !== null)
                            <li class="flex justify-between gap-3"><span class="text-aj-muted">{{ $label }}</span><span class="font-semibold">{{ $import->headers[$import->mapping[$key]] ?? '?' }}</span></li>
                        @endif
                    @endforeach
                </ul>
            </aside>
        </div>
    @else
        <form method="POST" action="{{ route('dashboard.import.run', $import) }}" class="grid gap-5 lg:grid-cols-[320px_1fr] lg:items-start">
            @csrf
            <section class="d-card" aria-labelledby="koppelen">
                <h2 id="koppelen" class="text-sm font-extrabold">Welke kolom hoort waarbij?</h2>
                <p class="mt-1 text-xs text-aj-muted">We hebben een voorzet gedaan op de kopteksten. Alleen de titel is verplicht.</p>
                <div class="mt-3 grid gap-2">
                    @foreach ($fields as $key => $label)
                        <label class="grid gap-1 text-sm">
                            <span class="font-bold">{{ $label }}{{ $key === 'title' ? ' *' : '' }}</span>
                            <select name="mapping[{{ $key }}]" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm" style="min-height:38px" @required($key === 'title')>
                                <option value="">Niet importeren</option>
                                @foreach ($import->headers as $index => $header)
                                    <option value="{{ $index }}" @selected(($mapping[$key] ?? null) === $index)>{{ $header }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary mt-4 w-full justify-center">Importeer {{ $import->rows_total }} {{ $import->rows_total === 1 ? 'rij' : 'rijen' }} als concept</button>
            </section>

            <section class="overflow-x-auto d-card" aria-labelledby="voorbeeld">
                <h2 id="voorbeeld" class="text-sm font-extrabold">Voorbeeld van de eerste rijen</h2>
                <p class="mt-1 text-xs text-aj-muted">Zo leest Aucvy je bestand met de voorzet hiernaast. Rijen met een fout worden overgeslagen en staan in het rapport.</p>
                @php $categoryNames = \App\Models\Category::query()->pluck('name', 'id'); @endphp
                    <table class="admin-table mt-3">
                    <thead><tr><th>Rij</th><th>Titel</th><th>Categorie</th><th>Conditie</th><th>Verkoopvorm</th><th>Startprijs</th><th>Fouten</th></tr></thead>
                    <tbody>
                        @foreach ($preview as $row)
                            <tr>
                                <td class="tabular text-aj-muted">{{ $row['line'] }}</td>
                                <td class="max-w-[24ch] truncate">{{ $row['values']['title'] ?? '-' }}</td>
                                <td>{{ $row['values']['category_id'] ? ($categoryNames[$row['values']['category_id']] ?? '-') : '-' }}</td>
                                <td>{{ $row['values']['condition'] ?? '-' }}</td>
                                <td>{{ $row['values']['sale_type'] ?? '-' }}</td>
                                <td class="tabular">{{ $row['values']['start_price_cents'] !== null ? \App\Support\Money\Money::format($row['values']['start_price_cents']) : '-' }}</td>
                                <td class="text-xs {{ $row['errors'] ? 'text-aj-error' : 'text-aj-success-text' }}">{{ $row['errors'] ? implode(' ', $row['errors']) : 'In orde' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-2 text-xs text-aj-muted">Het voorbeeld volgt de voorzet; verander je de koppeling, dan geldt die bij het importeren.</p>
            </section>
        </form>
    @endif
</x-layouts.dashboard>
