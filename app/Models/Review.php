<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reviews zijn niet cosmetisch: de verdeling over álle sterren wordt getoond,
 * kritische reviews blijven staan, en verwijderen kan niet - ook niet op
 * verzoek. Dat is een technische regel, geen redactionele (hfst. 13.6).
 */
#[Fillable([
    'order_id', 'reviewer_id', 'seller_id', 'company_id', 'rating', 'body',
])]
class Review extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'seller_replied_at' => 'datetime',
            'published_at' => 'datetime',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @param  Builder<Review>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }
}
