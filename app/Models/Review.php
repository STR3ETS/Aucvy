<?php

declare(strict_types=1);

namespace App\Models;

use App\Reviews\ReviewReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reviews zijn niet cosmetisch: de verdeling over álle sterren wordt getoond
 * en kritische reviews blijven staan. Volgens het Google-principe (besluit
 * Raph, 2 november 2026) gaat een review door een automatische controle
 * vóór publicatie, kan iedereen hem melden, en haalt alleen beheer hem
 * offline, met reden en spoor. De verkoper reageert, maar verwijdert niets.
 *
 * Gepubliceerd betekent: published_at gezet. Tegengehouden en verwijderd
 * hebben geen published_at, zodat elke telling die al op published_at
 * filterde vanzelf klopt.
 */
#[Fillable([
    'order_id', 'reviewer_id', 'seller_id', 'company_id', 'rating', 'body',
])]
class Review extends Model
{
    use HasFactory;

    public const string STATUS_PUBLISHED = 'published';

    public const string STATUS_HELD = 'held';

    public const string STATUS_REMOVED = 'removed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'seller_replied_at' => 'datetime',
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
            'moderation_reason' => ReviewReason::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ReviewReport::class);
    }

    public function isPublic(): bool
    {
        return $this->published_at !== null && $this->moderation_status === self::STATUS_PUBLISHED;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('moderation_status', self::STATUS_PUBLISHED);
    }
}
