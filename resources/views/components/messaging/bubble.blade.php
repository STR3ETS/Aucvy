@props(['message', 'viewer', 'conversation', 'asStaff' => false])

@php
    use App\Models\ConversationMessage;

    $mine = $viewer !== null && $message->sender_id === $viewer->id && $message->sender_type !== ConversationMessage::FROM_OPERATOR;
    $body = $message->bodyFor($viewer, $asStaff);
    $moderation = $message->moderations->sortByDesc('id')->first();
    $from = match ($message->sender_type) {
        ConversationMessage::FROM_BUYER => $conversation->buyerLabel(),
        ConversationMessage::FROM_SELLER => $conversation->sellerLabel(),
        ConversationMessage::FROM_IVY => 'Ivy',
        ConversationMessage::FROM_OPERATOR => ($message->sender?->name ?? 'Medewerker').', medewerker Aucvy',
        default => 'Aucvy',
    };
    $tone = match (true) {
        $message->sender_type === ConversationMessage::FROM_SYSTEM => 'system',
        $message->sender_type === ConversationMessage::FROM_OPERATOR => 'operator',
        $message->sender_type === ConversationMessage::FROM_IVY => 'ivy',
        $mine => 'mine',
        default => 'other',
    };
    $time = $message->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm');
@endphp

@if ($tone === 'system')
    <li class="py-1 text-center text-xs text-aj-muted" data-message-id="{{ $message->id }}">{{ $body }} · {{ $time }}</li>
@else
    <li @class(['flex flex-col gap-1', 'items-end' => $tone === 'mine', 'items-start' => $tone !== 'mine']) data-message-id="{{ $message->id }}">
        <span class="text-xs text-aj-muted">{{ $mine ? 'Jij ('.$from.')' : $from }} · {{ $time }}</span>
        <div @class([
            'max-w-[85%] rounded-aj-s px-4 py-2.5 text-sm',
            'bg-aj-blue text-white' => $tone === 'mine' && ! $message->isPending(),
            'border border-aj-border bg-aj-surface' => $tone === 'other',
            'border border-aj-blue/40 bg-aj-blue/5' => $tone === 'operator',
            'border border-aj-navy/30 bg-aj-navy/5' => $tone === 'ivy',
            'border border-aj-warn/50 bg-aj-warn/10 text-aj-text' => $message->isPending(),
        ])>
            {{-- Regeleinden uit het bericht blijven staan; de inspringing van dit sjabloon niet. --}}
            <span class="whitespace-pre-line">{{ $body ?? $message->body }}</span>
            @if ($message->attachment_path)
                <a href="{{ route('messages.attachment', [$conversation, $message]) }}" class="mt-1 block text-xs font-bold underline">{{ $message->attachment_name }}</a>
            @endif
        </div>
        @if ($message->isPending())
            <span class="max-w-[85%] text-xs text-aj-warn-text">
                {{ $message->state === ConversationMessage::HELD ? __('messaging.states.held') : __('messaging.states.rejected') }}
                {{ $moderation?->explanation }}
            </span>
        @elseif ($message->state === ConversationMessage::MASKED && ($mine || $asStaff))
            <span class="max-w-[85%] text-xs text-aj-muted">{{ __('messaging.explanations.contact') }}</span>
        @elseif ($message->hint)
            <span class="max-w-[85%] rounded-aj-sm border border-aj-navy/20 bg-aj-navy/5 px-3 py-2 text-xs">
                <span class="font-bold">Ivy:</span> {{ __('messaging.explanations.'.$message->hint) }}
                @if ($conversation->order)
                    <a href="{{ route('orders.show', $conversation->order) }}" class="font-bold underline">Naar de bestelling</a>
                @endif
            </span>
        @endif
    </li>
@endif
