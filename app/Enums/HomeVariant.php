<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De ontwerpen van de home in de A/B-test: het oude ontwerp uit
 * aucvy-home.html (A), het nieuwe ontwerp uit preview v4 (B) en de hybride
 * (C): A's opbouw in de stijlgids die A en B combineert.
 *
 * De waarde is de letter zoals hij in de cookie en de querystring staat
 * (?variant=c), zodat een variant deelbaar is via de URL.
 */
enum HomeVariant: string
{
    use HasLabel;

    case Current = 'a';
    case Redesign = 'b';
    case Hybrid = 'c';

    public function view(): string
    {
        return match ($this) {
            self::Current => 'home',
            self::Redesign => 'home-v4',
            self::Hybrid => 'home-c',
        };
    }
}
