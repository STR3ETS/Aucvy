<?php

declare(strict_types=1);

namespace App\Ivy\Contracts;

use App\Models\AiRun;

/**
 * Een taak waarvan de uitkomst niet mag blijven hangen als Ivy niet kan
 * antwoorden (noodrem, budget op, providerfout, ongeldige uitvoer). De
 * controle vóór publicatie gebruikt dit om de kavel dan naar een
 * medewerker te sturen in plaats van hem eeuwig "in beoordeling" te laten.
 */
interface HandlesFailedRun
{
    public function failed(AiRun $run): void;
}
