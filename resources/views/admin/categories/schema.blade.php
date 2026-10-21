<x-layouts.admin :title="'Kenmerken van '.$category->name" :subtitle="$category->isRoot() ? 'Hoofdcategorie: subcategorieën zonder eigen schema nemen dit over.' : 'Subcategorie van '.$category->parent->name.($category->specifications_schema === null ? '; neemt nu het schema van de hoofdcategorie over.' : '.')">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">
            <ul class="list-disc pl-4">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <p class="mt-4 text-sm text-aj-muted"><a class="underline" href="{{ route('admin.categories') }}">Terug naar categorieën</a></p>

    <form method="POST" action="{{ route('admin.categories.schema.update', $category) }}" class="mt-4 rounded-aj-s border border-aj-border bg-aj-surface p-5"
          x-data="{
              fields: {{ Js::from($fields) }},
              types: {{ Js::from($types) }},
              add() { this.fields.push({ key: '', label: '', type: 'string', unit: '', options: '', required: false }); },
              remove(index) { this.fields.splice(index, 1); },
              move(index, delta) { const target = index + delta; if (target < 0 || target >= this.fields.length) return; const [row] = this.fields.splice(index, 1); this.fields.splice(target, 0, row); },
              slug(row) { if (! row.key) { row.key = row.label.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40); } },
          }">
        @csrf @method('PUT')
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-sm font-extrabold">Velden in de wizard</h2>
            <p class="text-xs text-aj-muted">De volgorde hier is de volgorde in stap 4. Keuzelijst: opties gescheiden door komma's. De sleutel is wat Ivy en de export gebruiken; verander hem niet bij bestaande kavels.</p>
        </div>

        <table class="admin-table mt-3" x-show="fields.length" x-cloak>
            <thead><tr><th style="width:18%">Label</th><th style="width:16%">Sleutel</th><th style="width:12%">Type</th><th style="width:9%">Eenheid</th><th>Opties</th><th style="width:7%">Verplicht</th><th style="width:12%"></th></tr></thead>
            <tbody>
                <template x-for="(row, index) in fields" :key="index">
                    <tr>
                        <td><input type="text" :name="'fields[' + index + '][label]'" x-model="row.label" @blur="slug(row)" required maxlength="60" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm" style="min-height:36px"></td>
                        <td><input type="text" :name="'fields[' + index + '][key]'" x-model="row.key" required maxlength="40" pattern="[a-z][a-z0-9_]{1,39}" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 font-mono text-xs" style="min-height:36px"></td>
                        <td>
                            <select :name="'fields[' + index + '][type]'" x-model="row.type" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-1 text-sm" style="min-height:36px">
                                <template x-for="(label, value) in types" :key="value"><option :value="value" x-text="label" :selected="row.type === value"></option></template>
                            </select>
                        </td>
                        <td><input type="text" :name="'fields[' + index + '][unit]'" x-model="row.unit" maxlength="12" placeholder="kg" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm" style="min-height:36px"></td>
                        <td><input type="text" :name="'fields[' + index + '][options]'" x-model="row.options" :disabled="row.type !== 'select'" placeholder="Elektrisch, Diesel, LPG" class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm disabled:bg-aj-bg" style="min-height:36px"></td>
                        <td class="text-center"><input type="checkbox" :name="'fields[' + index + '][required]'" value="1" x-model="row.required"></td>
                        <td class="whitespace-nowrap text-right">
                            <button type="button" class="btn btn-ghost" style="height:30px;padding:0 8px;font-size:12px" @click="move(index, -1)" aria-label="Omhoog">↑</button>
                            <button type="button" class="btn btn-ghost" style="height:30px;padding:0 8px;font-size:12px" @click="move(index, 1)" aria-label="Omlaag">↓</button>
                            <button type="button" class="btn btn-ghost" style="height:30px;padding:0 8px;font-size:12px" @click="remove(index)" aria-label="Verwijderen">×</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <p class="mt-3 rounded-aj-sm border border-dashed border-aj-border-strong p-4 text-center text-sm text-aj-muted" x-show="! fields.length" x-cloak>
            Geen eigen velden. @if (! $category->isRoot()) De wizard gebruikt dan het schema van {{ $category->parent->name }}. @else De wizard toont dan alleen vrije kenmerken. @endif
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="btn btn-ghost" style="height:38px;font-size:13px" @click="add()">Veld toevoegen</button>
            <button type="submit" class="btn btn-primary" style="height:38px;font-size:13px">Schema opslaan</button>
            @if (! $category->isRoot() && $category->specifications_schema !== null)
                <button type="submit" name="inherit" value="1" class="btn btn-ghost ml-auto" style="height:38px;font-size:13px" onclick="return confirm('Eigen schema weggooien en dat van de hoofdcategorie gebruiken?')">Schema van hoofdcategorie gebruiken</button>
            @endif
        </div>
    </form>

    @if ($inherited !== null)
        <section class="mt-6 rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Overgenomen schema">
            <h2 class="text-sm font-extrabold">Nu in gebruik via {{ $category->parent->name }}</h2>
            <ul class="mt-2 flex flex-wrap gap-2 text-xs">
                @foreach ($inherited as $field)
                    <li class="rounded-full border border-aj-border px-2.5 py-1">{{ $field['label'] }} <span class="text-aj-muted">· {{ $types[$field['type']] ?? $field['type'] }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.admin>
