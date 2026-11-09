<x-layouts.dashboard title="Importeren" eyebrow="Mijn kavels" intro="Zet een hele lijst kavels in één keer klaar als concept, vanuit csv of Excel.">
    <x-slot:crumbs>
        <a href="{{ route('dashboard.lots') }}">Mijn kavels</a> <span aria-hidden="true">/</span> <span>Importeren</span>
    </x-slot:crumbs>

    @if ($errors->any())
        <div role="alert" class="d-soft is-err" style="margin:0 0 14px">{{ $errors->first() }}</div>
    @endif

    {{-- De drie stappen van het ontwerp; hier sta je op de eerste. --}}
    <ol class="d-import-steps" aria-label="Stappen">
        <li class="is-on" aria-current="step">1. Uploaden</li>
        <li>2. Kolommen koppelen</li>
        <li>3. Controleren</li>
    </ol>

    <div class="d-cols" style="margin-top:18px">
        <form method="POST" action="{{ route('dashboard.import.store') }}" enctype="multipart/form-data" class="d-col-main" x-data="{ name: '', over: false }">
            @csrf
            <label class="d-import-drop" :class="over && 'is-over'"
                   @dragover.prevent="over = true" @dragleave.prevent="over = false"
                   @drop.prevent="over = false; $refs.file.files = $event.dataTransfer.files; name = $refs.file.files[0]?.name ?? ''">
                <span class="icon" aria-hidden="true"><x-v4.icon name="upload" :size="22" stroke="2" /></span>
                <strong x-text="name || 'Kies een csv- of xlsx-bestand'">Kies een csv- of xlsx-bestand</strong>
                <span x-text="name ? 'Klik om een ander bestand te kiezen' : 'Maximaal 500 rijen en 4 MB per bestand'">Maximaal 500 rijen en 4 MB per bestand</span>
                <input type="file" name="file" x-ref="file" accept=".csv,.xlsx,text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required class="sr-only"
                       @change="name = $event.target.files[0]?.name ?? ''">
            </label>

            <div class="d-callout-info" style="flex-direction:column;gap:10px">
                <div style="display:flex;gap:12px;align-items:center">
                    <span class="mark" aria-hidden="true">i</span>
                    <strong style="margin:0">Wat moet er in je bestand staan?</strong>
                </div>
                <ol class="d-numsteps">
                    <li><span>1</span>Eén rij per kavel, met een kopregel.</li>
                    <li><span>2</span>Csv met puntkomma of komma, of xlsx (het eerste werkblad).</li>
                    <li><span>3</span>Bedragen in euro's. In de volgende stap koppel je de kolommen.</li>
                </ol>
            </div>

            <div style="display:flex;justify-content:flex-end">
                <button type="submit" class="d-btn d-btn-sq">Verder naar kolommen koppelen</button>
            </div>
        </form>

        <aside class="d-col-aside" style="padding:16px" aria-labelledby="kolommen">
            <h2 id="kolommen" class="d-h3" style="font-size:15px">Kolommen die we herkennen</h2>
            <p class="d-sub">Koptekst in het Nederlands of Engels; alleen de titel is verplicht.</p>
            <div class="d-rows" style="margin-top:8px">
                @foreach ($fields as $key => $label)
                    <div class="d-row" style="align-items:center"><span style="font-weight:600">{{ $label }}</span><code style="font-size:12px;color:#4d5566">{{ $key }}</code></div>
                @endforeach
            </div>
        </aside>
    </div>

    @if ($imports->isNotEmpty())
        <section style="margin-top:22px" aria-labelledby="eerder">
            <h2 id="eerder" class="d-h3" style="margin-bottom:10px">Eerdere imports</h2>
            <div class="d-datatable">
                <table>
                    <thead><tr><th>Bestand</th><th>Geüpload</th><th>Resultaat</th><th><span class="sr-only">Openen</span></th></tr></thead>
                    <tbody>
                        @foreach ($imports as $import)
                            <tr>
                                <td><a href="{{ route('dashboard.import.show', $import) }}" class="d-link" style="font-size:14px">{{ $import->filename }}</a></td>
                                <td class="nowrap">{{ $import->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                                <td>
                                    @if ($import->isDone())
                                        {{ $import->rows_created }} van {{ $import->rows_total }} rijen als concept{{ $import->errors ? ' · '.count($import->errors).' met fouten' : '' }}
                                    @else
                                        <span class="d-tag tone-warn">Nog niet geïmporteerd</span>
                                    @endif
                                </td>
                                <td class="num"><a href="{{ route('dashboard.import.show', $import) }}" class="d-link">{{ $import->isDone() ? 'Bekijk' : 'Verder' }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.dashboard>
