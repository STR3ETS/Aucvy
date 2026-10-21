<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Elk binnengekomen webhook-event van een externe partij (betaalprovider,
 * straks de identiteitsdienst), uniek per provider en event-id. Dubbele of
 * verlate events zijn daardoor een no-op, en de rij is het spoor voor een
 * replay (briefing sectie 7, flow D).
 */
#[Fillable(['provider', 'event_id', 'type', 'payload', 'received_at', 'processed_at', 'status'])]
class ProviderEvent extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function markProcessed(string $status = self::STATUS_PROCESSED): void
    {
        $this->update(['status' => $status, 'processed_at' => now()]);
    }
}
