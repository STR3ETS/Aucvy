<x-layouts.admin title="Categorieën">

    {{-- Aanmaken: naam plus optioneel een hoofdcategorie als ouder. --}}
    <form method="POST" action="{{ route('admin.categories.store') }}"
          class="mt-6 flex flex-wrap items-end gap-2 rounded-aj-s border border-aj-border bg-aj-surface p-4">
        @csrf
        <label class="min-w-52 flex-1">
            <span class="block text-xs font-bold text-aj-muted">Naam</span>
            <input type="text" name="name" required maxlength="80" value="{{ old('name') }}"
                   class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-white px-3 text-sm"
                   style="min-height:44px">
        </label>
        <label class="min-w-52">
            <span class="block text-xs font-bold text-aj-muted">Onder hoofdcategorie (optioneel)</span>
            <select name="parent_id" class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-white px-3 text-sm"
                    style="min-height:44px">
                <option value="">Geen - dit wordt een hoofdcategorie</option>
                @foreach ($roots as $root)
                    <option value="{{ $root->id }}" @selected(old('parent_id') == $root->id)>{{ $root->name }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-primary" style="height:44px;font-size:14px">Aanmaken</button>
    </form>

    <div class="mt-6 space-y-4">
        @foreach ($roots as $root)
            <section class="rounded-aj-s border border-aj-border bg-aj-surface" aria-label="{{ $root->name }}">
                <div class="flex flex-wrap items-center gap-3 border-b border-aj-border p-4">
                    <p class="flex-1 font-extrabold">{{ $root->name }}
                        <span class="text-xs font-normal text-aj-muted">/{{ $root->slug }} · {{ $root->lots_count }} {{ $root->lots_count === 1 ? 'kavel' : 'kavels' }}</span>
                    </p>
                    <a href="{{ route('admin.categories.schema', $root) }}" class="text-sm font-bold underline">Kenmerken{{ $root->specifications_schema ? ' ('.count($root->specifications_schema).')' : '' }}</a>
                    @if ($root->lots_count === 0 && $root->children->isEmpty())
                        <form method="POST" action="{{ route('admin.categories.destroy', $root) }}"
                              onsubmit="return confirm('{{ $root->name }} verwijderen?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm font-bold text-aj-error underline">Verwijderen</button>
                        </form>
                    @endif
                </div>
                <ul>
                    @forelse ($root->children as $child)
                        <li class="flex flex-wrap items-center gap-3 border-t border-aj-border px-4 py-2.5 first:border-t-0">
                            <p class="flex-1 text-sm font-semibold">{{ $child->name }}
                                <span class="text-xs font-normal text-aj-muted">/{{ $child->slug }} · {{ $child->lots_count }} {{ $child->lots_count === 1 ? 'kavel' : 'kavels' }}</span>
                            </p>
                            <a href="{{ route('admin.categories.schema', $child) }}" class="text-xs font-bold underline">Kenmerken{{ $child->specifications_schema ? ' ('.count($child->specifications_schema).')' : ' (van hoofd)' }}</a>
                            @if ($child->lots_count === 0)
                                <form method="POST" action="{{ route('admin.categories.destroy', $child) }}"
                                      onsubmit="return confirm('{{ $child->name }} verwijderen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-aj-error underline">Verwijderen</button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-3 text-sm text-aj-muted">Nog geen subcategorieën.</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</x-layouts.admin>
