<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Het maximum waar Ivy binnen meestapt.
 *
 * Dit bedrag is nooit zichtbaar - niet voor andere bieders, niet voor de
 * verkoper, en niet op een broadcastkanaal. Een maximumbod dat lekt, is een
 * gebruiker die geld verliest (hfst. 7).
 */
#[Fillable(['auction_id', 'user_id', 'max_amount_cents', 'active'])]
class AutoBid extends Model
{
    use HasFactory;

    /*
     | `active_flag` staat bewust niet in #[Fillable]: die kolom wordt door de
     | database zelf berekend en is niet schrijfbaar.
     */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_amount_cents' => 'integer',
            'active' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @param  Builder<AutoBid>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
