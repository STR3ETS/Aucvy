<x-layouts.admin title="Doorverwijzingen" :subtitle="$redirects->total().' '.($redirects->total() === 1 ? 'doorverwijzing' : 'doorverwijzingen').': een oud pad zonder pagina stuurt door in plaats van een 404'">
    @if ($errors->any())
        <div role="alert" class="mt-4 d-soft is-err">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.redirects.store') }}" class="mt-6 grid gap-3 d-card sm:grid-cols-[1fr_1fr_120px_auto] sm:items-end">
        @csrf
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Oud pad</span>
            <input type="text" name="from_path" value="{{ old('from_path') }}" required placeholder="/oude-pagina" class="d-input" style="min-height:42px">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Nieuw adres</span>
            <input type="text" name="to_url" value="{{ old('to_url') }}" required placeholder="/veilingen of https://..." class="d-input" style="min-height:42px">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Soort</span>
            <select name="status" class="d-select" style="min-height:42px">
                <option value="301" @selected(old('status', '301') === '301')>301 definitief</option>
                <option value="302" @selected(old('status') === '302')>302 tijdelijk</option>
            </select>
        </label>
        <button type="submit" class="d-btn d-btn-dark d-btn-sq" style="height:42px;font-size:13px">Opslaan</button>
    </form>

    <form method="GET" class="mt-6 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek op pad of adres" class="d-input" style="min-height:40px">
        <button type="submit" class="d-btn d-btn-dark d-btn-sq" style="height:40px;font-size:13px">Zoeken</button>
    </form>

    @if ($redirects->isEmpty())
        <p class="mt-4 d-card d-admin-empty">Nog geen doorverwijzingen.</p>
    @else
        <div class="mt-4 overflow-x-auto d-card">
            <table class="admin-table">
                <thead><tr><th>Oud pad</th><th>Nieuw adres</th><th>Soort</th><th>Gebruikt</th><th>Laatst</th><th></th></tr></thead>
                <tbody>
                    @foreach ($redirects as $redirect)
                        <tr>
                            <td class="font-bold">{{ $redirect->from_path }}</td>
                            <td class="max-w-[40ch] truncate"><a href="{{ $redirect->to_url }}" class="hover:underline" target="_blank" rel="noopener">{{ $redirect->to_url }}</a></td>
                            <td class="tabular">{{ $redirect->status }}</td>
                            <td class="tabular">{{ $redirect->hits }}</td>
                            <td class="whitespace-nowrap text-aj-muted">{{ $redirect->last_hit_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.redirects.destroy', $redirect) }}" onsubmit="return confirm('Deze doorverwijzing verwijderen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-aj-error underline">Verwijderen</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $redirects->links() }}</div>
    @endif
</x-layouts.admin>
