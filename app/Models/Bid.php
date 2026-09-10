<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BidStatus;
use App\Enums\BidType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een bod ontstaat uitsluitend via App\Auctions\Actions\PlaceBid.
 *
 * Niet in een controller, niet in een observer, niet in een seeder, niet in
 * Tinker. Heb je een tweede plek nodig, dan is het antwoord een extra Action
 * die intern PlaceBid aanroept (hfst. 3).
 *
 * Rijen worden nooit verwijderd en nooit gewijzigd, behalve `status`.
 */
#[Fillable([
    'auction_id', 'user_id', 'amount_cents', 'type', 'status', 'sequence',
    'idempotency_key', 'placed_at', 'ip', 'user_agent',
])]
class Bid extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'sequence' => 'integer',
            'type' => BidType::class,
            'status' => BidStatus::class,
            'placed_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * De biedhistorie zoals de kavelpagina hem toont: nieuwste bovenaan.
     *
     * @param  Builder<Bid>  $query
     */
    public function scopeHistory(Builder $query): void
    {
        $query->where('status', '!=', BidStatus::Rejected)
            ->orderByDesc('sequence');
    }

    /**
     * Wat op het publieke kanaal mag. Geen volledige naam, geen e-mailadres,
     * en nooit een maximumbod (hfst. 7).
     *
     * @return array<string, mixed>
     */
    public function toPublicPayload(): array
    {
        return [
            'sequence' => $this->sequence,
            'amount_cents' => $this->amount_cents,
            'bidder_label' => $this->user->publicName(),
            'type' => $this->type->value,
            'is_automatic' => $this->type->isAutomatic(),
            'placed_at' => $this->placed_at->toIso8601String(),
        ];
    }
}
