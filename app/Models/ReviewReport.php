<?php

declare(strict_types=1);

namespace App\Models;

use App\Reviews\ReviewReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een melding van een review door een bezoeker. Melden haalt niets weg:
 * de review blijft zichtbaar tot een medewerker beslist (Google-principe).
 */
#[Fillable(['review_id', 'reporter_id', 'reason', 'note'])]
class ReviewReport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReviewReason::class,
            'handled_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id')->withTrashed();
    }
}
