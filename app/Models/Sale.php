<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleState;
use Carbon\CarbonImmutable;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een veiling als verzameling kavels van één verkoper (Auction Studio,
 * briefing sectie 4): sluitschema, bezichtiging, ophalen en voorwaarden.
 * Elke kavel houdt zijn eigen biedronde; de veiling plant de sluitingen.
 */
#[Fillable([
    'public_id', 'seller_id', 'company_id', 'title', 'slug', 'description',
    'ends_at', 'closing_gap_seconds', 'viewing_at', 'viewing_note',
    'pickup_from', 'pickup_until', 'pickup_note', 'terms', 'published_at', 'cancelled_at',
])]
#[RouteKey('slug')]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ends_at' => 'immutable_datetime',
            'viewing_at' => 'immutable_datetime',
            'pickup_from' => 'immutable_date',
            'pickup_until' => 'immutable_date',
            'published_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'closing_gap_seconds' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class)->orderBy('sale_position')->orderBy('id');
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->whereNull('cancelled_at');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->cancelled_at === null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Wie aan deze veiling mag zitten: de verkoper, of een collega met een
     * rol die kavels mag bewerken.
     */
    public function isManagedBy(User $user): bool
    {
        if ($this->seller_id === $user->id) {
            return true;
        }

        return $this->company_id !== null && $this->company_id === $user->company_id && $user->canEditCompanyLots();
    }

    /**
     * Sluittijd van de kavel op deze positie (nul is de eerste).
     */
    public function closingFor(int $position): CarbonImmutable
    {
        return $this->ends_at->addSeconds(max(0, $position) * $this->closing_gap_seconds);
    }

    public function lastClosingAt(): CarbonImmutable
    {
        // Uit withCount('lots') als de lijst die meenam; anders één telling.
        $count = (int) ($this->getAttributes()['lots_count'] ?? $this->lots()->count());

        return $this->closingFor(max(0, $count - 1));
    }

    /**
     * De tellingen waarmee een lijst state() zonder query's per rij kan
     * bepalen: `->withCount(Sale::stateCounts())`.
     *
     * @return array<int|string, mixed>
     */
    public static function stateCounts(): array
    {
        return [
            'lots',
            'lots as open_lots_count' => fn ($query) => $query->whereHas('auction', fn ($auction) => $auction->whereNull('closed_at')),
        ];
    }

    public function state(): SaleState
    {
        if ($this->isCancelled()) {
            return SaleState::Cancelled;
        }

        if (! $this->isPublished()) {
            return SaleState::Draft;
        }

        // Een lopende biedronde telt, ook als antisnipe hem over het schema heen tilde.
        // Uit withCount('open_lots') als de lijst die meenam (zie openLotsCount()).
        // Via de ruwe attributen: de strikte modus weigert een onbekend attribuut.
        $openLots = $this->getAttributes()['open_lots_count'] ?? null;

        if ($openLots === null
            ? $this->lots()->whereHas('auction', fn ($query) => $query->whereNull('closed_at'))->exists()
            : (int) $openLots > 0) {
            return SaleState::Live;
        }

        if (now()->greaterThanOrEqualTo($this->lastClosingAt())) {
            return SaleState::Closed;
        }

        return SaleState::Scheduled;
    }
}
