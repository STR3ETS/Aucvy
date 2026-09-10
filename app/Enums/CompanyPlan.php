<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De tarieven uit de designs: 7,5% / 3,5% / 2,5% succesfee.
 *
 * Het percentage staat óók als kolom op `companies`, zodat een afwijkende
 * afspraak met een grote verkoper geen codewijziging vraagt. Deze waarden
 * zijn de standaard bij het aanmaken.
 */
enum CompanyPlan: string
{
    use HasLabel;

    case Start = 'start';
    case Pro = 'pro';
    case Enterprise = 'enterprise';

    public function defaultFeePercentage(): float
    {
        return match ($this) {
            self::Start => 7.5,
            self::Pro => 3.5,
            self::Enterprise => 2.5,
        };
    }
}
