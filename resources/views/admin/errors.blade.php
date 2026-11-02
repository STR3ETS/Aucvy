<x-layouts.admin title="Foutlog" :subtitle="$openCount.' '.($openCount === 1 ? 'open fout' : 'open fouten').': dezelfde soort fout telt op in één regel'">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif

    <nav class="mt-6 flex gap-2 text-sm" aria-label="Filter">
        <a href="{{ route('admin.errors') }}" @class(['rounded-full px-3 py-1 font-bold', 'bg-aj-navy text-white' => ! $showResolved, 'bg-aj-surface border border-aj-border' => $showResolved])>Open</a>
        <a href="{{ route('admin.errors', ['status' => 'afgehandeld']) }}" @class(['rounded-full px-3 py-1 font-bold', 'bg-aj-navy text-white' => $showResolved, 'bg-aj-surface border border-aj-border' => ! $showResolved])>Afgehandeld</a>
    </nav>

    @if ($events->isEmpty())
        <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center text-sm text-aj-muted">
            {{ $showResolved ? 'Nog niets afgehandeld.' : 'Geen open fouten.' }}
        </p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($events as $event)
                <li class="rounded-aj-s border border-aj-border bg-aj-surface p-5" x-data="{ resolve: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold uppercase tracking-wide text-aj-muted">{{ class_basename($event->class) }} · {{ $event->file }}:{{ $event->line }}</p>
                            <p class="mt-1 break-words font-mono text-sm">{{ $event->message }}</p>
                            <p class="mt-2 text-xs text-aj-muted">
                                <span class="tabular font-bold text-aj-text">{{ $event->occurrences }}×</span>
                                · eerst {{ $event->first_seen_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM HH:mm') }}
                                · laatst {{ $event->last_seen_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}
                                @if ($event->url)
                                    · {{ $event->method }} {{ \Illuminate\Support\Str::limit($event->url, 80) }}
                                @endif
                                @if ($event->lastUser)
                                    · bij {{ $event->lastUser->name }}
                                @endif
                            </p>
                            @if (! $event->isOpen())
                                <p class="mt-2 text-xs text-aj-success-text">
                                    Afgehandeld door {{ $event->resolver?->name ?? 'onbekend' }} {{ $event->resolved_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM HH:mm') }}{{ $event->resolution_note ? ': '.$event->resolution_note : '' }}
                                </p>
                            @endif
                        </div>
                        @if ($event->isOpen())
                            <button type="button" class="btn btn-dark" style="height:36px;font-size:13px" @click="resolve = ! resolve" :aria-expanded="resolve">Afhandelen</button>
                        @else
                            <form method="POST" action="{{ route('admin.errors.reopen', $event) }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px">Heropenen</button>
                            </form>
                        @endif
                    </div>
                    @if ($event->isOpen())
                        <form method="POST" action="{{ route('admin.errors.resolve', $event) }}" x-show="resolve" x-cloak class="mt-4 flex flex-wrap items-end gap-2">
                            @csrf
                            <label class="min-w-0 flex-1">
                                <span class="text-sm font-bold">Wat is er gedaan?</span>
                                <input type="text" name="note" maxlength="500" placeholder="Bijvoorbeeld: opgelost in de release van vandaag"
                                       class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:40px">
                            </label>
                            <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px">Markeer als afgehandeld</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $events->links() }}</div>
    @endif
</x-layouts.admin>
