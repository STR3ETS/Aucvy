<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Zekerheid van een Ivy-schatting. Verplicht zichtbaar bij elke uitkomst
 * (briefing hfst. 8) - nooit een bedrag zonder deze context.
 */
enum ValueConfidence: string
{
    use HasLabel;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * Bij lage zekerheid mag er geen enkel exact bedrag als advies uit, en
     * verdwijnt ook het label "% onder waarde-indicatie" van de kavelkaart
     * (hfst. 13.2). Dat is een productregel, geen stijlkeuze.
     */
    public function allowsExactAdvice(): bool
    {
        return $this !== self::Low;
    }
}
