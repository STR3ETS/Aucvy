{{--
    Documenten met het oogje (briefing nieuwe functies). Een openbaar document
    opent in de viewer; na gunning en privé tonen een slotkaart, met de
    controle van Lex als die er is.
--}}
@php
    $access = app(\App\Lots\Documents\DocumentAccess::class);
    $viewer = auth()->user();
@endphp

@if ($lot->documents->isNotEmpty())
    <section id="documenten" aria-labelledby="documenten-kop">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="documenten-kop" class="text-lg font-extrabold">Documenten</h2>
            @auth
                @unless ($lot->isManagedBy($viewer))
                    <a href="{{ route('lots.show', $lot) }}?vraag=documenten#vraag-kop" class="text-sm font-semibold underline">Vraag stellen</a>
                @endunless
            @endauth
        </div>
        <ul class="mt-2 space-y-2">
            @foreach ($lot->documents as $document)
                @php $lock = $access->lockReason($viewer, $document); @endphp
                <li class="flex flex-wrap items-center gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">
                    <svg class="size-5 shrink-0 text-aj-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        @if ($lock === null)
                            <path d="M11.5 2.5h-6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-10m-4-4 4 4m-4-4v4h4"/>
                        @else
                            <path d="M5 9V7a5 5 0 0 1 10 0v2m-11 0h12v8H4z"/>
                        @endif
                    </svg>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{ $document->kind->label() }}</span>
                        <span class="block truncate text-xs text-aj-muted">
                            @if ($lock === null)
                                {{ $document->original_filename }} · {{ number_format($document->size_bytes / 1024, 0, ',', '.') }} kB
                            @else
                                {{ __('documents.locks.'.$lock.'.title') }}
                            @endif
                        </span>
                    </span>
                    @if ($document->wasCheckedByIvy())
                        <span class="flex shrink-0 items-center gap-1.5 text-xs font-bold text-aj-success-text">
                            <x-ui.agent-avatar agent="lex" class="size-5" />
                            {{ __('documents.checked_by_lex') }}
                        </span>
                    @endif
                    @if ($lock === null)
                        <a href="{{ route('documents.view', $document) }}" class="btn btn-ghost shrink-0" style="height:34px;font-size:12px" aria-label="Bekijk {{ $document->kind->label() }}">
                            <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 10s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5z"/><circle cx="10" cy="10" r="2.5"/></svg>
                            Bekijken
                        </a>
                    @else
                        <span class="rounded-full bg-aj-bg px-2 py-0.5 text-xs font-bold text-aj-muted">{{ $document->visibilityLabel() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
