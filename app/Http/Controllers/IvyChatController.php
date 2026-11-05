<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ivy\Alerts\Alerts;
use App\Ivy\Chat\IvyFlags;
use App\Ivy\Chat\KnowledgeBase;
use App\Ivy\Chat\Pages;
use App\Ivy\Chat\RunIvyTurn;
use App\Ivy\StartTask;
use App\Ivy\Tasks\AnalyseGuestPhoto;
use App\Models\IvyConversation;
use App\Models\IvyGuestUpload;
use App\Models\IvyMessage;
use App\Models\Lot;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Het paneel van Ivy: één gesprek per klant (of gast via cookie), dat
 * tussen pagina's bewaard blijft en weet op welke pagina je zit. Ivy zegt
 * in het eerste bericht dat ze AI is. Alles blijft werken zonder haar.
 */
final class IvyChatController extends Controller
{
    public const string GUEST_COOKIE = 'ivy_guest';

    public function __construct(private readonly RunIvyTurn $turn, private readonly Alerts $alerts, private readonly Pages $pages) {}

    public function show(Request $request): JsonResponse
    {
        KnowledgeBase::seed();
        [$conversation, $lot] = $this->conversation($request);
        $user = $request->user();

        return $this->respond($request, [
            'enabled' => IvyFlags::forRole($conversation->role),
            'role' => $conversation->role,
            'conversation_id' => $conversation->id,
            'first_name' => $user !== null ? (explode(' ', trim((string) $user->name))[0] ?: null) : null,
            'greeting' => $conversation->messages()->count() === 0 ? $this->greeting($conversation, $lot, $user) : null,
            'chips' => $this->chips($conversation, $lot, $user, Pages::cleanPath($request->input('path'))),
            // De relatie sorteert zelf al oplopend; eerst los daarvan de laatste dertig, dan weer in leesvolgorde.
            'messages' => $conversation->messages()->reorder()->orderByDesc('created_at')->orderByDesc('id')->limit(30)->get()->reverse()->values()->map(fn (IvyMessage $message) => $this->message($message))->all(),
            'open_count' => $user !== null ? $this->alerts->inbox($user)['count'] : 0,
            'inbox_url' => $user !== null ? route('ivy.inbox') : null,
            'lot' => $lot !== null ? ['id' => $lot->id, 'title' => $lot->title, 'price' => $lot->auction ? Money::format($lot->currentPriceCents()) : null] : null,
        ]);
    }

