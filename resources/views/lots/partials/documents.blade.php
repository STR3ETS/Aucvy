{{--
    Documenten met het oogje (briefing nieuwe functies). Een openbaar document
    opent in de viewer; na gunning en privé tonen een slotregel, met de
    controle van Lex als die er is. Opmaak: de lijst uit het ontwerp van
    november.
--}}
@php
    $access = app(\App\Lots\Documents\DocumentAccess::class);
    $viewer = auth()->user();
    $mayAsk = $viewer !== null && ! $lot->isManagedBy($viewer);
@endphp

@if ($lot->documents->isNotEmpty())
    <section id="documenten" aria-labelledby="documenten-kop">
        <h2 id="documenten-kop" class="d-h2-page">Documenten</h2>
        <p class="d-sub" style="margin-bottom:10px">Bekijk ze hier met het oogje. Vraag je iets over een document, dan komt dat in de chat bij deze kavel.</p>
        <div class="d-docs">
            @foreach ($lot->documents as $document)
                @php $lock = $access->lockReason($viewer, $document); @endphp
                @if ($lock === null)
                    <div class="d-doc">
                        <span class="d-doc-icon" aria-hidden="true"><x-v4.icon name="file" :size="18" stroke="2" /></span>
                        <span class="d-doc-text">
                            <strong>{{ $document->kind->label() }}</strong>
                            <span>{{ strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) ?: 'Bestand' }} · {{ number_format($document->size_bytes / 1024, 0, ',', '.') }} kB</span>
                        </span>
                        @if ($document->wasCheckedByIvy())
                            <span class="d-tag tone-ok">{{ __('documents.checked_by_lex') }}</span>
                        @endif
                        <a href="{{ route('documents.view', $document) }}" class="d-btn d-btn-sm d-btn-sq" aria-label="Bekijk {{ $document->kind->label() }}">
                            <x-v4.icon name="eye" :size="15" stroke="2.2" /> Bekijken
                        </a>
                        @if ($mayAsk)
                            <a href="{{ route('lots.show', $lot) }}?vraag=documenten#vraag-kop" class="d-btn d-btn-secondary d-btn-sm d-btn-sq">Vraag stellen</a>
                        @endif
                    </div>
                @else
                    <div class="d-doc">
                        <span class="d-doc-icon is-locked" aria-hidden="true">
                            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 9V7a5 5 0 0 1 10 0v2m-11 0h12v8H4z"/></svg>
                        </span>
                        <span class="d-doc-text">
                            <strong>{{ $document->kind->label() }}</strong>
                            <span>{{ __('documents.locks.'.$lock.'.title') }}{{ $document->wasCheckedByIvy() ? ' · '.__('documents.checked_by_lex') : '' }}</span>
                        </span>
                        <span class="d-tag tone-grey">{{ $document->visibilityLabel() }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    </section>
@endif
