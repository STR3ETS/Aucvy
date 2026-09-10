<?php

declare(strict_types=1);

namespace App\Models;

use App\Auctions\Support\DutchPriceCalculator;
use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Enums\VatRegime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'public_id', 'seller_id', 'company_id', 'category_id', 'location_id',
    'project_id', 'title', 'slug', 'description', 'brand', 'model', 'year',
    'quantity', 'condition', 'specifications', 'status', 'sale_type', 'vat_regime',
    'completeness_score', 'value_min_cents', 'value_max_cents',
    'value_confidence', 'pickup_only', 'viewing_available',
    'transport_from_cents', 'draft_token', 'draft_meta', 'published_at',
])]
#[RouteKey('slug')]
class Lot extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'status' => LotStatus::class,
            'condition' => LotCondition::class,
            'sale_type' => SaleType::class,
            'vat_regime' => VatRegime::class,
            'value_confidence' => ValueConfidence::class,
            'completeness_score' => 'integer',
            'quantity' => 'integer',
            'year' => 'integer',
            'value_min_cents' => 'integer',
            'value_max_cents' => 'integer',
            'transport_from_cents' => 'integer',
            'pickup_only' => 'boolean',
            'viewing_available' => 'boolean',
            'draft_meta' => 'array',
            'published_at' => 'datetime',
        ];
    }

    // Relaties -------------------------------------------------------------

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function auction(): HasOne
    {
        return $this->hasOne(Auction::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(LotMedia::class)->orderBy('position');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LotDocument::class);
    }

    public function viewings(): HasMany
    {
        return $this->hasMany(Viewing::class);
    }

    public function order(): HasOne
    {
        return $this->hasOne(Order::class);
    }

    // Scopes ---------------------------------------------------------------

    /**
     * @param  Builder<Lot>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->whereIn('status', array_map(
            fn (LotStatus $status): string => $status->value,
            array_filter(LotStatus::cases(), fn (LotStatus $status): bool => $status->isPublic()),
        ));
    }

    /**
     * @param  Builder<Lot>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereIn('status', [LotStatus::Live, LotStatus::Closing]);
    }

    // Weergave -------------------------------------------------------------

    /**
     * Toont de waarde-indicatie alleen als beide grenzen én de zekerheid er
     * zijn. Altijd samen tonen of niet tonen - een losse ondergrens leest als
     * een belofte (hfst. 5).
     */
    public function hasValueEstimate(): bool
    {
        return $this->value_min_cents !== null
            && $this->value_max_cents !== null
            && $this->value_confidence !== null;
    }

    /**
     * Het label "% onder waarde-indicatie" verdwijnt bij lage zekerheid - de
     * indicatie is dan niet scherp genoeg om als koopargument te dienen
     * (hfst. 13.2).
     */
    public function mayShowValueComparison(): bool
    {
        return $this->hasValueEstimate()
            && $this->value_confidence->allowsExactAdvice();
    }

    public function isDraft(): bool
    {
        return $this->status === LotStatus::Draft;
    }

    /**
     * De prijs die op dit moment geldt.
     *
     * Bij een afslag is dat niet de opgeslagen kolom: die loopt bewust achter,
     * omdat de prijs een functie van de tijd is en er niets per tik
     * weggeschreven wordt (hfst. 6). Een kavelkaart die de kolom zou tonen
     * adverteert een afslag van € 20.000 die inmiddels € 8.000 kost.
     */
    public function currentPriceCents(): int
    {
        $auction = $this->auction;

        if ($auction === null) {
            return 0;
        }

        if ($this->sale_type === SaleType::Dutch
            && ! $auction->isClosed()
            && $auction->hasDutchSettings()
        ) {
            return app(DutchPriceCalculator::class)->priceAt($auction);
        }

        return $auction->current_price_cents;
    }
}
