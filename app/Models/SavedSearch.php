<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ivy's "ik bewaak de markt voor je", en de uitweg uit een lege
 * zoekresultatenpagina (hfst. 13.2).
 */
#[Fillable(['user_id', 'name', 'query', 'interpreted', 'channel'])]
class SavedSearch extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query' => 'array',
            'interpreted' => 'array',
            'channel' => NotificationChannel::class,
            'last_run_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('paused_at');
    }
}
