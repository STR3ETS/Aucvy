<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiAgent;
use App\Enums\InsightSeverity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De kaarten op het dashboard. De pagina doet zelf geen AI-calls; hij leest
 * alleen deze tabel (hfst. 13.5).
 */
#[Fillable([
    'user_id', 'agent', 'type', 'title', 'body', 'action_route',
    'action_label', 'action_params', 'severity', 'valid_until',
])]
class AiInsight extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agent' => AiAgent::class,
            'severity' => InsightSeverity::class,
            'action_params' => 'array',
            'valid_until' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Wat vandaag op het dashboard hoort te staan. Een signaal dat niet meer
     * klopt verdwijnt vanzelf in plaats van te blijven staan tot iemand hem
     * wegklikt.
     *
     * @param  Builder<AiInsight>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('dismissed_at')
            ->where(function (Builder $query): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
            });
    }

    /**
     * Geen kaart zonder actie (hfst. 13.5).
     */
    public function hasAction(): bool
    {
        return $this->action_route !== null;
    }
}
