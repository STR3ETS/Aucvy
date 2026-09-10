<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum UserType: string
{
    use HasLabel;

    case Private = 'private';
    case Business = 'business';

    /**
     * Zakelijke accounts moeten een geverifieerde KvK-koppeling hebben voordat
     * ze mogen verkopen; particulieren niet (briefing hfst. 13.6).
     */
    public function requiresCompany(): bool
    {
        return $this === self::Business;
    }
}
