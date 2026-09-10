<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\VatRegime;
use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'order_number', 'lot_id', 'auction_id', 'buyer_id', 'seller_id',
    'hammer_cents', 'buyer_premium_cents', 'transport_cents', 'vat_cents',
    'total_cents', 'vat_regime', 'status', 'payment_deadline_at',
])]
#[RouteKey('order_number')]
#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'vat_regime' => VatRegime::class,
            'hammer_cents' => 'integer',
            'buyer_premium_cents' => 'integer',
            'transport_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'payment_deadline_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'dispute_window_ends_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Relaties -------------------------------------------------------------

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    // Afgeleide waarden ----------------------------------------------------

    /**
     * Of de koper nog een probleem kan melden. Zolang dit venster open staat,
     * blijft de uitbetaling aangehouden (hfst. 9).
     */
    public function isWithinDisputeWindow(): bool
    {
        return $this->dispute_window_ends_at !== null
            && $this->dispute_window_ends_at->isFuture();
    }

    public function canBeReviewed(): bool
    {
        return $this->status->allowsReview();
    }
}
