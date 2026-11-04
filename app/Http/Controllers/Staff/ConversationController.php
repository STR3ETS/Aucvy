<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Messaging\Operator\OperatorActions;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * De operator in /admin: wachtrij op ernst en ouderdom, meelezen met de
 * kavel, de order en de botbeslissingen ernaast, reageren als medewerker
 * of intern, en een zaak omzetten naar een geschil.
 */
final class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        $filter = (string) $request->query('filter', 'alles');

        $query = Conversation::query()
            ->with(['lot', 'buyer', 'seller', 'company', 'order'])
            ->withCount(['messages as held_count' => fn ($query) => $query->where('state', ConversationMessage::HELD)]);

        match ($filter) {
            'vastgehouden' => $query->whereHas('messages', fn ($query) => $query->where('state', ConversationMessage::HELD)),
            'vlag' => $query->whereNotNull('flag')->whereNull('reported_at'),
            'melding' => $query->whereNotNull('reported_at'),
            default => $query->where(fn ($query) => $query->whereNotNull('flag')->orWhereNotNull('reported_at')->orWhereHas('messages', fn ($query) => $query->where('state', ConversationMessage::HELD))),
        };

        return view('admin.conversations.index', [
            'filter' => $filter,
            'conversations' => $query
                ->orderByRaw("case when flag in ('off_platform','bid_manipulation') then 0 when reported_at is not null then 1 else 2 end")
                ->orderBy('flagged_at')
                ->paginate(30)
                ->withQueryString(),
            'counts' => [
                'alles' => Conversation::query()->where(fn ($query) => $query->whereNotNull('flag')->orWhereNotNull('reported_at'))->count(),
                'vastgehouden' => ConversationMessage::query()->where('state', ConversationMessage::HELD)->distinct('conversation_id')->count('conversation_id'),
                'melding' => Conversation::query()->whereNotNull('reported_at')->count(),
            ],
        ]);
    }

    public function show(Request $request, Conversation $conversation, OperatorActions $operator): View|RedirectResponse
    {
        $reason = $request->query('reden');

        // Zonder vlag of melding alleen met een reden; het formulier vraagt erom.
        if ($conversation->flag === null && $conversation->reported_at === null && $conversation->dispute_id === null && ! is_string($reason)) {
            return view('admin.conversations.reason', ['conversation' => $conversation->load(['lot', 'buyer', 'company'])]);
        }

        $operator->open($conversation, $request->user(), is_string($reason) ? $reason : null, $request->ip());

        $conversation->load(['lot.media', 'buyer', 'seller', 'company', 'order.disputes', 'operator', 'messages.sender', 'messages.moderations.reviewer', 'accessLogs.staff']);

        return view('admin.conversations.show', [
            'conversation' => $conversation,
            'held' => $conversation->messages->filter(fn (ConversationMessage $message) => $message->state === ConversationMessage::HELD),
        ]);
    }

    public function release(Request $request, Conversation $conversation, ConversationMessage $message, OperatorActions $operator): RedirectResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);

        $operator->release($conversation, $message, $request->user(), $request->ip());

        return redirect()->route('admin.conversations.show', $conversation)->with('status', __('messaging.status.released'));
    }

    public function confirm(Request $request, Conversation $conversation, ConversationMessage $message, OperatorActions $operator): RedirectResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);

        $operator->confirm($conversation, $message, $request->user(), $request->ip());

        return redirect()->route('admin.conversations.show', $conversation)->with('status', __('messaging.status.confirmed'));
    }

    public function reply(Request $request, Conversation $conversation, OperatorActions $operator): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:'.ConversationMessage::MAX_LENGTH]]);

        $operator->reply($conversation, $request->user(), $validated['body'], $request->ip());

        return redirect()->route('admin.conversations.show', $conversation)->with('status', __('messaging.status.replied'));
    }

    public function note(Request $request, Conversation $conversation, OperatorActions $operator): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'min:2', 'max:'.ConversationMessage::MAX_LENGTH]]);

        $operator->note($conversation, $request->user(), $validated['body']);

        return redirect()->route('admin.conversations.show', $conversation)->with('status', __('messaging.status.noted'));
    }

    public function dispute(Request $request, Conversation $conversation, OperatorActions $operator): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);

        $operator->toDispute($conversation, $request->user(), $validated['reason'], $request->ip());

        return redirect()->route('admin.conversations.show', $conversation)->with('status', __('messaging.status.dispute'));
    }

    public function warn(Request $request, Conversation $conversation, OperatorActions $operator): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'min:10', 'max:500']]);

        $operator->warnSeller($conversation, $request->user(), $validated['note'], $request->ip());

        return redirect()->route('admin.conversations.show', $conversation)->with('status', __('messaging.status.warned'));
    }
}