    public function store(Request $request): JsonResponse|StreamedResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'min:1', 'max:2000'],
            'path' => ['nullable', 'string', 'max:300'],
            'title' => ['nullable', 'string', 'max:200'],
        ]);
        [$conversation, $lot] = $this->conversation($request);
        $page = ['path' => Pages::cleanPath($validated['path'] ?? null), 'title' => $validated['title'] ?? null];

        if ($request->boolean('stream')) {
            return $this->stream($request, $conversation, $validated['text'], $lot, $page);
        }

        $message = $this->turn->handle($conversation, $validated['text'], $request->user(), $lot, $request->ip(), $page);

        return $this->respond($request, [
            'message' => $this->message($message),
            'open_count' => $request->user() !== null ? $this->alerts->inbox($request->user())['count'] : 0,
        ]);
    }

    /**
     * Opnieuw beginnen: het lopende gesprek gaat dicht, het volgende
     * bericht opent een nieuw. De geschiedenis blijft bewaard voor beheer.
     */
    public function reset(Request $request): JsonResponse
    {
        [$conversation] = $this->conversation($request);

        if ($conversation->messages()->exists()) {
            $conversation->forceFill(['status' => IvyConversation::CLOSED])->save();
        }

        return $this->show($request);
    }

    /**
     * Het antwoord als stroom (zelfde vorm als Izzy bij Eazyonline): eerst
     * de stappen die Ivy zet, dan het getoetste bericht. Elke regel is
     * `data: {json}` met een `type`: stap, stap_klaar, bericht, fout, klaar.
     *
     * @param  array{path: string|null, title: string|null}  $page
     */
    private function stream(Request $request, IvyConversation $conversation, string $text, ?Lot $lot, array $page): StreamedResponse
    {
        $user = $request->user();
        $ip = $request->ip();

        $response = response()->stream(function () use ($conversation, $text, $user, $lot, $ip, $page): void {
            // De beurt maakt zijn werk af, ook als de klant op stop drukt: het antwoord staat er dan bij de volgende keer.
            ignore_user_abort(true);
            set_time_limit(180);

            $send = static function (string $type, array $data = []): void {
                echo 'data: '.json_encode(['type' => $type] + $data, JSON_UNESCAPED_UNICODE)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            // Opvulling, zodat een proxy de eerste regels niet vasthoudt.
            echo ':'.str_repeat(' ', 2048)."\n\n";
            flush();

            try {
                $message = $this->turn->handle($conversation, $text, $user, $lot, $ip, $page, $send);

                $send('bericht', [
                    'message' => $this->message($message),
                    'open_count' => $user !== null ? $this->alerts->inbox($user)['count'] : 0,
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $send('fout', ['tekst' => 'Er ging iets mis. Probeer het zo nog eens.']);
            }

            $send('klaar');
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);

        if ($request->attributes->has('ivy_guest_new')) {
            $response->headers->setCookie(Cookie::make(self::GUEST_COOKIE, (string) $request->attributes->get('ivy_guest_new'), 60 * 24 * 30, null, null, null, true, false, 'Lax'));
        }

        return $response;
    }

    /**
     * Foto als bewijs (website-Ivy): zonder account, maximaal vijf per
     * gesprek, privé, weg na de termijn. Het resultaat is een voorbeeld,
     * geen kavel.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240']]);
        [$conversation] = $this->conversation($request);
        $token = $this->guestToken($request);

        if (IvyGuestUpload::query()->where('guest_token', $token)->count() >= (int) config('aucvy.ivy.guest_upload_max', 5)) {
            return response()->json(['message' => 'Maximaal '.config('aucvy.ivy.guest_upload_max', 5).' foto\'s per gesprek.'], 422);
        }

        $file = $request->file('photo');
        $upload = IvyGuestUpload::create([
            'guest_token' => $token,
            'path' => $file->store('ivy-guest', 'local'),
            'mime' => (string) $file->getMimeType(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: '',
            'expires_at' => now()->addHours((int) config('aucvy.ivy.guest_photo_hours', 24)),
            'created_at' => now(),
        ]);

        $run = StartTask::run(new AnalyseGuestPhoto($upload, $conversation), $request->user());
        $upload->refresh();

        $card = ['type' => 'example', 'title' => 'Voorbeeld', 'body' => $upload->analysis['text'] ?? 'Ivy bekijkt de foto; het resultaat volgt in het gesprek.', 'label' => 'Voorbeeld, geen kavel'];
        $message = IvyMessage::create(['conversation_id' => $conversation->id, 'sender' => IvyMessage::IVY, 'body' => $upload->analysis['text'] ?? 'Dank je, ik bekijk de foto.', 'cards' => [$card], 'ai_run_id' => $run->id, 'created_at' => now()]);

        return $this->respond($request, ['message' => $this->message($message), 'upload_id' => $upload->id]);
    }

    /**
     * @return array{0: IvyConversation, 1: Lot|null}
     */
    private function conversation(Request $request): array
    {
        $user = $request->user();
        $path = Pages::cleanPath($request->input('path'));
        $lot = $request->filled('lot')
            ? Lot::query()->where('slug', (string) $request->input('lot'))->orWhere('id', (int) $request->input('lot'))->first()
            : ($path !== null ? $this->pages->lotFromPath($path) : null);
        $pageType = Str::limit((string) $request->input('page', 'onbekend'), 40, '');
        $role = $this->roleFor($user, $lot, $pageType);

        $query = IvyConversation::query()->where('status', IvyConversation::OPEN)->where('role', $role)->latest('updated_at');
        $conversation = $user !== null ? $query->where('user_id', $user->id)->first() : $query->where('guest_token', $this->guestToken($request))->first();

        if ($conversation === null) {
            $conversation = IvyConversation::create(['user_id' => $user?->id, 'guest_token' => $user === null ? $this->guestToken($request) : null, 'role' => $role, 'lot_id' => $lot?->id, 'page_type' => $pageType, 'status' => IvyConversation::OPEN]);
        } elseif ($conversation->lot_id !== $lot?->id || $conversation->page_type !== $pageType) {
            $conversation->forceFill(['lot_id' => $lot?->id ?? $conversation->lot_id, 'page_type' => $pageType])->save();
        }

        if ($lot !== null && ! $lot->status->isPublic() && ($user === null || ! $lot->isManagedBy($user))) {
            $lot = null;
        }

        return [$conversation, $lot];
    }

    private function roleFor(?User $user, ?Lot $lot, string $pageType): string
    {
        if ($user === null) {
            return IvyConversation::VISITOR;
        }

        if (($lot !== null && $lot->isManagedBy($user)) || Str::startsWith($pageType, ['verkoop', 'dashboard.lots', 'studio'])) {
            return IvyConversation::SELLER;
        }

        return IvyConversation::BUYER;
    }

    private function guestToken(Request $request): string
    {
        $token = (string) $request->cookie(self::GUEST_COOKIE, '');

        if ($token === '') {
            // Eén token per verzoek: de tweede aanroep krijgt hetzelfde als de eerste.
            $token = (string) ($request->attributes->get('ivy_guest_new') ?: Str::random(40));
            $request->attributes->set('ivy_guest_new', $token);
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(Request $request, array $data): JsonResponse
    {
        $response = response()->json($data);

        if ($request->attributes->has('ivy_guest_new')) {
            $response->withCookie(Cookie::make(self::GUEST_COOKIE, (string) $request->attributes->get('ivy_guest_new'), 60 * 24 * 30, null, null, null, true, false, 'Lax'));
        }

        return $response;
    }

    private function greeting(IvyConversation $conversation, ?Lot $lot, ?User $user): string
    {
        return match ($conversation->role) {
            IvyConversation::VISITOR => match (true) {
                Str::startsWith($conversation->page_type, 'verkoop') => 'Hoi, ik ben Ivy, de AI-assistent van Aucvy. Stuur een foto van wat je wilt verkopen, dan laat ik zien wat ik ervan maak. Of vraag me wat je wilt weten.',
                $lot !== null => 'Hoi, ik ben Ivy, de AI-assistent van Aucvy. Vraag me gerust iets over deze kavel, de kosten of hoe bieden werkt.',
                default => 'Hoi, ik ben Ivy, de AI-assistent van Aucvy. Ik beantwoord je vragen over kopen, verkopen en kosten. Wat wil je weten?',
            },
            IvyConversation::SELLER => 'Hoi '.(explode(' ', (string) $user?->name)[0] ?? '').', ik ben Ivy, je AI-assistent. Ik doe het werk voor, coach je op wat nog moet en meld wat er gebeurt met je kavels.',
            default => 'Hoi, ik ben Ivy, de AI-assistent van Aucvy. Ik kan voor je zoeken, kavels uitleggen en je biedingen in de gaten houden. Wat zoek je?',
        };
    }

    /**
     * @return list<string>
     */
    private function chips(IvyConversation $conversation, ?Lot $lot, ?User $user, ?string $path = null): array
    {
        if ($user !== null && $lot === null && $path !== null) {
            $forPage = match (true) {
                Str::startsWith($path, ['/dashboard/financien', '/dashboard/uitbetalingen']) => ['Wanneer krijg ik mijn uitbetaling?', 'Hoe werkt de staffel?', 'Wat hou ik over bij € 12.000?'],
                Str::startsWith($path, '/dashboard/biedingen') => ['Mijn biedingen', 'Hoe werkt automatisch bieden?', 'Iets zoeken'],
                Str::startsWith($path, '/bestellingen') => ['Mijn bestelling', 'Wanneer moet ik betalen?', 'Hoe werkt ophalen?'],
                Str::startsWith($path, '/verkopen') => ['Wat kost verkopen?', 'Welke verkoopvorm past bij mij?', 'Wat hou ik over bij € 12.000?'],
                Str::startsWith($path, ['/dashboard/kavels', '/dashboard/veilingen']) => ['Wat moet ik nog doen?', 'Mijn kavels', 'Wat kost verkopen?'],
                $path === '/dashboard' => ['Waar moet ik mee beginnen?', 'Mijn biedingen', 'Mijn kavels'],
                default => null,
            };

            if ($forPage !== null) {
                return $forPage;
            }
        }

        return match ($conversation->role) {
            IvyConversation::VISITOR => $lot !== null ? ['Wat kost dit totaal?', 'Hoe werkt bieden?', 'Wat doen jullie?'] : ['Wat doen jullie?', 'Wat kost verkopen?', 'Hoe veilig is kopen?'],
            IvyConversation::SELLER => $lot !== null ? ['Wat moet ik nog doen?', 'Hoeveel interesse is er?', 'Wat hou ik over bij € 12.000?'] : ['Mijn kavels', 'Wat kost verkopen?', 'Wat moet ik nog doen?'],
            default => $lot !== null ? ['Wat kost dit totaal?', 'Is de reserve gehaald?', 'Mijn biedingen'] : ['Iets zoeken', 'Mijn biedingen', 'Mijn bestelling'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function message(IvyMessage $message): array
    {
        return ['id' => $message->id, 'sender' => $message->sender, 'body' => $message->body, 'cards' => $message->cards ?? [], 'at' => $message->created_at->toIso8601String()];
    }
}
