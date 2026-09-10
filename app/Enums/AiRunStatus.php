<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Elke AI-taak landt in `ai_runs`, geslaagd of niet. Zonder dat log kun je
 * later niet uitleggen waarom Ivy iets zei, en dat ga je een keer moeten
 * kunnen (hfst. 8).
 *
 * `Skipped` is wat er gebeurt als de noodrem om staat of het budget op is.
 * Dat is geen fout - de applicatie hoort dan gewoon door te werken.
 */
enum AiRunStatus: string
{
    use HasLabel;

    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
