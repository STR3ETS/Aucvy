{{-- Gevuld door het berichtenblok (conversations); tot dan een nette lege staat. --}}
@php $conversations = $conversations ?? collect(); @endphp
@if ($conversations->isEmpty())
    <p class="mt-4 rounded-aj-sm border border-dashed border-aj-border-strong p-5 text-center text-sm text-aj-muted">Nog geen vragen of berichten over deze kavel.</p>
@else
    <ul class="mt-4 divide-y divide-aj-border rounded-aj-s border border-aj-border">
        @foreach ($conversations as $conversation)
            <li>
                <a href="{{ route('messages.show', $conversation) }}" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 text-sm hover:bg-aj-blue/5">
                    <span><span class="font-bold">{{ $conversation->buyerLabel() }}</span> · {{ $conversation->kindLabel() }}@if ($conversation->lot_part) · {{ $conversation->lot_part }}@endif</span>
                    <span class="text-xs text-aj-muted">{{ $conversation->last_message_at?->diffForHumans() ?? 'nieuw' }}</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
