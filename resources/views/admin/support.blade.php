<x-layouts.admin title="Support" :subtitle="$openCount.' '.($openCount === 1 ? 'open ticket' : 'open tickets').' · beantwoorden gaat per mail, sluiten hier'">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif

    <div class="mt-6 flex flex-wrap gap-2">
        <a href="{{ route('admin.support') }}" class="btn {{ $showClosed ? 'btn-ghost' : 'btn-dark' }}" style="height:38px;font-size:13px">Open</a>
        <a href="{{ route('admin.support', ['gesloten' => 1]) }}" class="btn {{ $showClosed ? 'btn-dark' : 'btn-ghost' }}" style="height:38px;font-size:13px">Alles, ook gesloten</a>
    </div>

    @if ($tickets->isEmpty())
        <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center text-sm text-aj-muted">Geen tickets{{ $showClosed ? '' : ' open' }}.</p>
    @else
        <ul class="mt-4 space-y-3">
            @foreach ($tickets as $ticket)
                <li class="rounded-aj-s border border-aj-border bg-aj-surface p-5" x-data="{ close: false }">
                    <div class="flex flex-wrap items-start gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold uppercase tracking-wide text-aj-muted">
                                #{{ $ticket->id }} · {{ $ticket->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}
                                @if ($ticket->order_number) · bestelling {{ $ticket->order_number }} @endif
                                @unless ($ticket->isOpen()) · gesloten door {{ $ticket->closer?->name ?? 'onbekend' }} @endunless
                            </p>
                            <h2 class="mt-1 text-lg font-extrabold">{{ $ticket->subject }}</h2>
                            <p class="text-sm text-aj-muted">
                                @if ($ticket->user)
                                    <a href="{{ route('admin.users.show', $ticket->user) }}" class="underline">{{ $ticket->name }}</a>
                                @else
                                    {{ $ticket->name }} (geen account)
                                @endif
                                · <a href="mailto:{{ $ticket->email }}?subject={{ rawurlencode('Re: '.$ticket->subject.' (#'.$ticket->id.')') }}" class="underline">{{ $ticket->email }}</a>
                            </p>
                            <p class="mt-3 whitespace-pre-line text-sm">{{ $ticket->message }}</p>
                            @if ($ticket->reply)
                                <p class="mt-3 rounded-aj-sm bg-aj-bg p-3 text-sm"><b>Notitie bij sluiten:</b> {{ $ticket->reply }}</p>
                            @endif
                        </div>
                        <div class="flex w-full flex-col gap-2 sm:w-auto">
                            @if ($ticket->isOpen())
                                <a href="mailto:{{ $ticket->email }}?subject={{ rawurlencode('Re: '.$ticket->subject.' (#'.$ticket->id.')') }}" class="btn btn-primary" style="height:40px;font-size:13px">Beantwoorden per mail</a>
                                <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="close = ! close">Sluiten</button>
                            @else
                                <form method="POST" action="{{ route('admin.support.reopen', $ticket) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-ghost w-full" style="height:40px;font-size:13px">Heropenen</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    @if ($ticket->isOpen())
                        <form method="POST" action="{{ route('admin.support.close', $ticket) }}" x-show="close" x-cloak class="mt-4 rounded-aj-sm border border-aj-border bg-aj-bg p-4">
                            @csrf
                            <label for="reply-{{ $ticket->id }}" class="block text-sm font-bold">Korte notitie (intern, optioneel)</label>
                            <textarea id="reply-{{ $ticket->id }}" name="reply" rows="2" maxlength="2000" class="mt-2 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm" placeholder="Bijvoorbeeld: beantwoord per mail, verwezen naar de geschilroute."></textarea>
                            <div class="mt-3 flex gap-2">
                                <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Ticket sluiten</button>
                                <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="close = false">Annuleren</button>
                            </div>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="mt-4">{{ $tickets->links() }}</div>
    @endif
</x-layouts.admin>
