@use('App\Models\TicketMessage')

<x-layouts.admin :title="$ticket->subject" :subtitle="$ticket->number.' · '.$ticket->typeLabel().' · '.$ticket->priorityLabel().' · '.($ticket->isOpen() ? ($ticket->isLate() ? 'te laat' : 'open') : 'gesloten')">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.service') }}" class="underline">Tickets</a> · {{ $ticket->number }}
        @if ($ticket->user) · <a href="{{ route('admin.customers.show', $ticket->user) }}" class="underline">klantkaart</a> @endif
        @if ($ticket->order) · <a href="{{ route('admin.orders.show', $ticket->order) }}" class="underline">bestelling {{ $ticket->order->order_number }}</a> @endif
        @if ($ticket->lot) · <a href="{{ route('admin.lots.dossier', $ticket->lot) }}" class="underline">dossier {{ $ticket->lot->public_id }}</a> @endif
    </p>

    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
        <div class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface" aria-label="Berichten">
                <ol class="space-y-4 p-4">
                    @foreach ($ticket->messages as $message)
                        @continue($message->draft)
                        <li @class(['rounded-aj-sm p-3 text-sm', 'border border-aj-border' => $message->sender_type === TicketMessage::FROM_CUSTOMER, 'border border-aj-blue/40 bg-aj-blue/5' => $message->sender_type === TicketMessage::FROM_STAFF && ! $message->internal, 'border border-dashed border-aj-border-strong bg-aj-bg' => $message->internal])>
                            <span class="text-xs font-bold text-aj-muted">{{ $message->internal ? 'Interne notitie' : ($message->sender_type === TicketMessage::FROM_CUSTOMER ? $ticket->name : 'Aucvy') }}{{ $message->sender ? ' · '.$message->sender->name : '' }} · {{ $message->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span>
                            <span class="mt-1 block whitespace-pre-line">{{ $message->body }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($ticket->isOpen())
                @can('admin-area', ['service', 'write'])
                    <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="antwoord" x-data="{ mode: 'reply' }">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 id="antwoord" class="text-sm font-extrabold">Antwoorden</h2>
                            <form method="POST" action="{{ route('admin.service.draft', $ticket) }}">@csrf<button type="submit" class="btn btn-ghost" style="height:32px;font-size:12px"><x-ui.agent-avatar agent="ivy" class="size-4" /> Concept van Ivy</button></form>
                        </div>
                        @if ($draft)
                            <p class="mt-2 rounded-aj-sm border border-aj-navy/20 bg-aj-navy/5 p-3 text-xs"><span class="font-bold">Concept van Ivy</span> (nog niet verstuurd): {{ $draft->body }}</p>
                        @endif
                        <div class="mt-3 flex gap-2 text-sm">
                            <button type="button" class="rounded-full px-3 py-1 font-bold" :class="mode === 'reply' ? 'bg-aj-navy text-white' : 'border border-aj-border'" @click="mode = 'reply'">Antwoord aan de klant</button>
                            <button type="button" class="rounded-full px-3 py-1 font-bold" :class="mode === 'note' ? 'bg-aj-navy text-white' : 'border border-aj-border'" @click="mode = 'note'">Interne notitie</button>
                        </div>
                        <form method="POST" action="{{ route('admin.service.reply', $ticket) }}" x-show="mode === 'reply'" class="mt-3">
                            @csrf
                            <textarea name="body" rows="5" required class="w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm" placeholder="Je antwoord gaat per mail naar {{ $ticket->email }}">{{ old('body', $draft?->body) }}</textarea>
                            <button type="submit" class="btn btn-primary mt-2" style="height:40px;font-size:13px">Versturen</button>
                        </form>
                        <form method="POST" action="{{ route('admin.service.note', $ticket) }}" x-show="mode === 'note'" x-cloak class="mt-3">
                            @csrf
                            <textarea name="body" rows="3" required class="w-full rounded-aj-sm border border-aj-border-strong p-3 text-sm" placeholder="Alleen voor het team"></textarea>
                            <button type="submit" class="btn btn-dark mt-2" style="height:40px;font-size:13px">Notitie opslaan</button>
                        </form>
                    </section>
                @endcan
            @endif
        </div>

        <aside class="space-y-4">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="gegevens">
                <h2 id="gegevens" class="text-sm font-extrabold">Ticket</h2>
                <dl class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Van</dt><dd class="font-semibold">{{ $ticket->name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">E-mail</dt><dd class="truncate font-semibold">{{ $ticket->email }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Binnen via</dt><dd class="font-semibold">{{ __('admin.tickets.sources.'.$ticket->source) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Eerste reactie</dt><dd class="font-semibold">{{ $ticket->first_response_at ? 'na '.$ticket->created_at->diffForHumans($ticket->first_response_at, true) : 'nog niet · uiterlijk '.$ticket->first_response_due_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</dd></div>
                </dl>
                @can('admin-area', ['service', 'write'])
                    <form method="POST" action="{{ route('admin.service.update', $ticket) }}" class="mt-3 space-y-2 text-sm">
                        @csrf
                        @method('PATCH')
                        <select name="type" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px"><option value="question" @selected($ticket->type === 'question')>Vraag</option><option value="complaint" @selected($ticket->type === 'complaint')>Klacht</option></select>
                        <select name="priority" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px">@foreach (['low', 'normal', 'high'] as $priority)<option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ __('admin.tickets.priorities.'.$priority) }}</option>@endforeach</select>
                        <select name="assigned_to" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px"><option value="">Niet toegewezen</option>@foreach ($staff as $member)<option value="{{ $member->id }}" @selected($ticket->assigned_to === $member->id)>{{ $member->name }}</option>@endforeach</select>
                        <button type="submit" class="btn btn-ghost w-full justify-center" style="height:34px;font-size:12px">Bijwerken</button>
                    </form>
                @endcan
            </section>

            @can('admin-area', ['service', 'write'])
                @if ($ticket->isOpen())
                    <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="acties">
                        <h2 id="acties" class="text-sm font-extrabold">Acties</h2>
                        <div class="mt-2 space-y-2">
                            <form method="POST" action="{{ route('admin.service.resolve', $ticket) }}">@csrf<button type="submit" class="btn btn-dark w-full justify-center" style="height:38px;font-size:13px">Opgelost</button></form>
                            @if ($ticket->order && $ticket->order->disputes->isEmpty())
                                <form method="POST" action="{{ route('admin.service.dispute', $ticket) }}" x-data="{ open: false }">
                                    @csrf
                                    <button type="button" class="btn btn-ghost w-full justify-center" style="height:38px;font-size:13px" @click="open = ! open">Doorzetten naar geschil</button>
                                    <div x-show="open" x-cloak class="mt-2">
                                        <textarea name="reason" rows="2" required minlength="10" class="w-full rounded-aj-sm border border-aj-border-strong p-2 text-sm" placeholder="Waarom, voor beide partijen"></textarea>
                                        <button type="submit" class="btn btn-dark mt-2 w-full justify-center" style="height:36px;font-size:12px">Geschil starten</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </section>
                @endif
            @endcan
        </aside>
    </div>
</x-layouts.admin>
