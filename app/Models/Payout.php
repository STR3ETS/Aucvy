<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'seller_id', 'amount_cents', 'fee_cents', 'status', 'hold_until',
    'released_at', 'failure_reason',
])]
class Payout extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount_cents' => 'integer',
            'fee_cents' => 'integer',
            'hold_until' => 'datetime',
            'released_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Wat de scheduled job oppikt zodra de houdtermijn verstreken is. Dit is
     * de 48 uur die de zekerheidspagina belooft - een kolom, geen belofte.
     *
     * @param  Builder<Payout>  $query
     */
    public function scopeReleasable(Builder $query): void
    {
        $query->where('status', PayoutStatus::Held)
            ->whereNotNull('hold_until')
            ->where('hold_until', '<=', now());
    }
}
