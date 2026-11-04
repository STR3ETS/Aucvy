<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentKind;
use App\Observers\DossierObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Staat altijd op een private disk. Het `path` mag nooit rechtstreeks in een
 * URL belanden - uitsluitend via een getekende URL van maximaal vijf minuten
 * (hfst. 15).
 */
#[Fillable([
    'lot_id', 'kind', 'path', 'original_filename', 'mime_type', 'size_bytes',
])]
#[ObservedBy(DossierObserver::class)]
class LotDocument extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'size_bytes' => 'integer',
            'ai_checked_at' => 'datetime',
            'ai_findings' => 'array',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * Alleen een document dat leesbaar was zonder opmerkingen draagt de
     * badge; een bevinding is geen keurmerk.
     */
    public function wasCheckedByIvy(): bool
    {
        return $this->ai_checked_at !== null
            && ($this->ai_findings['readable'] ?? false) === true
            && blank($this->ai_findings['issues'] ?? null);
    }
}
