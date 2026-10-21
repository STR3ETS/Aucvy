@php
    $subjectLabel = fn (string $type): string => class_basename($type);
    $subjectUrl = function (\App\Models\AuditLog $entry): ?string {
        return match (class_basename($entry->subject_type)) {
            'Lot' => route('admin.lots.show', $entry->subject_id),
            'Order' => route('admin.orders.show', $entry->subject_id),
            'User' => route('admin.users.show', $entry->subject_id),
            'Company' => route('admin.companies.show', $entry->subject_id),
            default => null,
        };
    };
    $compact = fn (?array $data): string => $data === null ? '' : collect($data)
        ->map(fn ($value, $key) => $key.': '.(is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)))
        ->join(' · ');
@endphp

<x-layouts.admin title="Auditlog" subtitle="Elke overgang die geld, status of eigendom raakt. Alleen lezen: de rijen zijn bewijs.">
    <form method="GET" class="mt-6 flex flex-wrap items-end gap-2" x-data @change="if ($event.target.tagName === 'SELECT') $el.requestSubmit()">
        <label><span class="sr-only">Actie</span>
            <select name="action" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                <option value="">Alle acties</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </label>
        <label><span class="sr-only">Onderwerp</span>
            <select name="subject" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
                <option value="">Alle onderwerpen</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject }}" @selected(($filters['subject'] ?? null) === $subject)>{{ $subjectLabel($subject) }}</option>
                @endforeach
            </select>
        </label>
        <label class="w-full max-w-56"><span class="sr-only">Wie</span>
            <input type="search" name="actor" value="{{ $filters['actor'] ?? '' }}" placeholder="Naam of e-mail van wie"
                   class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
        </label>
        <label><span class="sr-only">Vanaf</span>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
        </label>
        <label><span class="sr-only">Tot en met</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:44px">
        </label>
        <button type="submit" class="btn btn-ghost" style="height:44px;font-size:13px">Filteren</button>
        @if ($filters !== [])
            <a href="{{ route('admin.auditlog') }}" class="btn btn-ghost" style="height:44px;font-size:13px">Wissen</a>
        @endif
    </form>

    <div class="mt-4 overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface">
        <table class="admin-table">
            <thead><tr><th>Wanneer</th><th>Actie</th><th>Wie</th><th>Onderwerp</th><th>Van</th><th>Naar</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="tabular whitespace-nowrap text-aj-muted">{{ $entry->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY, HH:mm:ss') }}</td>
                        <td><code class="text-xs font-bold">{{ $entry->action }}</code></td>
                        <td>
                            @if ($entry->actor)
                                <a href="{{ route('admin.users.show', $entry->actor) }}" class="underline">{{ $entry->actor->name }}</a>
                            @else
                                <span class="text-aj-muted">systeem</span>
                            @endif
                        </td>
                        <td>
                            @if ($url = $subjectUrl($entry))
                                <a href="{{ $url }}" class="underline">{{ $subjectLabel($entry->subject_type) }} #{{ $entry->subject_id }}</a>
                            @else
                                {{ $subjectLabel($entry->subject_type) }} #{{ $entry->subject_id }}
                            @endif
                        </td>
                        <td class="max-w-[28ch] truncate text-xs text-aj-muted" title="{{ $compact($entry->before) }}">{{ $compact($entry->before) }}</td>
                        <td class="max-w-[40ch] truncate text-xs" title="{{ $compact($entry->after) }}">{{ $compact($entry->after) }}</td>
                        <td class="tabular text-xs text-aj-muted">{{ $entry->ip ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-sm text-aj-muted">Geen regels voor deze filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-layouts.admin>
