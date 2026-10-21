<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use App\Enums\LotCondition;
use App\Enums\ValueConfidence;
use App\Ivy\AiResult;
use App\Ivy\Contracts\AiTask;
use App\Ivy\Contracts\LlmProvider;
use App\Ivy\Jobs\RunAiTask;
use App\Ivy\TaskRegistry;
use App\Models\AiRun;
use App\Models\Lot;
use App\Models\User;

/*
| De job is de architectuur van hfst. 8: noodrem → budget → cache → provider
| → guards → toepassen. Elke stap heeft hier zijn eigen test, met de
| deterministische FakeProvider of een eigen stub.
*/

function ivyRun(Lot $lot, string $task = 'recognise_lot'): array
{
    $taskObject = TaskRegistry::forLot($task, $lot);

    $run = AiRun::create([
        'subject_type' => $lot->getMorphClass(),
        'subject_id' => $lot->id,
        'user_id' => $lot->seller_id,
        'agent' => $taskObject->agent(),
        'task' => $taskObject->name(),
        'input' => $taskObject->input(),
        'cache_key' => $taskObject->cacheKey(),
        'status' => AiRunStatus::Queued,
    ]);

    return [$run, $taskObject];
}

function runIvyJob(AiRun $run, AiTask $task): AiRun
{
    RunAiTask::dispatch($run, $task);

    return $run->fresh();
}

/** Een provider die vaste teksten teruggeeft en zijn aanroepen telt. */
function stubProvider(string ...$texts): object
{
    $stub = new class($texts) implements LlmProvider
    {
        public int $calls = 0;

        public function __construct(private readonly array $texts) {}

        public function complete(AiTask $task, string $prompt): AiResult
        {
            $text = $this->texts[min($this->calls, count($this->texts) - 1)];
            $this->calls++;

            return new AiResult(text: $text, model: 'stub', tokensIn: 100, tokensOut: 50);
        }
    };

    app()->instance(LlmProvider::class, $stub);

    return $stub;
}

describe('het gelukkige pad', function () {
    it('herkent een object en vult alleen lege velden', function () {
        $lot = Lot::factory()->anonymousDraft()->create([
            'description' => 'Complete horecakeuken van Siemens, drie jaar oud.',
            'brand' => null,
            'condition' => null,
        ]);

        [$run, $task] = ivyRun($lot);
        $run = runIvyJob($run, $task);

        expect($run->status)->toBe(AiRunStatus::Completed)
            ->and($run->confidence)->not->toBeNull()
            ->and($run->model)->toBe('fake')
            ->and($run->latency_ms)->not->toBeNull();

        $lot->refresh();

        expect($lot->brand)->toBe('Siemens')
            ->and($lot->condition)->toBeInstanceOf(LotCondition::class)
            // Kenmerken van Ivy dragen hun bron en zekerheid (briefing sectie 5).
            ->and($lot->specifications['objecttype']['value'])->toBe('Professionele keuken')
            ->and($lot->specifications['objecttype']['source'])->toBe('ai');
    });

    it('overschrijft nooit wat de verkoper zelf invulde', function () {
        $lot = Lot::factory()->anonymousDraft()->create([
            'description' => 'Keuken van Siemens.',
            'brand' => 'Miele', // De verkoper weet het beter dan de foto's.
        ]);

        [$run, $task] = ivyRun($lot);
        runIvyJob($run, $task);

        expect($lot->fresh()->brand)->toBe('Miele');
    });

    it('schrijft de advertentie maar laat een bewerkte omschrijving staan', function () {
        $lot = Lot::factory()->anonymousDraft()->create([
            'title' => null,
            'description' => 'Ik stop met mijn restaurant.',
        ]);

        [$run, $task] = ivyRun($lot, 'draft_listing');

        // De verkoper typt verder terwíjl Ivy bezig is: zijn tekst wint.
        $lot->update(['description' => 'Zelf geschreven, met liefde.']);

        $run = runIvyJob($run, $task);

        expect($run->status)->toBe(AiRunStatus::Completed)
            ->and($lot->fresh()->title)->not->toBeNull()
            ->and($lot->fresh()->description)->toBe('Zelf geschreven, met liefde.');
    });

    it('maakt van vergelijkingen een bandbreedte in PHP', function () {
        $lot = Lot::factory()->anonymousDraft()->create([
            'title' => 'Graafmachine',
            'description' => 'Volvo graafmachine uit 2019.',
            'brand' => 'Volvo',
        ]);

        [$run, $task] = ivyRun($lot, 'estimate_value');
        $run = runIvyJob($run, $task);

        $lot->refresh();

        // De range komt uit PHP: min omlaag en max omhoog afgerond op € 100 -
        // het LLM raakt nooit rechtstreeks een bedrag (hfst. 8).
        expect($run->status)->toBe(AiRunStatus::Completed)
            ->and($lot->value_min_cents)->toBeLessThanOrEqual($lot->value_max_cents)
            ->and($lot->value_min_cents % 10_000)->toBe(0)
            ->and($lot->value_max_cents % 10_000)->toBe(0)
            ->and($lot->value_confidence)->toBeInstanceOf(ValueConfidence::class);
    });
});

