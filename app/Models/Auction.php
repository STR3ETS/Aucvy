<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Het object waarop gelockt wordt bij elk bod.
 *
 * Dit model blijft bewust dom: het kent zijn eigen velden en relaties, maar
 * plaatst geen biedingen en sluit zichzelf niet. Dat gebeurt uitsluitend in
 * App\Auctions\Actions (stap 3) - één plek waar een bod kan ontstaan.
 */
#[Fillable([
    'lot_id', 'starts_at', 'ends_at', 'original_ends_at', 'start_price_cents',
    'reserve_price_cents', 'current_price_cents', 'increment_table',
    'anti_snipe_window_s', 'anti_snipe_extend_s', 'buy_now_price_cents',
    'buy_now_active', 'dutch_start_cents', 'dutch_floor_cents',
    'dutch_step_cents', 'dutch_interval_s',
])]
class Auction extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'original_ends_at' => 'datetime',
            'closed_at' => 'datetime',
            'paused_at' => 'datetime',
            'increment_table' => 'array',
            'start_price_cents' => 'integer',
            'reserve_price_cents' => 'integer',
            'current_price_cents' => 'integer',
            'buy_now_price_cents' => 'integer',
            'dutch_start_cents' => 'integer',
            'dutch_floor_cents' => 'integer',
            'dutch_step_cents' => 'integer',
            'dutch_interval_s' => 'integer',
            'anti_snipe_window_s' => 'integer',
            'anti_snipe_extend_s' => 'integer',
            'extension_count' => 'integer',
            'bid_count' => 'integer',
            'bidder_count' => 'integer',
            'last_sequence' => 'integer',
            'buy_now_active' => 'boolean',
            'reserve_met' => 'boolean',
        ];
    }

    // Relaties -------------------------------------------------------------

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function autoBids(): HasMany
    {
        return $this->hasMany(AutoBid::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AuctionEvent::class)->orderBy('sequence');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id')->withTrashed();
    }

    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'winning_bid_id');
    }

    /**
     * Ontstaat pas bij het sluiten, en alleen als de reserveprijs gehaald is.
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    // Scopes ---------------------------------------------------------------

    /**
     * Het vangnet achter CloseAuctionJob: veilingen die over hun eindtijd heen
     * zijn maar nog niet gesloten. Dit hoort normaal niets te vinden. Vindt hij
     * structureel wél iets, dan is dat een incident en geen routine (hfst. 6).
     *
     * @param  Builder<Auction>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereNull('closed_at')->whereNull('paused_at')->where('ends_at', '<=', now());
    }

    // Afgeleide waarden ----------------------------------------------------

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    /**
     * Stilgezet door de verkoper of beheer: de klok staat stil en er wordt
     * niet geboden, maar de veiling is niet voorbij (ResumeLot schuift de
     * sluittijd op).
     */
    public function isPaused(): bool
    {
        return $this->closed_at === null && $this->paused_at !== null;
    }

    /**
     * De slotfase, waarin een bod de veiling verlengt.
     */
    public function isInAntiSnipeWindow(): bool
    {
        if ($this->isClosed()) {
            return false;
        }

        return $this->ends_at->diffInSeconds(now(), absolute: true) <= $this->anti_snipe_window_s
            && $this->ends_at->isFuture();
    }

    public function wasExtended(): bool
    {
        return $this->extension_count > 0;
    }

    /**
     * Of deze veiling daadwerkelijk als afslag is ingericht.
     *
     * Een kavel kan `sale_type = dutch` dragen terwijl de instellingen nog
     * ontbreken - bijvoorbeeld als een concept halverwege is gepubliceerd.
     * De biedmotor hoort daar hard op te vallen, maar een lijstweergave niet:
     * één verkeerd geconfigureerde kavel mag de hele catalogus niet blanco
     * maken.
     */
    public function hasDutchSettings(): bool
    {
        return $this->dutch_start_cents !== null
            && $this->dutch_floor_cents !== null
            && $this->dutch_step_cents !== null
            && $this->dutch_interval_s !== null
            && $this->dutch_interval_s > 0;
    }
}
