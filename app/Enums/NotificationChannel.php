<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * E-mail is te laat voor "je bent overboden" - tegen de tijd dat iemand het
 * leest is de veiling voorbij. Voor tijdkritische meldingen is push de norm
 * (hfst. 14); e-mail blijft voor bewaarde zoekopdrachten prima.
 */
enum NotificationChannel: string
{
    use HasLabel;

    case Email = 'email';
    case Push = 'push';
}
