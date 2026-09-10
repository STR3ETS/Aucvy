<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'auction_id', 'amount_cents', 'provider', 'provider_id', 'status',
])]
class Deposit extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'status' => DepositStatus::class,
            'released_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * @param  Builder<Deposit>  $query
     */
    public function scopeCovering(Builder $query): void
    {
        $query->where('status', DepositStatus::Authorised);
    }
}
