<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Alles wat geld, status of eigendom raakt. Append-only en niet muteerbaar
 * vanuit de applicatie (hfst. 15).
 */
#[Fillable([
    'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after',
    'ip', 'created_at',
])]
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Bewijs wordt niet bijgewerkt of verwijderd. Een verwijderverzoek
     * loopt via anonimisering van de actor, niet via deze rijen (hfst. 15).
     */
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Auditregels zijn bewijs en worden niet bijgewerkt.'));
        static::deleting(fn (): never => throw new LogicException('Auditregels zijn bewijs en worden niet verwijderd.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
