@use('App\Lots\Documents\DocumentVisibility')

<x-layouts.public :title="$document->kind->label().' · '.$lot->title" :hero="false" :noindex="true">
    <div class="mx-auto max-w-7xl px-4 py-6">
        <p class="text-sm text-aj-muted">
            <a href="{{ route('lots.show', $lot) }}" class="underline">{{ $lot->title }}</a> · {{ $lot->public_id }} · Documenten
        </p>

        <div class="mt-3 grid gap-5 lg:grid-cols-[1fr_300px] lg:items-start">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface" aria-labelledby="doc-kop">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-aj-border px-4 py-3">
                    <div class="min-w-0">
                        <h1 id="doc-kop" class="truncate text-lg font-extrabold">{{ $document->kind->label() }}</h1>
                        <p class="text-xs text-aj-muted">{{ strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) }} · {{ number_format($document->size_bytes / 1024, 0, ',', '.') }} kB · hoort bij {{ $lot->title }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($fileUrl)
                            <a href="{{ $fileUrl }}&download=1" class="btn btn-ghost" style="height:36px;font-size:12px">Download</a>
                        @endif
                        @auth
                            @unless ($isSeller)
                                <a href="{{ route('lots.show', $lot) }}?vraag=documenten#vraag-kop" class="btn btn-primary" style="height:36px;font-size:12px">Vraag hierover stellen</a>
                            @endunless
                        @endauth
                    </div>
                </header>

                @if ($lock !== null)
                    <div class="grid min-h-[360px] place-items-center p-8 text-center">
                        <div class="max-w-md">
                            <svg class="mx-auto size-10 text-aj-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M5 9V7a5 5 0 0 1 10 0v2m-11 0h12v8H4z"/></svg>
                            <h2 class="mt-3 text-lg font-extrabold">{{ __('documents.locks.'.$lock.'.title') }}</h2>
                            <p class="mt-1 text-sm text-aj-muted">{{ __('documents.locks.'.$lock.'.text') }}</p>
                            @if ($lock === 'login')
                                <a href="{{ route('login') }}" class="btn btn-primary mt-4" style="height:40px;font-size:13px">Inloggen</a>
                            @endif
                        </div>
                    </div>
                @elseif ($document->isImage())
                    <div class="p-4">
                        <img src="{{ $fileUrl }}" alt="{{ $document->kind->label() }}" class="mx-auto max-h-[75vh] w-auto max-w-full rounded-aj-sm">
                    </div>
                @elseif ($document->isPdf())
                    {{-- De pdf-viewer van de browser, op het bestand achter de getekende URL (5 minuten). --}}
                    <iframe src="{{ $fileUrl }}#view=FitH" title="{{ $document->kind->label() }}" class="h-[75vh] w-full" sandbox="allow-same-origin allow-scripts"></iframe>
                @else
                    <div class="grid min-h-[300px] place-items-center p-8 text-center text-sm text-aj-muted">
                        <p>Dit bestandstype toont niet in de browser. <a href="{{ $fileUrl }}&download=1" class="font-semibold underline">Download het bestand</a>.</p>
                    </div>
                @endif
            </section>

            <aside class="space-y-4">
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="over">
                    <h2 id="over" class="text-sm font-extrabold">Over dit document</h2>
                    <dl class="mt-2 space-y-1 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Toegevoegd</dt><dd class="font-semibold">{{ $document->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">Zichtbaar voor</dt><dd class="font-semibold">{{ match ($document->visibility) { DocumentVisibility::AFTER_SALE => 'Winnaar, verkoper, medewerkers', DocumentVisibility::PRIVATE => 'Verkoper, Lex, medewerkers', default => 'Iedereen' } }}</dd></div>
                        @if ($document->wasCheckedByIvy())
                            <div class="flex justify-between gap-3"><dt class="text-aj-muted">Controle</dt><dd class="font-semibold text-aj-success-text">{{ __('documents.checked_by_lex') }}</dd></div>
                        @endif
                    </dl>
                    @if ($document->wasCheckedByIvy() && ! empty($document->ai_findings['summary']))
                        <p class="mt-3 rounded-aj-sm border border-aj-border bg-aj-bg p-3 text-xs"><span class="font-bold">Wat Lex eruit haalt:</span> {{ $document->ai_findings['summary'] }}</p>
                    @endif
                </section>

                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="alle">
                    <h2 id="alle" class="text-sm font-extrabold">Alle documenten</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($siblings as $sibling)
                            <li>
                                <a href="{{ route('documents.view', $sibling['document']) }}" @class(['flex items-center justify-between gap-2 rounded-aj-sm px-2 py-1.5 hover:bg-aj-bg', 'bg-aj-bg font-bold' => $sibling['document']->is($document)])>
                                    <span class="truncate">{{ $sibling['document']->kind->label() }}</span>
                                    <span class="shrink-0 text-xs text-aj-muted">{{ $sibling['lock'] === null ? strtoupper(pathinfo($sibling['document']->original_filename, PATHINFO_EXTENSION)) : $sibling['document']->visibilityLabel() }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.public>
