<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AiRunStatus;
use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Ivy\Guards\ConfidencePolicy;
use App\Ivy\Jobs\RunAiTask;
use App\Ivy\TaskRegistry;
use App\Lots\DraftAccess;
use App\Lots\DraftPayload;
use App\Models\AiRun;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ivy-taken starten en volgen (hfst. 7 en 8).
 *
 * Alles asynchroon: POST zet een run in de wachtrij en geeft direct het
 * run-id terug; de client polt GET tot de run klaar is. Staat de noodrem om,
 * dan komt er meteen een `skipped`-run terug - de funnel valt dan terug op
 * handmatig invullen en niemand staat te wachten op niets.
 */
final class IvyTaskController extends Controller
{
    public function __construct(
        private readonly DraftAccess $access,
        private readonly DraftPayload $draftPayload,
        private readonly ConfidencePolicy $confidence,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task' => ['required', 'string', Rule::in([...TaskRegistry::names(), 'interpret_search'])],
            'lot_id' => ['required_unless:task,interpret_search', 'integer'],
            'query' => ['required_if:task,interpret_search', 'string', 'min:2', 'max:120'],
        ]);

        $task = match (true) {
            // Zoeken is publiek; het onderwerp is de zoekvraag, geen kavel.
            $validated['task'] === 'interpret_search' => TaskRegistry::searchFor($validated['query'], $request->user()),

            // Concepttaken: dezelfde poort als de rest van de funnel -
            // eigenaar of cookie, anders 404 (13.4).
            TaskRegistry::isDraftScoped($validated['task']) => TaskRegistry::forLot(
                $validated['task'],
                $this->access->resolve($request, Lot::findOrFail($validated['lot_id'])),
            ),

            // Kaveltaken (biedadvies): elke ingelogde kijker op een live kavel.
            default => TaskRegistry::forLot(
                $validated['task'],
                tap(Lot::findOrFail($validated['lot_id']), function (Lot $lot) use ($request): void {
                    abort_unless($request->user() !== null, 401);
                    abort_unless($lot->status === LotStatus::Live && $lot->auction()->exists(), 404);
                }),
            ),
        };

        $subject = $task->subject();

        $run = AiRun::create([
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'user_id' => $request->user()?->id,
            'agent' => $task->agent(),
            'task' => $task->name(),
            'input' => $task->input(),
            'cache_key' => $task->cacheKey(),
            'status' => AiRunStatus::Queued,
        ]);

        if (! config('aucvy.ai.enabled')) {
            // De noodrem slaat hier al aan: geen wachttijd, wel een logregel.
            $run->update([
                'status' => AiRunStatus::Skipped,
                'error' => 'Noodrem: Ivy staat uit (aucvy.ai.enabled).',
                'latency_ms' => 0,
            ]);
        } else {
            RunAiTask::dispatch($run, $task);
        }

        return response()->json(['run' => $this->runPayload($run)], 201);
    }

    public function show(Request $request, AiRun $run): JsonResponse
    {
        // Toegang eerst - óók een wachtende of mislukte run gaat niemand
        // anders iets aan.
        $lot = $this->authorizeRun($request, $run);

        $response = ['run' => $this->runPayload($run)];

        // Een geslaagde concepttaak heeft het concept al bijgewerkt; de verse
        // conceptstand gaat mee zodat de funnel hem als autosave-respons kan
        // toepassen.
        if ($lot !== null && TaskRegistry::isDraftScoped($run->task) && $run->status === AiRunStatus::Completed) {
            $response += $this->draftPayload->for($lot->fresh(), auth()->check());
        }

        return response()->json($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(AiRun $run): array
    {
        $payload = [
            'id' => $run->id,
            'task' => $run->task,
            'agent' => $run->agent->value,
            'status' => $run->status->value,
        ];

        if ($run->status === AiRunStatus::Completed) {
            // Alleen gevalideerde uitvoer, mét het zekerheidslabel dat bepaalt
            // wat de UI ermee mag (hfst. 8).
            $payload['output'] = $run->output;
            $payload['confidence'] = $run->confidence;
            $payload['confidence_level'] = $this->confidence->level($run->confidence);
            $payload['confidence_hint'] = $this->confidence->hint($run->confidence);
        }

        if (in_array($run->status, [AiRunStatus::Failed, AiRunStatus::Skipped], true)) {
            // Eigen tekst, geen modeluitvoer - mag naar de client.
            $payload['error'] = $run->error;
        }

        return $payload;
    }

    /**
     * Toegangscontrole per taakscope. Anders 404, om dezelfde reden als bij
     * de concepten: een 403 bevestigt dat er iets bestaat.
     */
    private function authorizeRun(Request $request, AiRun $run): ?Lot
    {
        // Zoekinterpretatie: publiek zolang de run niet aan een account
        // hangt - de inhoud is de lezing van een zoekterm, meer niet.
        if ($run->task === 'interpret_search') {
            abort_unless($run->user_id === null || $run->user_id === $request->user()?->id, 404);

            return null;
        }

        $subject = $run->subject;

        if (! $subject instanceof Lot) {
            // Gebruikersgebonden runs (weekly): alleen de eigenaar.
            abort_unless($run->user_id !== null && $run->user_id === $request->user()?->id, 404);

            return null;
        }

        // Concepttaken via de funnelpoort; kaveltaken (biedadvies) alleen
        // voor wie de run zelf startte.
        if (TaskRegistry::isDraftScoped($run->task)) {
            return $this->access->resolve($request, $subject);
        }

        abort_unless($run->user_id !== null && $run->user_id === $request->user()?->id, 404);

        return $subject;
    }
}
