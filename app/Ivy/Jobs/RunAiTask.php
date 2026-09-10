<?php

declare(strict_types=1);

namespace App\Ivy\Jobs;

use App\Enums\AiRunStatus;
use App\Ivy\AiResult;
use App\Ivy\Contracts\AiTask;
use App\Ivy\Contracts\LlmProvider;
use App\Ivy\Guards\BudgetGuard;
use App\Ivy\Guards\OutputValidator;
use App\Models\AiRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Elke AI-taak loopt hierdoorheen - geen uitzonderingen (hfst. 8).
 *
 * De volgorde is de architectuur:
 *
 *  1. noodrem - staat `aucvy.ai.enabled` uit, dan wordt de run `skipped` en
 *     werkt de applicatie gewoon door;
 *  2. budget - op is op, ook `skipped`;
 *  3. cache - zelfde input en promptversie als een eerdere geslaagde run
 *     betekent hergebruik, kosten nul;
 *  4. provider - met één schema-herkansing op een strengere prompt;
 *  5. guards - schema en verboden frasen; ongevalideerde uitvoer komt nooit
 *     op een scherm;
 *  6. toepassen - de taak vult gaten in het onderwerp, gebruikersinvoer wint.
 *
 * Alles landt in `ai_runs`, geslaagd of niet.
 */
final class RunAiTask implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 1;

    public function __construct(public AiRun $run, public AiTask $task)
    {
        $this->timeout = (int) config('aucvy.ai.task_timeout_seconds');
        $this->onQueue('ai');
    }

    public function handle(OutputValidator $validator, BudgetGuard $budget): void
    {
        if ($this->run->status !== AiRunStatus::Queued) {
            return;
        }

        if (! config('aucvy.ai.enabled')) {
            $this->skip('Noodrem: Ivy staat uit (aucvy.ai.enabled).');

            return;
        }

        if (($reason = $budget->refusalReason($this->run->user)) !== null) {
            $this->skip($reason);

            return;
        }

        if ($this->completeFromCache()) {
            return;
        }

        $this->run->update(['status' => AiRunStatus::Running]);

        $startedAt = hrtime(true);

        try {
            $provider = app(LlmProvider::class);

            /*
             | Eén herkansing, dan een nette fout - en dat geldt voor béíde
             | poorten: het schema én de verboden formuleringen. Een model dat
             | "gegarandeerd" schrijft krijgt één kans om te herschrijven;
             | ongevalideerde uitvoer bereikt hoe dan ook nooit een scherm.
             */
            $result = $provider->complete($this->task, $this->task->prompt());
            [$output, $problems] = $this->validate($validator, $result->text);

            if ($problems !== []) {
                $retry = $provider->complete($this->task, $this->stricterPrompt($problems));
                $result = $this->combine($result, $retry);
                [$output, $problems] = $this->validate($validator, $retry->text);

                if ($problems !== []) {
                    $this->fail_($result, $startedAt, 'Uitvoer voldeed ook na herkansing niet: '.implode('; ', $problems));

                    return;
                }
            }

            $confidence = is_numeric($output['confidence'] ?? null)
                ? max(0.0, min(1.0, (float) $output['confidence']))
                : null;

            $this->run->update([
                'status' => AiRunStatus::Completed,
                'output' => $output,
                'confidence' => $confidence,
                'model' => $result->model,
                'tokens_in' => $result->tokensIn,
                'tokens_out' => $result->tokensOut,
                'cost_cents' => $result->costCents(),
                'latency_ms' => $this->elapsedMs($startedAt),
            ]);

            $this->task->apply($this->run);
        } catch (Throwable $exception) {
            report($exception);
            $this->fail_(null, $startedAt, 'Provider faalde: '.$exception->getMessage());
        }
    }

    /**
     * Ook als de worker de job hard afbreekt (timeout) hoort er een eerlijke
     * regel in het log te staan.
     */
    public function failed(?Throwable $exception): void
    {
        $this->run->refresh();

        if (in_array($this->run->status, [AiRunStatus::Queued, AiRunStatus::Running], true)) {
            $this->run->update([
                'status' => AiRunStatus::Failed,
                'error' => 'Afgebroken: '.($exception?->getMessage() ?? 'onbekende fout'),
            ]);
        }
    }

    /**
     * Zelfde invoer, zelfde promptversie, eerder geslaagd: hergebruik. De run
     * blijft in het log staan - met kosten nul - zodat ook cache-treffers
     * navolgbaar zijn.
     */
    private function completeFromCache(): bool
    {
        $cached = AiRun::query()
            ->where('cache_key', $this->run->cache_key)
            ->where('status', AiRunStatus::Completed)
            ->whereKeyNot($this->run->id)
            ->latest('id')
            ->first();

        if ($cached === null) {
            return false;
        }

        $this->run->update([
            'status' => AiRunStatus::Completed,
            'output' => $cached->output,
            'confidence' => $cached->confidence,
            'model' => $cached->model,
            'cost_cents' => 0,
            'latency_ms' => 0,
        ]);

        $this->task->apply($this->run);

        return true;
    }

    private function skip(string $reason): void
    {
        $this->run->update([
            'status' => AiRunStatus::Skipped,
            'error' => $reason,
            'latency_ms' => 0,
        ]);
    }

    /** Met underscore: Queueable levert al een fail() met andere semantiek. */
    private function fail_(?AiResult $result, int $startedAt, string $error): void
    {
        $this->run->update([
            'status' => AiRunStatus::Failed,
            'error' => $error,
            'model' => $result?->model,
            'tokens_in' => $result?->tokensIn,
            'tokens_out' => $result?->tokensOut,
            'cost_cents' => $result?->costCents() ?? 0,
            'latency_ms' => $this->elapsedMs($startedAt),
        ]);
    }

    /**
     * Beide poorten in één controle: schema én verboden formuleringen.
     *
     * @return array{0: array<string, mixed>|null, 1: list<string>}
     */
    private function validate(OutputValidator $validator, string $text): array
    {
        $output = $validator->extractJson($text);

        if ($output === null) {
            return [null, ['$: antwoord bevat geen geldig JSON-object']];
        }

        $problems = $validator->schemaErrors($output, $this->task->schema());

        foreach ($validator->forbiddenPhrases($output) as $phrase) {
            $problems[] = "verboden formulering: \"{$phrase}\"";
        }

        return [$output, $problems];
    }

    /**
     * @param  list<string>  $errors
     */
    private function stricterPrompt(array $errors): string
    {
        return $this->task->prompt()
            ."\n\nJe vorige antwoord is afgekeurd:\n- "
            .implode("\n- ", $errors)
            ."\n\nHerschrijf je antwoord: uitsluitend één geldig JSON-object dat exact aan het schema voldoet, "
            .'zonder de genoemde verboden formuleringen - geen garanties, geen beloften. Geen tekst eromheen.';
    }

    private function combine(AiResult $first, AiResult $second): AiResult
    {
        return new AiResult(
            text: $second->text,
            model: $second->model,
            tokensIn: $first->tokensIn + $second->tokensIn,
            tokensOut: $first->tokensOut + $second->tokensOut,
        );
    }

    private function elapsedMs(int $startedAt): int
    {
        return (int) intdiv(hrtime(true) - $startedAt, 1_000_000);
    }
}