describe('de noodrem en het budget', function () {
    it('slaat over als de noodrem om staat', function () {
        config(['aucvy.ai.enabled' => false]);

        $lot = Lot::factory()->anonymousDraft()->create();
        [$run, $task] = ivyRun($lot);
        $run = runIvyJob($run, $task);

        expect($run->status)->toBe(AiRunStatus::Skipped)
            ->and($run->error)->toContain('Noodrem');
    });

    it('slaat over als het omgevingsbudget op is', function () {
        $lot = Lot::factory()->anonymousDraft()->create();

        // Een eerdere dure dag.
        AiRun::create([
            'agent' => 'ivy', 'task' => 'recognise_lot', 'input' => [],
            'status' => AiRunStatus::Completed,
            'cost_cents' => (int) config('aucvy.ai.daily_budget_cents_total'),
        ]);

        [$run, $task] = ivyRun($lot);
        $run = runIvyJob($run, $task);

        expect($run->status)->toBe(AiRunStatus::Skipped)
            ->and($run->error)->toContain('omgeving');
    });

    it('slaat over als het dagbudget van de gebruiker op is', function () {
        $user = User::factory()->create();
        $lot = Lot::factory()->anonymousDraft()->create(['seller_id' => $user->id]);

        AiRun::create([
            'user_id' => $user->id,
            'agent' => 'ivy', 'task' => 'recognise_lot', 'input' => [],
            'status' => AiRunStatus::Completed,
            'cost_cents' => (int) config('aucvy.ai.daily_budget_cents_per_user'),
        ]);

        [$run, $task] = ivyRun($lot);
        $run = runIvyJob($run, $task);

        expect($run->status)->toBe(AiRunStatus::Skipped)
            ->and($run->error)->toContain('gebruiker');
    });
});

describe('de cache', function () {
    it('hergebruikt een eerdere geslaagde run met dezelfde invoer', function () {
        $stub = stubProvider(json_encode([
            'object_type' => 'Koelcel', 'brand' => null, 'model' => null,
            'year' => null, 'condition' => 'good', 'specifications' => [],
            'confidence' => 0.7,
        ]));

        $lot = Lot::factory()->anonymousDraft()->create(['description' => 'Koelcel.']);

        [$first, $firstTask] = ivyRun($lot);
        runIvyJob($first, $firstTask);

        [$second, $secondTask] = ivyRun($lot->fresh());
        $second = runIvyJob($second, $secondTask);

        // Tweede run: zelfde uitkomst, nul kosten, en de provider is maar
        // één keer aangeroepen.
        expect($stub->calls)->toBe(1)
            ->and($second->status)->toBe(AiRunStatus::Completed)
            ->and($second->output)->toEqual($first->fresh()->output)
            ->and($second->cost_cents)->toBe(0);
    });
});

describe('de guards', function () {
    it('geeft één herkansing bij een schemafout en telt de tokens op', function () {
        stubProvider(
            'Sorry, hier is wat tekst zonder json.',
            json_encode([
                'object_type' => 'Steiger', 'brand' => null, 'model' => null,
                'year' => null, 'condition' => 'used', 'specifications' => [],
                'confidence' => 0.6,
            ]),
        );

        $lot = Lot::factory()->anonymousDraft()->create();
        [$run, $task] = ivyRun($lot);
        $run = runIvyJob($run, $task);

        expect($run->status)->toBe(AiRunStatus::Completed)
            ->and($run->tokens_in)->toBe(200)
            ->and($run->tokens_out)->toBe(100);
    });

    it('faalt netjes als ook de herkansing niet aan het schema voldoet', function () {
        stubProvider('{"onzin": true}');

        $lot = Lot::factory()->anonymousDraft()->create();
        [$run, $task] = ivyRun($lot);
        $run = runIvyJob($run, $task);

        expect($run->status)->toBe(AiRunStatus::Failed)
            ->and($run->error)->toContain('herkansing')
            ->and($run->output)->toBeNull();
    });

    it('geeft een verboden formulering één herkansing om te herschrijven', function () {
        $stub = stubProvider(
            json_encode([
                'title' => 'Topkeuken', 'confidence' => 0.9,
                'description' => 'Werking gegarandeerd, betaling via escrow.',
            ]),
            json_encode([
                'title' => 'Nette keuken', 'confidence' => 0.8,
                'description' => 'Gebruikssporen aanwezig; zie de foto\'s.',
            ]),
        );

        $lot = Lot::factory()->anonymousDraft()->create(['title' => null, 'description' => 'Keuken.']);
        [$run, $task] = ivyRun($lot, 'draft_listing');
        $run = runIvyJob($run, $task);

        expect($stub->calls)->toBe(2)
            ->and($run->status)->toBe(AiRunStatus::Completed)
            ->and($lot->fresh()->title)->toBe('Nette keuken');
    });

    it('blokkeert verboden formuleringen hard als ook de herkansing fout is', function () {
        stubProvider(json_encode([
            'title' => 'Topkeuken', 'confidence' => 0.9,
            'description' => 'Werking gegarandeerd, betaling via escrow.',
        ]));

        $lot = Lot::factory()->anonymousDraft()->create(['title' => null, 'description' => 'Keuken.']);
        [$run, $task] = ivyRun($lot, 'draft_listing');
        $run = runIvyJob($run, $task);

        // Ongevalideerde of verboden uitvoer komt nooit op een scherm: de run
        // faalt en het concept blijft onaangeraakt.
        expect($run->status)->toBe(AiRunStatus::Failed)
            ->and($run->error)->toContain('verboden formulering')
            ->and($lot->fresh()->title)->toBeNull();
    });

    it('faalt netjes als de provider stukgaat', function () {
        app()->instance(LlmProvider::class, new class implements LlmProvider
        {
            public function complete(AiTask $task, string $prompt): AiResult
            {
                throw new RuntimeException('API onbereikbaar');
            }
        });

        $lot = Lot::factory()->anonymousDraft()->create();
        [$run, $task] = ivyRun($lot);
        $run = runIvyJob($run, $task);

        expect($run->status)->toBe(AiRunStatus::Failed)
            ->and($run->error)->toContain('API onbereikbaar');
    });
});
