<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AiRunStatus;
use App\Ivy\Jobs\RunAiTask;
use App\Ivy\TaskRegistry;
use App\Models\AiRun;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Zet voor elke actieve verkoper de wekelijkse Ivy-analyse in de wachtrij
 * (hfst. 8: WeeklyInsights, alle zes agents; hfst. 13.5: het blok "Wat Ivy
 * deze week zag").
 *
 * Via dezelfde RunAiTask als elke andere taak: noodrem, budget, cache en
 * guards gelden onverkort, en alles landt in `ai_runs`.
 */
final class RunWeeklyInsights extends Command
{
    protected $signature = 'ivy:weekly-insights';

    protected $description = 'Plant de wekelijkse Ivy-bevindingen in voor elke verkoper met kavels';

    public function handle(): int
    {
        $sellers = User::query()->whereHas('lots')->get();

        foreach ($sellers as $seller) {
            $task = TaskRegistry::weeklyInsightsFor($seller);

            $run = AiRun::create([
                'subject_type' => $seller->getMorphClass(),
                'subject_id' => $seller->id,
                'user_id' => $seller->id,
                'agent' => $task->agent(),
                'task' => $task->name(),
                'input' => $task->input(),
                'cache_key' => $task->cacheKey(),
                'status' => AiRunStatus::Queued,
            ]);

            RunAiTask::dispatch($run, $task);
        }

        $this->info("{$sellers->count()} verkopers ingepland.");

        return self::SUCCESS;
    }
}
