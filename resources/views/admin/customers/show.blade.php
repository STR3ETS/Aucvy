@use('App\Support\Money\Money')

<x-layouts.admin :title="$customer->name" :subtitle="($customer->company ? 'Zakelijk · '.$customer->company->name : 'Particulier').' · '.$customer->kyc_status->label().' · klant sinds '.$customer->created_at->isoFormat('D MMM YYYY')">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.customers') }}" class="d-tablelink">Klanten</a> · klantkaart ·
        <a href="{{ route('admin.users.show', $customer) }}" class="d-tablelink">accountacties</a>
        @if ($customer->company) · <a href="{{ route('admin.companies.show', $customer->company) }}" class="d-tablelink">bedrijf</a> @endif
    </p>


    <div class="mt-4 grid gap-3 sm:grid-cols-4">
        <div class="d-card"><p class="text-xs font-bold text-aj-muted">Gekocht</p><p class="tabular mt-1 text-xl font-extrabold">{{ Money::format($bought) }}</p></div>
        <div class="d-card"><p class="text-xs font-bold text-aj-muted">Verkocht</p><p class="tabular mt-1 text-xl font-extrabold">{{ Money::format($sold) }}</p></div>
        <div class="d-card"><p class="text-xs font-bold text-aj-muted">Kavels</p><p class="tabular mt-1 text-xl font-extrabold">{{ $customer->lots_count }}</p></div>
        <div class="d-card"><p class="text-xs font-bold text-aj-muted">Biedingen</p><p class="tabular mt-1 text-xl font-extrabold">{{ $customer->bids_count }}</p></div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_340px] lg:items-start">
        <section class="d-card" aria-labelledby="tijdlijn">
            <h2 id="tijdlijn" class="d-h3">Tijdlijn</h2>
            <p class="text-xs text-aj-muted">Bestellingen, tickets, mail en het dossier van zijn kavels; geen eigen kopie van gegevens.</p>
            @if ($timeline->isEmpty())
                <p class="mt-3 text-sm text-aj-muted">Nog niets gebeurd.</p>
            @else
                <ol class="mt-3 divide-y divide-aj-border text-sm">
                    @foreach ($timeline as $entry)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 py-2">
                            <span>@if ($entry['url'])<a href="{{ $entry['url'] }}" class="hover:underline">{{ $entry['text'] }}</a>@else{{ $entry['text'] }}@endif</span>
                            <span class="text-xs text-aj-muted">{{ $entry['at']?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <aside class="space-y-5">
            @if ($role?->seesCustomerNotes())
                <section class="d-card" aria-labelledby="notities">
                    <h2 id="notities" class="d-h3">Notities</h2>
                    @can('admin-area', ['customers', 'write'])
                        <form method="POST" action="{{ route('admin.customers.note', $customer) }}" class="mt-2">
                            @csrf
                            <textarea name="body" rows="2" required class="w-full d-textarea" placeholder="Notitie voor het team"></textarea>
                            <button type="submit" class="d-btn d-btn-dark d-btn-sq mt-2" style="height:34px;font-size:12px">Opslaan</button>
                        </form>
                    @endcan
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse ($notes as $note)
                            <li class="rounded-aj-sm bg-aj-bg p-2"><span class="whitespace-pre-line">{{ $note->body }}</span><span class="block text-xs text-aj-muted">{{ $note->author?->name }} · {{ $note->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span></li>
                        @empty
                            <li class="text-xs text-aj-muted">Nog geen notities.</li>
                        @endforelse
                    </ul>
                </section>
            @endif

            <section class="d-card" aria-labelledby="taken">
                <h2 id="taken" class="d-h3">Taken</h2>
                @can('admin-area', ['customers', 'write'])
                    <form method="POST" action="{{ route('admin.customers.task', $customer) }}" class="mt-2 space-y-2 text-sm">
                        @csrf
                        <input type="text" name="title" required minlength="3" maxlength="200" placeholder="Wat moet er gebeuren?" class="w-full d-input" style="min-height:36px">
                        <input type="datetime-local" name="due_at" class="w-full d-input" style="min-height:36px">
                        <button type="submit" class="d-btn d-btn-dark d-btn-sq" style="height:34px;font-size:12px">Taak aanmaken</button>
                    </form>
                @endcan
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($tasks as $task)
                        <li @class(['flex items-start justify-between gap-2 rounded-aj-sm bg-aj-bg p-2', 'opacity-60 line-through' => $task->done_at])>
                            <span>{{ $task->title }}<span class="block text-xs text-aj-muted">{{ $task->due_at ? 'voor '.$task->due_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') : 'geen deadline' }}{{ $task->assignee ? ' · '.$task->assignee->name : '' }}</span></span>
                            @if (! $task->done_at)
                                @can('admin-area', ['customers', 'write'])
                                    <form method="POST" action="{{ route('admin.customers.task.complete', [$customer, $task]) }}">@csrf<button type="submit" class="d-link text-xs">Klaar</button></form>
                                @endcan
                            @endif
                        </li>
                    @empty
                        <li class="text-xs text-aj-muted">Geen taken.</li>
                    @endforelse
                </ul>
            </section>

            <section class="d-card" aria-labelledby="voorkeuren">
                <h2 id="voorkeuren" class="d-h3">Mailvoorkeuren</h2>
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach ($preferences as $key => $enabled)
                        <li class="flex justify-between"><span>{{ $key }}</span><span class="font-bold">{{ $enabled ? 'aan' : 'uit' }}</span></li>
                    @endforeach
                </ul>
            </section>

            <section class="d-card" aria-labelledby="inzage">
                <h2 id="inzage" class="d-h3">Inzage</h2>
                <ul class="mt-2 space-y-1 text-xs text-aj-muted">
                    @foreach ($accessLogs as $log)
                        <li>{{ $log->staff?->name }} · {{ $log->opened_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</li>
                    @endforeach
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.admin>
