<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\Dossier\Dossier;
use App\Messaging\Operator\OperatorActions;
use App\Messaging\PublishAnswer;
use App\Messaging\SendMessage;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berichten voor koper en verkoper: de inbox, één gesprek, een bericht
 * sturen, melden, blokkeren en (verkoper) een antwoord bij de kavel zetten.
 * Alleen de twee partijen zien een gesprek; een derde krijgt 404.
 */
final class ConversationController extends Controller
{
    /** Zoveel gesprekken staan er in de lijst naast een geopend gesprek. */
    private const int LIST_LIMIT = 25;

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        /*
         | Wie gesprekken heeft, komt meteen in het gesprek dat aandacht
         | vraagt: het eerste ongelezen, anders het laatste. De lijst zonder
         | geopend gesprek (met ?page=) blijft voor oudere gesprekken en voor
         | de terugknop op de telefoon.
         */
        if (! $request->has('page')) {
            $recent = Conversation::query()
                ->forParticipant($user)
                ->orderByRaw('last_message_at is null, last_message_at desc')
                ->limit(self::LIST_LIMIT)
                ->get();
            $open = $recent->first(fn (Conversation $conversation): bool => $conversation->isUnreadFor($user)) ?? $recent->first();

            if ($open !== null) {
                return redirect()->route('messages.show', $open);
            }
        }

        $conversations = Conversation::query()
            ->forParticipant($user)
            ->with(['lot.media', 'buyer', 'seller', 'company', 'order'])
            ->orderByRaw('last_message_at is null, last_message_at desc')
            ->paginate(self::LIST_LIMIT);

        return view('dashboard.messages.index', [
            'conversations' => $conversations,
            'user' => $user,
            'unreadCount' => $conversations->getCollection()->filter(fn (Conversation $conversation) => $conversation->isUnreadFor($user))->count(),
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $user = $this->participant($request, $conversation);

        $conversation->load(['lot.media', 'buyer', 'seller', 'company', 'order', 'operator']);
        $conversation->markReadFor($user);

        return view('dashboard.messages.show', [
            'conversation' => $conversation,
            'messages' => $this->visibleMessages($conversation, $user),
            'user' => $user,
            'isBuyer' => $conversation->isBuyer($user),
            'conversations' => $this->listFor($user, $conversation),
            'hasOlder' => Conversation::query()->forParticipant($user)->count() > self::LIST_LIMIT,
            // "Alles over deze kavel" naast het gesprek (ontwerp van november): het dossier, alleen wat deze lezer mag zien.
            'dossier' => Dossier::timeline($conversation->lot, $conversation->isBuyer($user) ? 'buyer' : 'seller', $user, orderId: $conversation->order_id, limit: 8),
        ]);
    }

    /**
     * De lijst naast een geopend gesprek, in dezelfde volgorde als op de
     * overzichtspagina: het gesprek met het laatste bericht bovenaan. Het
     * geopende gesprek blijft op zijn eigen plek staan.
     *
     * @return Collection<int, Conversation>
     */
    private function listFor(User $user, Conversation $current): Collection
    {
        $list = Conversation::query()
            ->forParticipant($user)
            ->with(['lot.media', 'buyer', 'seller', 'company', 'order'])
            ->orderByRaw('last_message_at is null, last_message_at desc')
            ->limit(self::LIST_LIMIT)
            ->get();

        // Een oud gesprek dat buiten de eerste vijfentwintig valt, staat onderaan.
        return $list->contains($current) ? $list : $list->push($current);
    }

    public function store(Request $request, Conversation $conversation, SendMessage $send): RedirectResponse
    {
        $user = $this->participant($request, $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:'.ConversationMessage::MAX_LENGTH],
            'attachment' => ['nullable', 'file', 'mimetypes:'.implode(',', (array) config('messaging.attachment_mimes')), 'max:'.(int) config('messaging.attachment_max_kb')],
            'client_key' => ['nullable', 'uuid'],
        ]);

        $message = $send->handle($conversation, $user, $validated['body'], $request->file('attachment'), $request->ip(), clientKey: $validated['client_key'] ?? null);

        $status = match ($message->state) {
            ConversationMessage::HELD => __('messaging.status.held'),
            ConversationMessage::REJECTED => __('messaging.status.rejected'),
            default => __('messaging.status.sent'),
        };

        return redirect()->route('messages.show', $conversation)->with('status', $status);
    }

    /**
     * Voor de client die realtime of met polling bijwerkt: de berichten na
     * een id, met de rechten van deze lezer.
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $this->participant($request, $conversation);
        $after = (int) $request->query('after', '0');

        $messages = $this->visibleMessages($conversation, $user)->filter(fn (ConversationMessage $message) => $message->id > $after)->values();

        if ($messages->isNotEmpty()) {
            $conversation->markReadFor($user);
        }

        return response()->json([
            'messages' => $messages->map(fn (ConversationMessage $message) => [
                'id' => $message->id,
                'html' => view('components.messaging.bubble', ['message' => $message, 'viewer' => $user, 'conversation' => $conversation])->render(),
            ]),
        ]);
    }

    public function attachment(Request $request, Conversation $conversation, ConversationMessage $message): StreamedResponse
    {
        $user = $this->participant($request, $conversation);

        abort_unless($message->conversation_id === $conversation->id && $message->attachment_path !== null, 404);
        abort_unless($message->bodyFor($user) !== null, 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name);
    }

    public function publish(Request $request, Conversation $conversation, ConversationMessage $message, PublishAnswer $publish): RedirectResponse
    {
        $user = $this->participant($request, $conversation);

        $publish->handle($conversation, $message, $user);

        return redirect()->route('messages.show', $conversation)->with('status', __('messaging.status.published'));
    }

    public function report(Request $request, Conversation $conversation, OperatorActions $operator): RedirectResponse
    {
        $user = $this->participant($request, $conversation);
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $operator->report($conversation, $user, $validated['note'] ?? null);

        return redirect()->route('messages.show', $conversation)->with('status', __('messaging.status.reported'));
    }

    public function block(Request $request, Conversation $conversation, OperatorActions $operator): RedirectResponse
    {
        $user = $this->participant($request, $conversation);

        $operator->block($conversation, $user);

        return redirect()->route('messages.index')->with('status', __('messaging.status.blocked'));
    }

    private function participant(Request $request, Conversation $conversation): User
    {
        $user = $request->user();

        // 404 en geen 403: dat een gesprek bestaat gaat een derde niets aan.
        abort_unless($conversation->isParticipant($user), 404);

        return $user;
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    private function visibleMessages(Conversation $conversation, User $user): Collection
    {
        return $conversation->messages()
            ->where('internal', false)
            ->with(['sender', 'moderations'])
            ->get()
            ->filter(fn (ConversationMessage $message) => $message->bodyFor($user) !== null || $message->isFrom($user))
            ->values();
    }
}
