@use('App\Lots\Documents\DocumentVisibility')

@if ($lot->documents->isEmpty())
    <p class="mt-4 rounded-aj-sm border border-dashed border-aj-border-strong p-5 text-center text-sm text-aj-muted">Nog geen documenten. Toevoegen kan via <a href="{{ route('dashboard.lots.edit', $lot) }}" class="font-semibold underline">Bewerken</a>.</p>
@else
    <ul class="mt-4 space-y-2">
        @foreach ($lot->documents as $document)
            <li class="rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{ $document->kind->label() }}</span>
                        <span class="block truncate text-xs text-aj-muted">{{ $document->original_filename }} · {{ number_format($document->size_bytes / 1024, 0, ',', '.') }} kB · {{ $document->view_count }}× bekeken</span>
                    </span>
                    @if ($document->wasCheckedByIvy())
                        <span class="flex shrink-0 items-center gap-1.5 text-xs font-bold text-aj-success-text"><x-ui.agent-avatar agent="lex" class="size-5" /> {{ __('documents.checked_by_lex') }}</span>
                    @endif
                    <a href="{{ route('documents.view', $document) }}" class="btn btn-ghost" style="height:34px;font-size:12px">Bekijken</a>
                </div>
                {{-- Wie mag het zien: openbaar, na gunning of privé. Een privé document openbaar maken geeft een waarschuwing. --}}
                <form method="POST" action="{{ route('dashboard.lots.documents.visibility', [$lot, $document]) }}" class="mt-2 flex flex-wrap items-center gap-2" x-data="{ level: @js($document->visibility) }">
                    @csrf
                    @method('PATCH')
                    <label class="text-xs font-bold" for="vis-{{ $document->id }}">Zichtbaar voor</label>
                    <select id="vis-{{ $document->id }}" name="visibility" x-model="level" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-xs" style="min-height:32px">
                        @foreach (DocumentVisibility::ALL as $level)
                            <option value="{{ $level }}">{{ DocumentVisibility::label($level) }}: {{ __('documents.visibility_help.'.$level) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-ghost" style="height:32px;font-size:12px" x-show="level !== @js($document->visibility)">Opslaan</button>
                    <span class="w-full text-xs text-aj-warn-text" x-show="level === 'public' && @js($document->visibility) !== 'public'" x-cloak>{{ __('documents.public_warning') }}</span>
                </form>
            </li>
        @endforeach
    </ul>
@endif
