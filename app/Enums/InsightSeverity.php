<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Hoe zwaar een dashboardsignaal weegt. Geen kaart zonder actie: kun je geen
 * concrete handeling bedenken, dan is het geen signaal maar een cijfer en
 * hoort het lager op de pagina (hfst. 13.5).
 */
enum InsightSeverity: string
{
    use HasLabel;

    case Info = 'info';
    case Attention = 'attention';
    case Urgent = 'urgent';
}
