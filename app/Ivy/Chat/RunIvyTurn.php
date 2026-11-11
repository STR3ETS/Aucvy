<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Enums\AiAgent;
use App\Enums\AiRunStatus;
use App\Ivy\AiResult;
use App\Ivy\Guards\BudgetGuard;
use App\Models\AiRun;
use App\Models\IvyConversation;
use App\Models\IvyMessage;
use App\Models\KbGap;
use App\Models\Lot;
use App\Models\User;
use Closure;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Eén beurt: bericht van de klant opslaan, context bouwen, het model met
 * tools laten werken (maximaal zes rondes), het antwoord toetsen, en het
 * antwoord met kaarten opslaan. Elke beurt is een AiRun met kosten. Bij
 * een uitgeschakelde of uitgeputte Ivy antwoordt ze alleen uit de
 * kennisbank.
 *
 * Met een $emit-functie meldt de beurt onderweg wat Ivy doet ("Kavels
 * zoeken"), zodat het paneel dat live kan tonen. De tekst zelf gaat pas
 * naar buiten na de toets: een bedrag dat niet klopt mag de klant nooit
 * te zien krijgen, ook niet even.
 */
final class RunIvyTurn
{
    private const int MAX_ROUNDS = 6;

    public function __construct(
        private readonly ChatProvider $provider,
        private readonly ToolRegistry $tools,
        private readonly ContextBuilder $context,
        private readonly ChatValidator $validator,
        private readonly BudgetGuard $budget,
        private readonly KnowledgeBase $kb,
    ) {}

    /**
     * @param  array{path?: string|null, title?: string|null}  $page
     * @param  (Closure(string, array<string, mixed>): void)|null  $emit
     */
    public function handle(IvyConversation $conversation, string $text, ?User $user, ?Lot $lot, ?string $ip = null, array $page = [], ?Closure $emit = null): IvyMessage
    {
        $emit ??= static function (string $type, array $data): void {};
        $text = trim($text);

        IvyMessage::create(['conversation_id' => $conversation->id, 'sender' => IvyMessage::USER, 'body' => Str::limit($text, 2000, ''), 'created_at' => now()]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        if (! IvyFlags::forRole($conversation->role)) {
            return $this->reply($conversation, 'Ivy staat even uit. Bieden, zoeken en je meldingen werken gewoon; vraag een medewerker als je hulp nodig hebt.', [], null);
        }

        if (($reason = $this->budget->refusalReason($user)) !== null) {
            $hit = $this->kb->search($text, $conversation->role);

            return $this->reply($conversation, $hit !== null ? $hit['answer'] : 'Ik kan nu alleen uit de kennisbank antwoorden ('.$reason.'). Probeer het later nog eens of vraag een medewerker.', [], null);
        }

        $run = AiRun::create([
            'subject_type' => $lot?->getMorphClass(),
            'subject_id' => $lot?->getKey(),
            'user_id' => $user?->id,
            'agent' => $conversation->agent ?? AiAgent::Ivy,
            'task' => 'ivy_chat',
            'input' => ['text' => Str::limit($text, 500), 'role' => $conversation->role],
            'cache_key' => hash('sha256', 'chat|'.$conversation->id.'|'.now()->timestamp.'|'.Str::random(6)),
            'conversation_id' => $conversation->id,
            'ivy_role' => $conversation->role,
            'status' => AiRunStatus::Running,
        ]);

        $started = hrtime(true);
        $context = new ToolContext($conversation, $user, $conversation->role, $lot, $ip);
        $system = $this->context->build($conversation, $lot, $user, $page);
        $messages = $this->history($conversation);
        // Bedragen die het platform zelf in de context zet (feiten, taken) mag Ivy noemen.
        $allowedAmounts = $this->context->amounts($system);
        $cards = [];
        $toolLog = [];
        $executedProposal = false;
        $tokensIn = 0;
        $tokensOut = 0;
        $model = 'fake';
        $answer = '';

        try {
            for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
                $response = $this->provider->respond($system, $messages, $this->tools->toolsFor($conversation->role), $this->tierFor($text));
                $tokensIn += $response->tokensIn;
                $tokensOut += $response->tokensOut;
                $model = $response->model;

                if (! $response->hasToolCalls()) {
                    $answer = $response->text;

                    break;
                }

                $assistantContent = [];
                $resultContent = [];

                foreach ($response->toolCalls as $call) {
                    $emit('stap', ['id' => $call['id'], 'tekst' => $this->tools->step($call['name'], $call['input'])]);
                    $result = $this->tools->call($call['name'], $call['input'], $context);
                    $toolLog[] = ['name' => $call['name'], 'input' => $call['input'], 'ok' => ! isset($result['error'])];
                    $emit('stap_klaar', ['id' => $call['id'], 'ok' => ! isset($result['error'])]);

                    foreach ((array) ($result['amounts'] ?? []) as $amount) {
                        $allowedAmounts[] = (string) $amount;
                    }

                    if (isset($result['card'])) {
                        $cards[] = $result['card'];
                    }

                    // Wat Ivy zelf deed, en een knop naar een pagina: als kaart, zodat het in het gesprek blijft staan.
                    if (isset($result['done'])) {
                        $cards[] = ['type' => 'done', 'text' => (string) $result['done']['text'], 'url' => $result['done']['url'] ?? null];
                    }

                    if (isset($result['link'])) {
                        $cards[] = ['type' => 'link', 'url' => (string) $result['link']['url'], 'label' => (string) $result['link']['label'], 'direct' => (bool) $result['link']['direct']];
                    }

                    foreach ((array) ($result['lots'] ?? []) as $lotCard) {
                        $cards[] = $lotCard;
                    }

                    if (($result['unknown'] ?? false) === true) {
                        KbGap::record($text, $conversation->role);
                    }

                    $assistantContent[] = ['type' => 'tool_use', 'id' => $call['id'], 'name' => $call['name'], 'input' => (object) $call['input']];
                    $resultContent[] = ['type' => 'tool_result', 'tool_use_id' => $call['id'], 'name' => $call['name'], 'content' => json_encode($this->withoutCards($result), JSON_UNESCAPED_UNICODE) ?: '{}'];
                }

                if ($response->text !== '') {
                    array_unshift($assistantContent, ['type' => 'text', 'text' => $response->text]);
                }

                $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
                $messages[] = ['role' => 'user', 'content' => $resultContent];
            }

            // Alle rondes op aan tools: nog één keer zonder tools, zodat er altijd een antwoord komt.
            if ($answer === '') {
                $closing = $this->provider->respond($system, $messages, [], 'fast');
                $tokensIn += $closing->tokensIn;
                $tokensOut += $closing->tokensOut;
                $answer = $closing->text;
            }

            $answer = $this->validated($answer, $allowedAmounts, $executedProposal, $system, $messages, $conversation->role);
        } catch (Throwable $exception) {
            report($exception);
            $run->update(['status' => AiRunStatus::Failed, 'error' => Str::limit($exception->getMessage(), 490), 'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000)]);

            return $this->reply($conversation, 'Ik kon je vraag nu niet verwerken. Bieden en je meldingen werken gewoon; probeer het zo nog eens of vraag een medewerker.', [], $run);
        }

        $run->update([
            'status' => AiRunStatus::Completed,
            'output' => ['text' => Str::limit($answer, 2000), 'tools' => $toolLog],
            'model' => $model,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'cost_cents' => (new AiResult($answer, $model, $tokensIn, $tokensOut))->costCents(),
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);

        return $this->reply($conversation, $answer, $cards, $run, $toolLog);
    }

    /**
     * De toets; bij een probleem één keer opnieuw, daarna de vaste tekst.
     *
     * @param  list<string>  $allowedAmounts
     * @param  list<array{role: string, content: string|array<int, array<string, mixed>>}>  $messages
     */
    private function validated(string $answer, array $allowedAmounts, bool $executedProposal, string $system, array $messages, string $role): string
    {
        $problems = $this->validator->problems($answer, $allowedAmounts, $executedProposal);

        if ($problems === []) {
            return $answer;
        }

        $retry = $this->provider->respond($system."\n\nJe vorige antwoord is afgekeurd (".implode(', ', $problems).'). Noem alleen bedragen uit de toolresultaten, geen verboden woorden, geen contactgegevens, en beweer niets wat je niet hebt gedaan.', $messages, [], 'fast');
        $again = $this->validator->problems($retry->text, $allowedAmounts, $executedProposal);

        if ($again === [] && $retry->text !== '') {
            return $retry->text;
        }

        if (in_array('claimed_action', $problems, true) || in_array('contact_details', $problems, true)) {
            report(new RuntimeException('Ivy-incident: '.implode(', ', $problems)));
        }

        return ChatValidator::FALLBACK;
    }

    /**
     * @param  list<array<string, mixed>>  $cards
     * @param  list<array<string, mixed>>  $toolLog
     */
    private function reply(IvyConversation $conversation, string $text, array $cards, ?AiRun $run, array $toolLog = []): IvyMessage
    {
        $message = IvyMessage::create([
            'conversation_id' => $conversation->id,
            'sender' => IvyMessage::IVY,
            'body' => $text,
            'cards' => $cards === [] ? null : array_slice($cards, 0, 6),
            'tool_calls' => $toolLog === [] ? null : $toolLog,
            'ai_run_id' => $run?->id,
            'prompt_version' => ContextBuilder::VERSION,
            'created_at' => now(),
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function history(IvyConversation $conversation): array
    {
        // ULID's lopen op binnen dezelfde seconde; daarom ook op id sorteren.
        return $conversation->messages()->reorder()->orderByDesc('created_at')->orderByDesc('id')->limit(12)->get()->reverse()->values()
            ->filter(fn (IvyMessage $message) => in_array($message->sender, [IvyMessage::USER, IvyMessage::IVY], true) && $message->body !== '')
            ->map(fn (IvyMessage $message) => ['role' => $message->sender === IvyMessage::USER ? 'user' : 'assistant', 'content' => $message->body])
            ->values()->all();
    }

    private function tierFor(string $text): string
    {
        $lower = Str::lower($text);

        return str_contains($lower, 'advies') || str_contains($lower, 'waarde') || str_contains($lower, 'foto') ? 'strong' : 'fast';
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function withoutCards(array $result): array
    {
        unset($result['card'], $result['amounts'], $result['done'], $result['link']);

        if (isset($result['lots'])) {
            $result['lots'] = array_map(fn ($lot) => array_intersect_key($lot, array_flip(['id', 'title', 'meta', 'price', 'price_label', 'ends', 'badge'])), $result['lots']);
        }

        return $result;
    }
}
