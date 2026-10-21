<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Een bewaard antwoord op een aanvraag met een idempotency key. Zie
 * App\Support\Idempotency\Idempotency.
 */
#[Fillable(['scope', 'key', 'response', 'expires_at'])]
class IdempotencyKey extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
