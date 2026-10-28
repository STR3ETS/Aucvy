<x-layouts.admin title="Doorverwijzingen" :subtitle="$redirects->total().' '.($redirects->total() === 1 ? 'doorverwijzing' : 'doorverwijzingen').': een oud pad zonder pagina stuurt door in plaats van een 404'">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.redirects.store') }}" class="mt-6 grid gap-3 rounded-aj-s border border-aj-border bg-aj-surface p-5 sm:grid-cols-[1fr_1fr_120px_auto] sm:items-end">
        @csrf
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Oud pad</span>
            <input type="text" name="from_path" value="{{ old('from_path') }}" required placeholder="/oude-pagina" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:42px">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Nieuw adres</span>
            <input type="text" name="to_url" value="{{ old('to_url') }}" required placeholder="/veilingen of https://..." class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:42px">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Soort</span>
            <select name="status" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:42px">
                <option value="301" @selected(old('status', '301') === '301')>301 definitief</option>
                <option value="302" @selected(old('status') === '302')>302 tijdelijk</option>
            </select>
        </label>
        <button type="submit" class="btn btn-dark" style="height:42px;font-size:13px">Opslaan</button>
    </form>

    <form method="GET" class="mt-6 flex flex-wrap gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Zoek op pad of adres" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:40px">
        <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Zoeken</button>
    </form>

    @if ($redirects->isEmpty())
        <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center text-sm text-aj-muted">Nog geen doorverwijzingen.</p>
    @else
        <div class="mt-4 overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface p-5">
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
