<?php

declare(strict_types=1);

namespace App\Models;

use App\Auctions\Support\DutchPriceCalculator;
use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Enums\VatRegime;
use App\Lots\Translation;
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
    'public_id', 'seller_id', 'company_id', 'category_id', 'sale_id', 'sale_position', 'location_id',
    'project_id', 'title', 'slug', 'description', 'brand', 'model', 'gtin', 'serial_number', 'year',
    'quantity', 'condition', 'specifications', 'status', 'sale_type', 'vat_regime',
    'completeness_score', 'value_min_cents', 'value_max_cents',
    'value_confidence', 'pickup_only', 'viewing_available',
    'transport_from_cents', 'draft_token', 'draft_meta', 'field_sources', 'translations', 'published_at', 'interest_score', 'is_hot', 'hot_since',
    'unit', 'warranty', 'warranty_until',
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
            'screening' => 'array',
            'warranty_until' => 'date',
            'documents_declared_at' => 'datetime',
            'submitted_at' => 'datetime',
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
            'field_sources' => 'array',
            'translations' => 'array',
            'published_at' => 'datetime',
            'is_hot' => 'boolean',
            'hot_since' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    // Relaties -------------------------------------------------------------

    /**
     * De vertaling in een taal, alleen zolang hij bij de huidige Nederlandse
     * tekst hoort (briefing stap 4: vertaling).
     *
     * @return array{title: string, description: string, hash: string, run_id: int|null, source: string, translated_at: string}|null
     */
    public function translation(string $locale): ?array
    {
        $entry = $this->translations[$locale] ?? null;

        if (! is_array($entry) || ($entry['hash'] ?? null) !== Translation::hash($this)) {
            return null;
        }

        return $entry;
    }

    /**
     * Wie aan deze kavel mag zitten: de verkoper zelf, of een collega met
     * een rol die kavels mag bewerken (briefing sectie 2).
     */
    public function isManagedBy(User $user): bool
    {
        if ($this->seller_id === $user->id) {
            return true;
        }

        return $this->company_id !== null && $this->company_id === $user->company_id && $user->canEditCompanyLots();
    }

    /**
     * De veiling (Auction Studio) waar deze kavel in zit, als hij er in zit.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * De medewerker die de kavel na publicatie heeft beoordeeld.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
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

    public function publicAnswers(): HasMany
    {
        return $this->hasMany(LotPublicAnswer::class)->latest('id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
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
