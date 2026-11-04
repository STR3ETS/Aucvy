<x-layouts.dashboard title="Berichten" eyebrow="Berichten" intro="Elk gesprek hoort bij één kavel en blijft bewaard in het dossier.">
    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif

    @if ($conversations->isEmpty())
        <p class="rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-8 text-center text-sm text-aj-muted">
            Nog geen gesprekken. Stel een vraag op een kavelpagina, of wacht op een bericht van een koper.
        </p>
    @else
        <ul class="divide-y divide-aj-border rounded-aj-s border border-aj-border bg-aj-surface">
            @foreach ($conversations as $conversation)
                @php $unread = $conversation->isUnreadFor($user); @endphp
                <li>
                    <a href="{{ route('messages.show', $conversation) }}" class="flex gap-4 px-4 py-3 hover:bg-aj-blue/5">
                        <span class="block h-14 w-20 flex-none overflow-hidden rounded-aj-sm border border-aj-border bg-aj-navy">
                            @if ($conversation->lot->media->isNotEmpty())
                                <img src="{{ $conversation->lot->media->first()->path }}" alt="" class="h-full w-full object-cover">
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-baseline justify-between gap-x-3">
                                <span @class(['truncate text-sm', 'font-extrabold' => $unread, 'font-semibold' => ! $unread])>{{ $conversation->lot->title }}</span>
                                <span class="text-xs text-aj-muted">{{ $conversation->last_message_at?->diffForHumans() ?? 'nieuw' }}</span>
                            </span>
                            <span class="block truncate text-xs text-aj-muted">
                                {{ $conversation->counterpartLabel($user) }} · {{ $conversation->kindLabel() }}@if ($conversation->partLabel()) · {{ $conversation->partLabel() }}@endif
                                @if ($conversation->hasOperator()) · medewerker leest mee @endif
                                @if ($conversation->isBlocked()) · gesloten @endif
                            </span>
                        </span>
                        @if ($unread)
                            <span class="mt-1 size-2.5 flex-none rounded-full bg-aj-blue" aria-label="Ongelezen"></span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-4">{{ $conversations->links() }}</div>
    @endif
</x-layouts.dashboard>
