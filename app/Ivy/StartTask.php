<?php

declare(strict_types=1);

namespace App\Ivy;

use App\Enums\AiRunStatus;
use App\Ivy\Contracts\AiTask;
use App\Ivy\Jobs\RunAiTask;
use App\Models\AiRun;
use App\Models\User;

/**
 * Een Ivy-taak starten vanuit de applicatie zelf (publicatie, bewerking,
 * planning): dezelfde run-administratie en dezelfde noodrem als de API.
 */
final class StartTask
{
    public static function run(AiTask $task, ?User $user = null): AiRun
    {
        $subject = $task->subject();

        $run = AiRun::create([
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'user_id' => $user?->id,
            'agent' => $task->agent(),
            'task' => $task->name(),
            'input' => $task->input(),
            'cache_key' => $task->cacheKey(),
            'status' => AiRunStatus::Queued,
        ]);

        if (! config('aucvy.ai.enabled')) {
            $run->update([
                'status' => AiRunStatus::Skipped,
                'error' => 'Noodrem: Ivy staat uit (aucvy.ai.enabled).',
                'latency_ms' => 0,
            ]);

            return $run;
        }

        RunAiTask::dispatch($run, $task)->afterCommit();

        return $run;
    }
}
