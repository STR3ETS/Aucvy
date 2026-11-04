@if ($lot->documents->isEmpty())
    <p class="mt-4 rounded-aj-sm border border-dashed border-aj-border-strong p-5 text-center text-sm text-aj-muted">Nog geen documenten. Toevoegen kan via <a href="{{ route('dashboard.lots.edit', $lot) }}" class="font-semibold underline">Bewerken</a>.</p>
@else
    <ul class="mt-4 space-y-2">
        @foreach ($lot->documents as $document)
            <li class="flex flex-wrap items-center gap-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold">{{ $document->kind->label() }}</span>
                    <span class="block truncate text-xs text-aj-muted">{{ $document->original_filename }} · {{ number_format($document->size_bytes / 1024, 0, ',', '.') }} kB</span>
                </span>
                @if ($document->wasCheckedByIvy())
                    <span class="flex shrink-0 items-center gap-1.5 text-xs font-bold text-aj-success-text"><x-ui.agent-avatar agent="lex" class="size-5" /> Gecontroleerd door Lex</span>
                @endif
                @if (isset($document->visibility))
                    <span class="rounded-full bg-aj-surface px-2 py-0.5 text-xs font-bold">{{ __('documents.visibility.'.$document->visibility) }}</span>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('documents.view'))
                    <a href="{{ route('documents.view', $document) }}" class="btn btn-ghost" style="height:34px;font-size:12px">Bekijken</a>
                @endif
            </li>
        @endforeach
    </ul>
@endif
