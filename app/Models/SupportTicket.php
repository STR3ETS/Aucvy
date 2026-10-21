<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een bericht via het contactformulier, tot beheer het sluit.
 */
#[Fillable(['user_id', 'name', 'email', 'subject', 'message', 'order_number', 'status', 'ip'])]
class SupportTicket extends Model
{
    use HasFactory;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
