<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Ivy is naar buiten één assistent en achter de schermen zes specialisten.
 *
 * Elk signaal op het dashboard draagt de naam van de specialist die het
 * opmerkte, met zijn kleur. Dat maakt een advies navolgbaar in plaats van
 * magisch (hfst. 13.5).
 */
enum AiAgent: string
{
    use HasLabel;

    case Ivy = 'ivy';
    case Atlas = 'atlas';
    case Nova = 'nova';
    case Finn = 'finn';
    case Flow = 'flow';
    case Lex = 'lex';

    /**
     * Waar deze specialist over gaat. Verschijnt onder zijn naam op het
     * dashboard.
     */
    public function speciality(): string
    {
        return __('enums.ai_agent_speciality.'.$this->value);
    }

    /**
     * De Tailwind-tokennaam uit resources/css/app.css. Deze kleuren zijn
     * getoetst op minimaal 5,4:1 als tekst op licht - niet zelf bijstellen
     * (hfst. 10).
     */
    public function colorToken(): string
    {
        return 'aj-'.$this->value;
    }
}
