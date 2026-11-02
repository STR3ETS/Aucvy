<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén soort fout in het foutlog van beheer, met hoe vaak hij optrad en wie
 * hem afhandelde (zie App\Support\Health\ErrorLog).
 */
#[Fillable([
    'fingerprint', 'class', 'message', 'file', 'line', 'url', 'method', 'last_user_id',
    'occurrences', 'first_seen_at', 'last_seen_at', 'resolved_at', 'resolved_by', 'resolution_note',
])]
class ErrorEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line' => 'integer',
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by')->withTrashed();
    }

    public function lastUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_user_id')->withTrashed();
    }

    /**
     * @param  Builder<ErrorEvent>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }
}
