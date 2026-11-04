<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lot_id', 'auction_id', 'seller_id', 'room', 'provider', 'status', 'started_at', 'ended_at', 'viewers_now', 'peak_viewers', 'viewer_minutes', 'max_viewers', 'recording_id', 'end_reason'])]
class LiveSession extends Model
{
    public const string PREPARING = 'preparing';

    public const string LIVE = 'live';

    public const string ENDED = 'ended';

    public const string FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'viewers_now' => 'integer',
            'peak_viewers' => 'integer',
            'viewer_minutes' => 'integer',
            'max_viewers' => 'integer',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isLive(): bool
    {
        return $this->status === self::LIVE;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PREPARING, self::LIVE], true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [self::PREPARING, self::LIVE]);
    }

    public function statusLabel(): string
    {
        return __('live.status.'.$this->status);
    }
}
