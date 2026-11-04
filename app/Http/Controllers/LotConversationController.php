<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Messaging\StartConversation;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Vanaf de kavelpagina: een korte vraag bij een onderdeel, of een gesprek.
 * De verkoper start vanaf zijn dossier een gesprek met een bieder of koper.
 */
final class LotConversationController extends Controller
{
    public function ask(Request $request, Lot $lot, StartConversation $start): RedirectResponse
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in([Conversation::KIND_QUESTION, Conversation::KIND_CHAT])],
            'lot_part' => ['nullable', Rule::in(Conversation::PARTS)],
            'body' => ['required', 'string', 'min:2', 'max:'.ConversationMessage::MAX_LENGTH],
        ]);

        $conversation = $start->asBuyer($lot, $request->user(), $validated['kind'], $validated['body'], $validated['lot_part'] ?? null, $request->ip());

        $last = $conversation->messages->last();
        $status = $last?->state === ConversationMessage::VISIBLE || $last?->state === ConversationMessage::MASKED
            ? __('messaging.status.sent')
            : ($last?->state === ConversationMessage::HELD ? __('messaging.status.held') : __('messaging.status.rejected'));

        return redirect()->route('messages.show', $conversation)->with('status', $status);
    }

    public function message(Request $request, Lot $lot, User $buyer, StartConversation $start): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:'.ConversationMessage::MAX_LENGTH],
        ]);

        $conversation = $start->asSeller($lot, $request->user(), $buyer, $validated['body'], $request->ip());

        return redirect()->route('messages.show', $conversation)->with('status', __('messaging.status.sent'));
    }
}
