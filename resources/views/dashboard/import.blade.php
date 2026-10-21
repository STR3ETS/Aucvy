<x-layouts.dashboard title="Importeren" eyebrow="Mijn kavels" intro="Zet een hele lijst uit csv of Excel in één keer klaar als concepten. Daarna loop je per kavel de wizard langs voor foto's en publicatie.">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.lots') }}">Mijn kavels</a> <span aria-hidden="true">/</span> <span>Importeren</span>
    </x-slot:crumbs>

    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="upload">
            <h2 id="upload" class="text-lg font-extrabold">Csv of Excel uploaden</h2>
            <p class="mt-1 text-sm text-aj-muted">Eén rij per kavel, met een kopregel. Csv (puntkomma of komma) of xlsx (het eerste werkblad); bedragen in euro's. Maximaal 500 rijen en 4 MB per bestand. In de volgende stap koppel je de kolommen.</p>
            <form method="POST" action="{{ route('dashboard.import.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <label class="dropzone dropzone-sm" style="cursor:pointer">
                    <x-v4.icon name="upload" :size="18" stroke="2.2" />
                    <span class="text-sm"><b>Kies een csv- of xlsx-bestand</b></span>
                    <input type="file" name="file" accept=".csv,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="sr-only" onchange="this.form.querySelector('[data-name]').textContent = this.files[0]?.name ?? ''">
                    <span class="text-xs text-aj-muted" data-name></span>
                </label>
                <button type="submit" class="btn btn-primary">Verder naar kolommen koppelen</button>
            </form>
        </section>

        <aside class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="kolommen">
            <h2 id="kolommen" class="text-sm font-extrabold">Kolommen die we herkennen</h2>
            <p class="mt-1 text-xs text-aj-muted">Koptekst in het Nederlands of Engels; alleen de titel is verplicht.</p>
            <ul class="mt-3 grid gap-1 text-sm">
                @foreach ($fields as $key => $label)
                    <li class="flex justify-between gap-3 border-b border-aj-border py-1"><span>{{ $label }}</span><code class="text-xs">{{ $key }}</code></li>
                @endforeach
            </ul>
        </aside>
    </div>

    @if ($imports->isNotEmpty())
        <section class="mt-6 rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="eerder">
            <h2 id="eerder" class="text-sm font-extrabold">Eerdere imports</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($imports as $import)
                    <li class="flex flex-wrap items-center gap-3 border-b border-aj-border pb-2">
                        <a href="{{ route('dashboard.import.show', $import) }}" class="font-bold underline">{{ $import->filename }}</a>
                        <span class="text-aj-muted">{{ $import->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span>
                        <span class="ml-auto text-xs">
                            @if ($import->isDone())
                                {{ $import->rows_created }} van {{ $import->rows_total }} rijen als concept{{ $import->errors ? ' · '.count($import->errors).' met fouten' : '' }}
                            @else
                                Nog niet geïmporteerd
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.dashboard>
