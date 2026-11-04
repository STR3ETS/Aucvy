<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuctionEventType;
use App\Observers\DossierObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. De bron voor replay na een netwerkdip én voor het auditlog.
 */
#[Fillable(['auction_id', 'sequence', 'type', 'payload', 'created_at'])]
#[ObservedBy(DossierObserver::class)]
class AuctionEvent extends Model
{
    use HasFactory;

    /**
     * Geen updated_at: een event wordt geschreven en daarna nooit meer
     * aangeraakt.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'type' => AuctionEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    /**
     * De replay die de client opvraagt met /events?after={seq}.
     *
     * @param  Builder<AuctionEvent>  $query
     */
    public function scopeAfterSequence(Builder $query, int $sequence): void
    {
        $query->where('sequence', '>', $sequence)->orderBy('sequence');
    }
}
